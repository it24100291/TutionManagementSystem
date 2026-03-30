<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../src/config/env.php';
require_once __DIR__ . '/../../src/config/db.php';
require_once __DIR__ . '/student_mock_data.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const STUDENT_FIXED_PAYMENT_AMOUNT = 3500;

function studentPaymentsTableExists(PDO $db, string $tableName): bool {
    $stmt = $db->prepare(" 
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?
    ");
    $stmt->execute([$tableName]);
    return (int) $stmt->fetchColumn() > 0;
}

function studentPaymentsColumnExists(PDO $db, string $tableName, string $columnName): bool {
    $stmt = $db->prepare(" 
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
    ");
    $stmt->execute([$tableName, $columnName]);
    return (int) $stmt->fetchColumn() > 0;
}

function resolvePaymentsStudentEntityId(PDO $db, int $userId): int {
    if (
        studentPaymentsTableExists($db, 'students') &&
        studentPaymentsColumnExists($db, 'students', 'id') &&
        studentPaymentsColumnExists($db, 'students', 'user_id')
    ) {
        $stmt = $db->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $studentRowId = $stmt->fetchColumn();
        if ($studentRowId !== false) {
            return (int) $studentRowId;
        }
    }

    return $userId;
}

function normalizeStudentPaymentStatus(?string $status): string {
    $normalized = strtolower(trim((string) $status));
    return match ($normalized) {
        'paid' => 'Paid',
        'pending' => 'Pending',
        default => 'Unpaid',
    };
}

function resolveStudentPaymentMonthExpr(PDO $db, string $tableName): ?string {
    if (studentPaymentsColumnExists($db, $tableName, 'month')) {
        return 'month';
    }
    if (studentPaymentsColumnExists($db, $tableName, 'payment_month')) {
        return 'payment_month';
    }
    return null;
}

function resolveStudentPaymentDateExpr(PDO $db, string $tableName): ?string {
    if (studentPaymentsColumnExists($db, $tableName, 'payment_date')) {
        return 'payment_date';
    }
    if (studentPaymentsColumnExists($db, $tableName, 'created_at')) {
        return 'created_at';
    }
    return null;
}

function ensureStudentPaymentColumns(PDO $db, string $tableName): void {
    if (!studentPaymentsColumnExists($db, $tableName, 'receipt_path')) {
        $db->exec("ALTER TABLE {$tableName} ADD COLUMN receipt_path VARCHAR(255) NULL");
    }
}

function formatStudentPaymentMonthLabel(string $rawMonth): string {
    if (preg_match('/^\d{4}-\d{2}$/', $rawMonth)) {
        $date = DateTime::createFromFormat('Y-m', $rawMonth);
        if ($date) {
            return $date->format('F Y');
        }
    }

    $timestamp = strtotime($rawMonth);
    if ($timestamp !== false) {
        return date('F Y', $timestamp);
    }

    return $rawMonth;
}

function resolveStudentPaymentUploadPath(): string {
    $directory = dirname(__DIR__) . '/uploads/student-payment-receipts';
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
    return $directory;
}

function buildStudentPaymentResponse(PDO $db, int $studentId): array {
    $result = [
        'history' => [],
    ];

    $paymentsTable = null;
    if (studentPaymentsTableExists($db, 'payments')) {
        $paymentsTable = 'payments';
    } elseif (studentPaymentsTableExists($db, 'fee_payments')) {
        $paymentsTable = 'fee_payments';
    }

    if ($paymentsTable !== null && studentPaymentsColumnExists($db, $paymentsTable, 'student_id')) {
        ensureStudentPaymentColumns($db, $paymentsTable);
        $monthColumn = resolveStudentPaymentMonthExpr($db, $paymentsTable);
        $dateColumn = resolveStudentPaymentDateExpr($db, $paymentsTable);
        $statusColumnExists = studentPaymentsColumnExists($db, $paymentsTable, 'status');
        $receiptColumnExists = studentPaymentsColumnExists($db, $paymentsTable, 'receipt_path');
        $orderExpr = $dateColumn ? "{$dateColumn} DESC" : 'id DESC';
        $monthExpr = $monthColumn ? $monthColumn : "DATE_FORMAT(CURRENT_DATE(), '%Y-%m')";
        $dateExpr = $dateColumn ? "DATE_FORMAT({$dateColumn}, '%Y-%m-%d')" : "''";
        $statusExpr = $statusColumnExists ? 'status' : "'Unpaid'";
        $receiptExpr = $receiptColumnExists ? 'COALESCE(receipt_path, \'\')' : "''";

        $stmt = $db->prepare(" 
            SELECT
                {$monthExpr} AS raw_month,
                {$dateExpr} AS payment_date,
                {$statusExpr} AS status,
                {$receiptExpr} AS receipt_path
            FROM {$paymentsTable}
            WHERE student_id = ?
            ORDER BY {$orderExpr}
        ");
        $stmt->execute([$studentId]);

        $history = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $history[] = [
                'month' => formatStudentPaymentMonthLabel((string) ($row['raw_month'] ?? '')),
                'raw_month' => (string) ($row['raw_month'] ?? ''),
                'amount' => STUDENT_FIXED_PAYMENT_AMOUNT,
                'receipt_path' => (string) ($row['receipt_path'] ?? ''),
                'status' => normalizeStudentPaymentStatus($row['status'] ?? 'Unpaid'),
            ];
        }

        $result['history'] = $history;
    }

    if (empty($result['history'])) {
        $mock = studentMockPayments($studentId);
        $result['history'] = array_map(static function (array $row): array {
            return [
                'month' => (string) $row['month'],
                'raw_month' => date('Y-m', strtotime((string) $row['month'])) ?: '',
                'amount' => STUDENT_FIXED_PAYMENT_AMOUNT,
                'receipt_path' => '',
                'status' => normalizeStudentPaymentStatus($row['status'] ?? 'Unpaid'),
            ];
        }, $mock['history'] ?? []);
    }

    return $result;
}

try {
    $db = getDB();
    $inputStudentId = isset($_GET['student_id']) ? trim((string) $_GET['student_id']) : '';
    if ($inputStudentId === '' && isset($_SESSION['student_id'])) {
        $inputStudentId = trim((string) $_SESSION['student_id']);
    }
    if ($inputStudentId === '' && isset($_SESSION['user']['id'])) {
        $inputStudentId = trim((string) $_SESSION['user']['id']);
    }

    if ($inputStudentId === '' || !ctype_digit($inputStudentId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid student_id']);
        exit;
    }

    $userId = (int) $inputStudentId;
    $studentId = resolvePaymentsStudentEntityId($db, $userId);

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
        echo json_encode(buildStudentPaymentResponse($db, $studentId));
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $paymentsTable = null;
    if (studentPaymentsTableExists($db, 'payments')) {
        $paymentsTable = 'payments';
    } elseif (studentPaymentsTableExists($db, 'fee_payments')) {
        $paymentsTable = 'fee_payments';
    }

    if ($paymentsTable === null || !studentPaymentsColumnExists($db, $paymentsTable, 'student_id')) {
        http_response_code(400);
        echo json_encode(['error' => 'Payment records are not available yet.']);
        exit;
    }

    ensureStudentPaymentColumns($db, $paymentsTable);

    $monthValue = trim((string) ($_POST['month'] ?? ''));
    if ($monthValue === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Please select a payment month.']);
        exit;
    }

    if (!isset($_FILES['receipt']) || (int) ($_FILES['receipt']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Please upload the payment receipt.']);
        exit;
    }

    $file = $_FILES['receipt'];
    if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['error' => 'Receipt must be smaller than 5MB.']);
        exit;
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!in_array($extension, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Receipt must be PDF, JPG, JPEG, or PNG.']);
        exit;
    }

    $uploadDir = resolveStudentPaymentUploadPath();
    $filename = sprintf('student_%d_%s_%s.%s', $studentId, preg_replace('/[^0-9]/', '', $monthValue), uniqid(), $extension);
    $absolutePath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file((string) $file['tmp_name'], $absolutePath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save receipt file.']);
        exit;
    }

    $receiptPath = '/api/uploads/student-payment-receipts/' . $filename;
    $monthColumn = resolveStudentPaymentMonthExpr($db, $paymentsTable);
    $dateColumn = resolveStudentPaymentDateExpr($db, $paymentsTable);

    if ($monthColumn === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Payment month field is not configured.']);
        exit;
    }

    $selectStmt = $db->prepare("SELECT id FROM {$paymentsTable} WHERE student_id = ? AND {$monthColumn} = ? LIMIT 1");
    $selectStmt->execute([$studentId, $monthValue]);
    $existingId = $selectStmt->fetchColumn();

    if ($existingId !== false) {
        $updateSql = "UPDATE {$paymentsTable} SET amount = ?, status = 'Pending', receipt_path = ?";
        if ($dateColumn && $dateColumn !== 'created_at') {
            $updateSql .= ", {$dateColumn} = CURDATE()";
        }
        $updateSql .= " WHERE id = ?";
        $updateStmt = $db->prepare($updateSql);
        $updateStmt->execute([STUDENT_FIXED_PAYMENT_AMOUNT, $receiptPath, (int) $existingId]);
    } else {
        $columns = ['student_id', $monthColumn, 'amount', 'status', 'receipt_path'];
        $values = ['?', '?', '?', '?', '?'];
        $params = [$studentId, $monthValue, STUDENT_FIXED_PAYMENT_AMOUNT, 'Pending', $receiptPath];
        if ($dateColumn && $dateColumn !== 'created_at') {
            $columns[] = $dateColumn;
            $values[] = 'CURDATE()';
        }
        $insertSql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $paymentsTable, implode(', ', $columns), implode(', ', $values));
        $insertStmt = $db->prepare($insertSql);
        $insertStmt->execute($params);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Payment receipt uploaded successfully.',
        'history' => buildStudentPaymentResponse($db, $studentId)['history'],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

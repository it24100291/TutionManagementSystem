<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../src/config/env.php';
require_once __DIR__ . '/../../src/config/db.php';
require_once __DIR__ . '/student_mock_data.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

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

try {
    $db = getDB();
    $userId = (int) $inputStudentId;
    $studentId = resolvePaymentsStudentEntityId($db, $userId);

    $result = [
        'total_paid_months' => 0,
        'total_unpaid_months' => 0,
        'total_outstanding_amount' => 0,
        'history' => [],
    ];

    $paymentsTable = null;
    if (studentPaymentsTableExists($db, 'payments')) {
        $paymentsTable = 'payments';
    } elseif (studentPaymentsTableExists($db, 'fee_payments')) {
        $paymentsTable = 'fee_payments';
    }

    if ($paymentsTable !== null && studentPaymentsColumnExists($db, $paymentsTable, 'student_id')) {
        $statusColumnExists = studentPaymentsColumnExists($db, $paymentsTable, 'status');
        $amountColumnExists = studentPaymentsColumnExists($db, $paymentsTable, 'amount');
        $monthColumn = studentPaymentsColumnExists($db, $paymentsTable, 'month')
            ? 'month'
            : (studentPaymentsColumnExists($db, $paymentsTable, 'payment_month') ? 'payment_month' : null);
        $dateColumn = studentPaymentsColumnExists($db, $paymentsTable, 'payment_date')
            ? 'payment_date'
            : (studentPaymentsColumnExists($db, $paymentsTable, 'created_at') ? 'created_at' : null);

        if ($statusColumnExists) {
            $stmt = $db->prepare("
                SELECT
                    SUM(CASE WHEN LOWER(status) = 'paid' THEN 1 ELSE 0 END) AS paid_count,
                    SUM(CASE WHEN LOWER(status) IN ('unpaid', 'pending') THEN 1 ELSE 0 END) AS unpaid_count,
                    SUM(CASE WHEN LOWER(status) IN ('unpaid', 'pending') THEN COALESCE(amount, 0) ELSE 0 END) AS outstanding_amount
                FROM {$paymentsTable}
                WHERE student_id = ?
            ");
            $stmt->execute([$studentId]);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);
            $result['total_paid_months'] = (int) ($summary['paid_count'] ?? 0);
            $result['total_unpaid_months'] = (int) ($summary['unpaid_count'] ?? 0);
            $result['total_outstanding_amount'] = (int) round((float) ($summary['outstanding_amount'] ?? 0));
        }

        $monthExpr = $monthColumn
            ? "DATE_FORMAT({$monthColumn}, '%M %Y')"
            : "'Month'";
        $dateExpr = $dateColumn
            ? "DATE_FORMAT({$dateColumn}, '%Y-%m-%d')"
            : "''";
        $amountExpr = $amountColumnExists ? 'COALESCE(amount, 0)' : '0';
        $statusExpr = $statusColumnExists ? 'status' : "'unpaid'";
        $orderExpr = $dateColumn ? "{$dateColumn} DESC" : 'id DESC';

        $stmt = $db->prepare("
            SELECT
                {$monthExpr} AS month,
                {$amountExpr} AS amount,
                {$statusExpr} AS status,
                {$dateExpr} AS payment_date
            FROM {$paymentsTable}
            WHERE student_id = ?
            ORDER BY {$orderExpr}
        ");
        $stmt->execute([$studentId]);
        $result['history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $shouldUseMock =
        (int) ($result['total_paid_months'] ?? 0) === 0 &&
        (int) ($result['total_unpaid_months'] ?? 0) === 0 &&
        (int) ($result['total_outstanding_amount'] ?? 0) === 0 &&
        empty($result['history']);

    echo json_encode($shouldUseMock ? studentMockPayments($studentId) : $result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

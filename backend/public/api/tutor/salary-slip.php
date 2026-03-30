<?php
require_once __DIR__ . '/../../../src/config/env.php';
require_once __DIR__ . '/../../../src/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$inputTutorId = isset($_GET['tutor_id']) ? trim((string) $_GET['tutor_id']) : '';
if ($inputTutorId === '' && isset($_SESSION['tutor_id'])) {
    $inputTutorId = trim((string) $_SESSION['tutor_id']);
}
if ($inputTutorId === '' && isset($_SESSION['user']['id'])) {
    $inputTutorId = trim((string) $_SESSION['user']['id']);
}

$month = isset($_GET['month']) ? trim((string) $_GET['month']) : date('Y-m');

if ($inputTutorId === '' || !ctype_digit($inputTutorId)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid tutor_id']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid month']);
    exit;
}

function slipTableExists(PDO $db, string $tableName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?
    ");
    $stmt->execute([$tableName]);
    return (int) $stmt->fetchColumn() > 0;
}

function slipColumnExists(PDO $db, string $tableName, string $columnName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
    ");
    $stmt->execute([$tableName, $columnName]);
    return (int) $stmt->fetchColumn() > 0;
}

function pdfEscape(string $value): string {
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
}

function resolveSlipTutorRatePerHour(PDO $db, int $tutorId): int {
    if (!slipTableExists($db, 'tutors') || !slipColumnExists($db, 'tutors', 'subject')) {
        return 700;
    }

    $hasUserId = slipColumnExists($db, 'tutors', 'user_id');
    $whereClause = $hasUserId ? 'WHERE user_id = ? OR id = ?' : 'WHERE id = ?';
    $params = $hasUserId ? [$tutorId, $tutorId] : [$tutorId];

    $stmt = $db->prepare("
        SELECT subject
        FROM tutors
        {$whereClause}
        LIMIT 1
    ");
    $stmt->execute($params);
    $subject = strtolower(trim((string) ($stmt->fetchColumn() ?: '')));

    if (in_array($subject, ['science', 'mathematics', 'maths', 'ict'], true)) {
        return 800;
    }

    return 700;
}

function buildSimplePdf(array $lines): string {
    $content = "BT\n/F1 18 Tf\n50 790 Td\n";
    $first = true;
    foreach ($lines as $line) {
        if (!$first) {
            $content .= "0 -24 Td\n";
        }
        $content .= '(' . pdfEscape($line) . ") Tj\n";
        $first = false;
    }
    $content .= "ET";

    $objects = [];
    $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj";
    $objects[] = "2 0 obj\n<< /Type /Pages /Count 1 /Kids [3 0 R] >>\nendobj";
    $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj";
    $objects[] = "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream\nendobj";
    $objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj";

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object . "\n";
    }

    $xrefPosition = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefPosition . "\n%%EOF";

    return $pdf;
}

try {
    $db = getDB();
    $tutorId = (int) $inputTutorId;

    $monthDate = DateTime::createFromFormat('Y-m', $month);
    $monthStart = $monthDate ? $monthDate->format('Y-m-01') : date('Y-m-01');
    $monthLabel = $monthDate ? $monthDate->format('F Y') : date('F Y');

    $hoursThisMonth = 0;
    if (
        slipTableExists($db, 'timetable') &&
        slipColumnExists($db, 'timetable', 'tutor_id') &&
        slipColumnExists($db, 'timetable', 'duration_hours') &&
        slipColumnExists($db, 'timetable', 'status') &&
        slipColumnExists($db, 'timetable', 'class_date')
    ) {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(duration_hours), 0)
            FROM timetable
            WHERE tutor_id = ?
              AND status = 'active'
              AND MONTH(class_date) = MONTH(?)
              AND YEAR(class_date) = YEAR(?)
        ");
        $stmt->execute([$tutorId, $monthStart, $monthStart]);
        $hoursThisMonth = (int) round((float) $stmt->fetchColumn());
    }

    $ratePerHour = resolveSlipTutorRatePerHour($db, $tutorId);

    $status = 'pending';
    $amount = $hoursThisMonth * $ratePerHour;
    if (
        slipTableExists($db, 'salary_payments') &&
        slipColumnExists($db, 'salary_payments', 'tutor_id') &&
        slipColumnExists($db, 'salary_payments', 'payment_month') &&
        slipColumnExists($db, 'salary_payments', 'amount') &&
        slipColumnExists($db, 'salary_payments', 'status')
    ) {
        $stmt = $db->prepare("
            SELECT amount, status
            FROM salary_payments
            WHERE tutor_id = ?
              AND MONTH(payment_month) = MONTH(?)
              AND YEAR(payment_month) = YEAR(?)
            ORDER BY payment_month DESC
            LIMIT 1
        ");
        $stmt->execute([$tutorId, $monthStart, $monthStart]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $amount = (int) round((float) ($row['amount'] ?? $amount));
            $status = strtolower((string) ($row['status'] ?? 'pending')) === 'paid' ? 'paid' : 'pending';
        }
    }

    $pdf = buildSimplePdf([
        'Tutor Salary Slip',
        'Tutor ID: ' . $tutorId,
        'Month: ' . $monthLabel,
        'Hours Worked: ' . $hoursThisMonth,
        'Rate Per Hour: LKR ' . number_format($ratePerHour),
        'Base Salary: LKR ' . number_format($amount),
        'Payment Status: ' . ucfirst($status),
    ]);

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="salary-slip-' . $tutorId . '-' . $month . '.pdf"');
    echo $pdf;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}

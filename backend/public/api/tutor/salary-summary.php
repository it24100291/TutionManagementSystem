<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../src/config/env.php';
require_once __DIR__ . '/../../../src/config/db.php';
require_once __DIR__ . '/tutor_mock_data.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
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

if ($inputTutorId === '' || !ctype_digit($inputTutorId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid tutor_id']);
    exit;
}

function salaryTableExists(PDO $db, string $tableName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?
    ");
    $stmt->execute([$tableName]);
    return (int) $stmt->fetchColumn() > 0;
}

function salaryColumnExists(PDO $db, string $tableName, string $columnName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
    ");
    $stmt->execute([$tableName, $columnName]);
    return (int) $stmt->fetchColumn() > 0;
}

try {
    $db = getDB();
    $tutorId = (int) $inputTutorId;

    $hoursThisMonth = 0;
    if (
        salaryTableExists($db, 'timetable') &&
        salaryColumnExists($db, 'timetable', 'tutor_id') &&
        salaryColumnExists($db, 'timetable', 'duration_hours') &&
        salaryColumnExists($db, 'timetable', 'status') &&
        salaryColumnExists($db, 'timetable', 'class_date')
    ) {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(duration_hours), 0)
            FROM timetable
            WHERE tutor_id = ?
              AND status = 'active'
              AND MONTH(class_date) = MONTH(CURDATE())
              AND YEAR(class_date) = YEAR(CURDATE())
        ");
        $stmt->execute([$tutorId]);
        $hoursThisMonth = (int) round((float) $stmt->fetchColumn());
    }

    $ratePerHour = 0;
    if (
        salaryTableExists($db, 'tutors') &&
        salaryColumnExists($db, 'tutors', 'id') &&
        salaryColumnExists($db, 'tutors', 'rate_per_hour')
    ) {
        $stmt = $db->prepare("
            SELECT COALESCE(rate_per_hour, 0)
            FROM tutors
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$tutorId]);
        $rate = $stmt->fetchColumn();
        if ($rate !== false) {
            $ratePerHour = (int) round((float) $rate);
        }
    }

    $history = [];
    $currentStatus = 'pending';
    if (
        salaryTableExists($db, 'salary_payments') &&
        salaryColumnExists($db, 'salary_payments', 'tutor_id') &&
        salaryColumnExists($db, 'salary_payments', 'payment_month') &&
        salaryColumnExists($db, 'salary_payments', 'amount') &&
        salaryColumnExists($db, 'salary_payments', 'status')
    ) {
        $stmt = $db->prepare("
            SELECT
                DATE_FORMAT(payment_month, '%M %Y') AS month,
                amount,
                status,
                payment_month
            FROM salary_payments
            WHERE tutor_id = ?
            ORDER BY payment_month DESC
            LIMIT 3
        ");
        $stmt->execute([$tutorId]);
        $historyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($historyRows as $index => $row) {
            $normalizedStatus = strtolower((string) ($row['status'] ?? 'pending')) === 'paid' ? 'paid' : 'pending';
            if ($index === 0) {
                $currentStatus = $normalizedStatus;
            }
            $history[] = [
                'month' => (string) ($row['month'] ?? ''),
                'amount' => (int) round((float) ($row['amount'] ?? 0)),
                'status' => $normalizedStatus,
            ];
        }
    }

    $baseSalary = $hoursThisMonth * $ratePerHour;

    $result = [
        'hours_this_month' => $hoursThisMonth,
        'rate_per_hour' => $ratePerHour,
        'base_salary' => $baseSalary,
        'current_status' => $currentStatus,
        'history' => $history,
    ];

    $shouldUseMock = $hoursThisMonth === 0 && $ratePerHour === 0 && empty($history);
    echo json_encode($shouldUseMock ? tutorMockSalarySummary() : $result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

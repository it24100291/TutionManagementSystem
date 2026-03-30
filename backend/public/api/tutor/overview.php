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

function tutorTableExists(PDO $db, string $tableName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?
    ");
    $stmt->execute([$tableName]);
    return (int) $stmt->fetchColumn() > 0;
}

function tutorColumnExists(PDO $db, string $tableName, string $columnName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
    ");
    $stmt->execute([$tableName, $columnName]);
    return (int) $stmt->fetchColumn() > 0;
}

function resolveOverviewTutorProfileId(PDO $db, int $tutorId): int {
    if (
        tutorTableExists($db, 'tutors') &&
        tutorColumnExists($db, 'tutors', 'id') &&
        tutorColumnExists($db, 'tutors', 'user_id')
    ) {
        $stmt = $db->prepare("SELECT id FROM tutors WHERE user_id = ? LIMIT 1");
        $stmt->execute([$tutorId]);
        $resolvedId = $stmt->fetchColumn();
        if ($resolvedId !== false) {
            return (int) $resolvedId;
        }
    }

    return $tutorId;
}

try {
    $db = getDB();
    $tutorId = (int) $inputTutorId;
    $tutorProfileId = resolveOverviewTutorProfileId($db, $tutorId);
    $targetHours = 60;

    if (!tutorTableExists($db, 'timetable')) {
        echo json_encode(tutorMockOverview($tutorId));
        exit;
    }

    $classesToday = 0;
    if (
        tutorColumnExists($db, 'timetable', 'tutor_id') &&
        tutorColumnExists($db, 'timetable', 'class_date') &&
        tutorColumnExists($db, 'timetable', 'status')
    ) {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM timetable
            WHERE tutor_id = ? AND class_date = CURDATE() AND status = 'active'
        ");
        $stmt->execute([$tutorId]);
        $classesToday = (int) $stmt->fetchColumn();
    }

    $totalStudents = 0;
    if (
        tutorTableExists($db, 'students') &&
        tutorColumnExists($db, 'students', 'class_id') &&
        tutorColumnExists($db, 'timetable', 'class_id') &&
        tutorColumnExists($db, 'timetable', 'tutor_id')
    ) {
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT s.id)
            FROM students s
            JOIN timetable t ON s.class_id = t.class_id
            WHERE t.tutor_id = ?
        ");
        $stmt->execute([$tutorId]);
        $totalStudents = (int) $stmt->fetchColumn();
    }

    $hoursThisMonth = 0;
    if (
        tutorColumnExists($db, 'timetable', 'tutor_id') &&
        tutorColumnExists($db, 'timetable', 'duration_hours') &&
        tutorColumnExists($db, 'timetable', 'class_date') &&
        tutorColumnExists($db, 'timetable', 'status')
    ) {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(duration_hours), 0)
            FROM timetable
            WHERE tutor_id = ?
              AND MONTH(class_date) = MONTH(CURDATE())
              AND YEAR(class_date) = YEAR(CURDATE())
              AND status = 'active'
        ");
        $stmt->execute([$tutorId]);
        $hoursThisMonth = (int) round((float) $stmt->fetchColumn());
    }

    $salaryStatus = 'pending';
    if (
        tutorTableExists($db, 'salary_payments') &&
        tutorColumnExists($db, 'salary_payments', 'tutor_id') &&
        tutorColumnExists($db, 'salary_payments', 'payment_month') &&
        tutorColumnExists($db, 'salary_payments', 'status')
    ) {
        $stmt = $db->prepare("
            SELECT status
            FROM salary_payments
            WHERE tutor_id = ?
              AND MONTH(payment_month) = MONTH(CURDATE())
              AND YEAR(payment_month) = YEAR(CURDATE())
            LIMIT 1
        ");
        $stmt->execute([$tutorProfileId]);
        $status = $stmt->fetchColumn();
        if ($status !== false) {
            $salaryStatus = strtolower((string) $status) === 'paid' ? 'paid' : 'pending';
        }
    }

    $result = [
        'classes_today' => $classesToday,
        'total_students' => $totalStudents,
        'hours_this_month' => $hoursThisMonth,
        'target_hours' => $targetHours,
        'salary_status' => $salaryStatus,
    ];

    $shouldUseMock =
        $classesToday === 0 &&
        $totalStudents === 0 &&
        $hoursThisMonth === 0;

    echo json_encode($shouldUseMock ? tutorMockOverview($tutorId) : $result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

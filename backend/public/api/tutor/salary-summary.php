<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../src/config/env.php';
require_once __DIR__ . '/../../../src/config/db.php';

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

function parseTimetableDurationHours(string $timeSlot): float {
    $parts = array_map('trim', explode('-', $timeSlot));
    if (count($parts) !== 2) {
        return 0.0;
    }

    $start = DateTime::createFromFormat('H:i', $parts[0]);
    $end = DateTime::createFromFormat('H:i', $parts[1]);

    if (!$start || !$end) {
        return 0.0;
    }

    $startMinutes = ((int) $start->format('H') * 60) + (int) $start->format('i');
    $endMinutes = ((int) $end->format('H') * 60) + (int) $end->format('i');
    $durationMinutes = $endMinutes - $startMinutes;

    return $durationMinutes > 0 ? $durationMinutes / 60 : 0.0;
}

function resolveTutorProfileId(PDO $db, int $tutorId): int {
    if (
        salaryTableExists($db, 'tutors') &&
        salaryColumnExists($db, 'tutors', 'id') &&
        salaryColumnExists($db, 'tutors', 'user_id')
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

function resolveTutorRatePerHour(PDO $db, int $tutorId): int {
    if (!salaryTableExists($db, 'tutors') || !salaryColumnExists($db, 'tutors', 'subject')) {
        return 0;
    }

    $hasUserId = salaryColumnExists($db, 'tutors', 'user_id');
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

try {
    $db = getDB();
    $tutorId = (int) $inputTutorId;
    $tutorProfileId = resolveTutorProfileId($db, $tutorId);

    $hoursThisMonth = 0;
    if (
        salaryTableExists($db, 'timetable') &&
        salaryColumnExists($db, 'timetable', 'tutor_id') &&
        salaryColumnExists($db, 'timetable', 'time_slot')
    ) {
        $tutorIds = array_values(array_unique([$tutorId, $tutorProfileId]));

        $placeholders = implode(',', array_fill(0, count($tutorIds), '?'));
        $stmt = $db->prepare("
            SELECT DISTINCT day, time_slot
            FROM timetable
            WHERE tutor_id IN ({$placeholders})
        ");
        $stmt->execute($tutorIds);
        $slots = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $weeklyHours = 0.0;
        foreach ($slots as $slot) {
            $weeklyHours += parseTimetableDurationHours((string) ($slot['time_slot'] ?? ''));
        }

        $hoursThisMonth = (int) round($weeklyHours * 4);
    }

    $ratePerHour = resolveTutorRatePerHour($db, $tutorId);

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
        $stmt->execute([$tutorProfileId]);
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
    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

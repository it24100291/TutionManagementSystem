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

function resolveOverviewTutorIds(PDO $db, int $tutorId): array {
    $ids = [$tutorId];

    if (
        tutorTableExists($db, 'tutors') &&
        tutorColumnExists($db, 'tutors', 'id') &&
        tutorColumnExists($db, 'tutors', 'user_id')
    ) {
        $stmt = $db->prepare("SELECT id, user_id FROM tutors WHERE id = ? OR user_id = ? LIMIT 1");
        $stmt->execute([$tutorId, $tutorId]);
        $resolved = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($resolved) {
            $ids[] = (int) ($resolved['id'] ?? 0);
            $ids[] = (int) ($resolved['user_id'] ?? 0);
        }
    }

    return array_values(array_unique(array_filter($ids)));
}

function resolveOverviewTutorProfileId(PDO $db, int $tutorId): int {
    if (
        tutorTableExists($db, 'tutors') &&
        tutorColumnExists($db, 'tutors', 'id') &&
        tutorColumnExists($db, 'tutors', 'user_id')
    ) {
        $stmt = $db->prepare("SELECT id FROM tutors WHERE id = ? OR user_id = ? LIMIT 1");
        $stmt->execute([$tutorId, $tutorId]);
        $resolvedId = $stmt->fetchColumn();
        if ($resolvedId !== false) {
            return (int) $resolvedId;
        }
    }

    return $tutorId;
}

function parseOverviewTimeSlotHours(string $timeSlot): float {
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

function buildOverviewGradeVariants(array $grades): array {
    $variants = [];

    foreach ($grades as $grade) {
        $value = trim((string) $grade);
        if ($value === '') {
            continue;
        }

        $variants[] = $value;
        if (preg_match('/^Grade\s*(\d+)$/i', $value, $matches)) {
            $variants[] = $matches[1];
            $variants[] = 'Grade ' . $matches[1];
        } elseif (ctype_digit($value)) {
            $variants[] = 'Grade ' . $value;
        }
    }

    return array_values(array_unique(array_filter($variants)));
}

try {
    $db = getDB();
    $tutorId = (int) $inputTutorId;
    $tutorIds = resolveOverviewTutorIds($db, $tutorId);
    $tutorProfileId = resolveOverviewTutorProfileId($db, $tutorId);
    $targetHours = 60;
    $result = [
        'classes_today' => 0,
        'total_students' => 0,
        'hours_this_month' => 0,
        'target_hours' => $targetHours,
        'salary_status' => 'pending',
    ];

    if (!tutorTableExists($db, 'timetable')) {
        echo json_encode($result);
        exit;
    }

    $classesToday = 0;
    $assignedGrades = [];
    if (
        tutorColumnExists($db, 'timetable', 'tutor_id') &&
        tutorColumnExists($db, 'timetable', 'day') &&
        tutorColumnExists($db, 'timetable', 'grade') &&
        !empty($tutorIds)
    ) {
        $placeholders = implode(',', array_fill(0, count($tutorIds), '?'));
        $todayParams = $tutorIds;
        $todayParams[] = date('l');

        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM timetable
            WHERE tutor_id IN ({$placeholders}) AND day = ?
        ");
        $stmt->execute($todayParams);
        $classesToday = (int) $stmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT DISTINCT grade
            FROM timetable
            WHERE tutor_id IN ({$placeholders})
        ");
        $stmt->execute($tutorIds);
        $assignedGrades = array_map(static fn ($row) => (string) ($row['grade'] ?? ''), $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    $totalStudents = 0;
    if (
        tutorTableExists($db, 'students') &&
        tutorColumnExists($db, 'students', 'grade') &&
        !empty($assignedGrades)
    ) {
        $gradeVariants = buildOverviewGradeVariants($assignedGrades);
        if (empty($gradeVariants)) {
            $gradeVariants = ['__no_matching_grade__'];
        }
        $gradePlaceholders = implode(',', array_fill(0, count($gradeVariants), '?'));
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT s.id)
            FROM students s
            WHERE s.grade IN ({$gradePlaceholders})
        ");
        $stmt->execute($gradeVariants);
        $totalStudents = (int) $stmt->fetchColumn();
    }

    $hoursThisMonth = 0;
    if (
        tutorColumnExists($db, 'timetable', 'tutor_id') &&
        tutorColumnExists($db, 'timetable', 'day') &&
        tutorColumnExists($db, 'timetable', 'time_slot') &&
        !empty($tutorIds)
    ) {
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
            $weeklyHours += parseOverviewTimeSlotHours((string) ($slot['time_slot'] ?? ''));
        }

        $hoursThisMonth = (int) round($weeklyHours * 4);
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
    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

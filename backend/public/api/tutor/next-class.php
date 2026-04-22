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

function nextClassTableExists(PDO $db, string $tableName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?
    ");
    $stmt->execute([$tableName]);
    return (int) $stmt->fetchColumn() > 0;
}

function nextClassColumnExists(PDO $db, string $tableName, string $columnName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
    ");
    $stmt->execute([$tableName, $columnName]);
    return (int) $stmt->fetchColumn() > 0;
}

function resolveNextClassTutorIds(PDO $db, int $rawTutorId): array {
    $resolvedIds = [$rawTutorId];

    if (
        nextClassTableExists($db, 'tutors') &&
        nextClassColumnExists($db, 'tutors', 'id') &&
        nextClassColumnExists($db, 'tutors', 'user_id')
    ) {
        $resolveStmt = $db->prepare("SELECT id, user_id FROM tutors WHERE id = ? OR user_id = ?");
        $resolveStmt->execute([$rawTutorId, $rawTutorId]);

        foreach ($resolveStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $resolvedIds[] = (int) ($row['id'] ?? 0);
            $resolvedIds[] = (int) ($row['user_id'] ?? 0);
        }
    }

    return array_values(array_unique(array_filter($resolvedIds)));
}

function nextClassBuildGradeVariants(string $grade): array {
    $value = trim($grade);
    if ($value === '') {
        return [];
    }

    $variants = [$value];
    if (preg_match('/^Grade\s+(\d+)$/i', $value, $matches)) {
        $variants[] = $matches[1];
    } elseif (preg_match('/^\d+$/', $value)) {
        $variants[] = 'Grade ' . $value;
    }

    return array_values(array_unique(array_filter(array_map('trim', $variants))));
}

try {
    $db = getDB();
    $tutorIds = resolveNextClassTutorIds($db, (int) $inputTutorId);

    if (!nextClassTableExists($db, 'timetable')) {
        echo json_encode(null);
        exit;
    }

    $attendanceExists = nextClassTableExists($db, 'attendance')
        && nextClassColumnExists($db, 'attendance', 'timetable_id')
        && nextClassColumnExists($db, 'attendance', 'marked_at');
    $placeholders = implode(',', array_fill(0, count($tutorIds), '?'));

    $hasRecurringTimetableColumns =
        nextClassColumnExists($db, 'timetable', 'id') &&
        nextClassColumnExists($db, 'timetable', 'tutor_id') &&
        nextClassColumnExists($db, 'timetable', 'grade') &&
        nextClassColumnExists($db, 'timetable', 'day') &&
        nextClassColumnExists($db, 'timetable', 'time_slot') &&
        nextClassColumnExists($db, 'timetable', 'subject');

    $hasDatedClassColumns =
        nextClassColumnExists($db, 'timetable', 'class_id') &&
        nextClassColumnExists($db, 'timetable', 'class_date') &&
        nextClassColumnExists($db, 'timetable', 'start_time') &&
        nextClassTableExists($db, 'classes') &&
        nextClassColumnExists($db, 'classes', 'id') &&
        nextClassColumnExists($db, 'classes', 'name') &&
        nextClassColumnExists($db, 'classes', 'grade');

    if ($hasRecurringTimetableColumns) {
        $today = date('l');
        $attendanceSelect = $attendanceExists
            ? "EXISTS(
                    SELECT 1
                    FROM attendance a
                    WHERE a.timetable_id = t.id
                      AND DATE(a.marked_at) = CURDATE()
                )"
            : '0';

        $stmt = $db->prepare("
            SELECT
                t.id,
                NULL AS class_id,
                t.subject,
                t.subject AS name,
                t.grade,
                TRIM(SUBSTRING_INDEX(t.time_slot, '-', 1)) AS start_time,
                {$attendanceSelect} AS attendance_submitted
            FROM timetable t
            WHERE t.tutor_id IN ({$placeholders})
              AND t.day = ?
            ORDER BY STR_TO_DATE(TRIM(SUBSTRING_INDEX(t.time_slot, '-', 1)), '%H:%i') ASC, t.id ASC
            LIMIT 1
        ");
        $stmt->execute(array_merge($tutorIds, [$today]));
        $nextClass = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($nextClass) {
            echo json_encode($nextClass);
            exit;
        }
    }

    if (!$hasDatedClassColumns) {
        echo json_encode(null);
        exit;
    }

    if ($attendanceExists) {
        $stmt = $db->prepare("
            SELECT
                t.id,
                t.class_id,
                t.subject,
                c.name,
                c.grade,
                DATE_FORMAT(t.start_time, '%H:%i') AS start_time,
                EXISTS(
                    SELECT 1
                    FROM attendance a
                    WHERE a.timetable_id = t.id
                      AND DATE(a.marked_at) = CURDATE()
                ) AS attendance_submitted
            FROM timetable t
            JOIN classes c ON t.class_id = c.id
            WHERE t.tutor_id IN ({$placeholders}) AND t.class_date = CURDATE()
            ORDER BY t.start_time ASC
            LIMIT 1
        ");
        $stmt->execute($tutorIds);
        $nextClass = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($nextClass) {
            echo json_encode($nextClass);
            exit;
        }
    }

    $stmt = $db->prepare("
        SELECT t.id, t.class_id, t.subject, c.name, c.grade, DATE_FORMAT(t.start_time, '%H:%i') AS start_time, 0 AS attendance_submitted
        FROM timetable t
        JOIN classes c ON t.class_id = c.id
        WHERE t.tutor_id IN ({$placeholders}) AND t.class_date = CURDATE()
        ORDER BY t.start_time ASC
        LIMIT 1
    ");
    $stmt->execute($tutorIds);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: null);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

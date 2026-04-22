<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../src/config/env.php';
require_once __DIR__ . '/../../../src/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'POST') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$inputTutorId = isset($data['tutor_id']) ? trim((string) $data['tutor_id']) : '';
$inputTimetableId = isset($data['timetable_id']) ? trim((string) $data['timetable_id']) : '';
$records = $data['records'] ?? null;

if ($inputTutorId === '' && isset($_SESSION['tutor_id'])) {
    $inputTutorId = trim((string) $_SESSION['tutor_id']);
}
if ($inputTutorId === '' && isset($_SESSION['user']['id'])) {
    $inputTutorId = trim((string) $_SESSION['user']['id']);
}

if (
    $inputTutorId === '' || !ctype_digit($inputTutorId) ||
    $inputTimetableId === '' || !ctype_digit($inputTimetableId) ||
    !is_array($records) || count($records) === 0
) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid tutor_id, timetable_id, or records']);
    exit;
}

function attendanceTableExists(PDO $db, string $tableName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?
    ");
    $stmt->execute([$tableName]);
    return (int) $stmt->fetchColumn() > 0;
}

function attendanceColumnExists(PDO $db, string $tableName, string $columnName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
    ");
    $stmt->execute([$tableName, $columnName]);
    return (int) $stmt->fetchColumn() > 0;
}

function resolveAttendanceTutorIds(PDO $db, int $rawTutorId): array {
    $resolvedIds = [$rawTutorId];

    if (
        attendanceTableExists($db, 'tutors') &&
        attendanceColumnExists($db, 'tutors', 'id') &&
        attendanceColumnExists($db, 'tutors', 'user_id')
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

function attendanceTimetableBelongsToFirstClass(PDO $db, array $tutorIds, int $timetableId): bool {
    if (!attendanceTableExists($db, 'timetable') || !attendanceColumnExists($db, 'timetable', 'id') || !attendanceColumnExists($db, 'timetable', 'tutor_id')) {
        return false;
    }

    $placeholders = implode(',', array_fill(0, count($tutorIds), '?'));

    if (attendanceColumnExists($db, 'timetable', 'class_date') && attendanceColumnExists($db, 'timetable', 'start_time')) {
        $stmt = $db->prepare("
            SELECT t.id
            FROM timetable t
            WHERE t.id = ?
              AND t.class_date = CURDATE()
              AND t.tutor_id IN ({$placeholders})
              AND t.id = (
                  SELECT t2.id
                  FROM timetable t2
                  WHERE t2.class_date = CURDATE()
                    AND t2.tutor_id IN ({$placeholders})
                  ORDER BY t2.start_time ASC, t2.id ASC
                  LIMIT 1
              )
            LIMIT 1
        ");
        $stmt->execute(array_merge([$timetableId], $tutorIds, $tutorIds));

        return (bool) $stmt->fetchColumn();
    }

    if (attendanceColumnExists($db, 'timetable', 'day') && attendanceColumnExists($db, 'timetable', 'time_slot')) {
        $today = date('l');
        $stmt = $db->prepare("
            SELECT t.id
            FROM timetable t
            WHERE t.id = ?
              AND t.day = ?
              AND t.tutor_id IN ({$placeholders})
              AND t.id = (
                  SELECT t2.id
                  FROM timetable t2
                  WHERE t2.day = ?
                    AND t2.tutor_id IN ({$placeholders})
                  ORDER BY STR_TO_DATE(TRIM(SUBSTRING_INDEX(t2.time_slot, '-', 1)), '%H:%i') ASC, t2.id ASC
                  LIMIT 1
              )
            LIMIT 1
        ");
        $stmt->execute(array_merge([$timetableId, $today], $tutorIds, [$today], $tutorIds));

        return (bool) $stmt->fetchColumn();
    }

    return false;
}

function attendanceStudentBelongsToTimetableClass(PDO $db, int $studentId, int $timetableId): bool {
    if (
        !attendanceTableExists($db, 'students') ||
        !attendanceTableExists($db, 'timetable') ||
        !attendanceColumnExists($db, 'students', 'id') ||
        !attendanceColumnExists($db, 'timetable', 'id')
    ) {
        return false;
    }

    if (
        attendanceColumnExists($db, 'students', 'class_id') &&
        attendanceColumnExists($db, 'timetable', 'class_id')
    ) {
        $stmt = $db->prepare("
            SELECT 1
            FROM students s
            JOIN timetable t ON t.class_id = s.class_id
            WHERE s.id = ? AND t.id = ?
            LIMIT 1
        ");
        $stmt->execute([$studentId, $timetableId]);

        return (bool) $stmt->fetchColumn();
    }

    if (
        attendanceColumnExists($db, 'students', 'grade') &&
        attendanceColumnExists($db, 'timetable', 'grade')
    ) {
        $stmt = $db->prepare("
            SELECT 1
            FROM students s
            JOIN timetable t
              ON (
                s.grade = t.grade
                OR CONCAT('Grade ', s.grade) = t.grade
                OR s.grade = REPLACE(t.grade, 'Grade ', '')
              )
            WHERE s.id = ? AND t.id = ?
            LIMIT 1
        ");
        $stmt->execute([$studentId, $timetableId]);

        return (bool) $stmt->fetchColumn();
    }

    return false;
}

try {
    $db = getDB();
    $tutorId = (int) $inputTutorId;
    $timetableId = (int) $inputTimetableId;
    $tutorIds = resolveAttendanceTutorIds($db, $tutorId);

    $db->exec("
        CREATE TABLE IF NOT EXISTS attendance (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            student_id INT UNSIGNED NOT NULL,
            timetable_id INT UNSIGNED NOT NULL,
            tutor_id INT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL,
            marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    if (!attendanceTimetableBelongsToFirstClass($db, $tutorIds, $timetableId)) {
        http_response_code(403);
        echo json_encode(['error' => 'You can only submit attendance for your first class today.']);
        exit;
    }

    $db->beginTransaction();

    $existingStmt = $db->prepare("
        SELECT COUNT(*) FROM attendance
        WHERE timetable_id = ? AND tutor_id = ? AND DATE(marked_at) = CURDATE()
    ");
    $existingStmt->execute([$timetableId, $tutorId]);
    if ((int) $existingStmt->fetchColumn() > 0) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        http_response_code(400);
        echo json_encode(['error' => 'Attendance already submitted for this class today']);
        exit;
    }

    $insertStmt = $db->prepare("
        INSERT INTO attendance (student_id, timetable_id, tutor_id, status, marked_at)
        VALUES (?, ?, ?, ?, NOW())
    ");

    $count = 0;
    foreach ($records as $record) {
        $studentId = isset($record['student_id']) ? trim((string) $record['student_id']) : '';
        $status = isset($record['status']) ? trim((string) $record['status']) : '';

        if ($studentId === '' || !ctype_digit($studentId) || !in_array($status, ['present', 'absent'], true)) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            http_response_code(400);
            echo json_encode(['error' => 'Invalid attendance record payload']);
            exit;
        }

        if (!attendanceStudentBelongsToTimetableClass($db, (int) $studentId, $timetableId)) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            http_response_code(400);
            echo json_encode(['error' => 'Student does not belong to this class']);
            exit;
        }

        $insertStmt->execute([(int) $studentId, $timetableId, $tutorId, $status]);
        $count++;
    }

    $db->commit();
    echo json_encode(['success' => true, 'count' => $count, 'message' => "Attendance submitted for {$count} students."]);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

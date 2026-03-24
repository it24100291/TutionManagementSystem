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

try {
    $db = getDB();
    $tutorId = (int) $inputTutorId;

    $requiredTables = ['timetable', 'classes'];
    foreach ($requiredTables as $tableName) {
        if (!nextClassTableExists($db, $tableName)) {
            echo json_encode(tutorMockNextClass());
            exit;
        }
    }

    $requiredColumns = [
        ['timetable', 'id'],
        ['timetable', 'tutor_id'],
        ['timetable', 'class_id'],
        ['timetable', 'class_date'],
        ['timetable', 'start_time'],
        ['classes', 'id'],
        ['classes', 'name'],
        ['classes', 'grade'],
    ];

    foreach ($requiredColumns as [$tableName, $columnName]) {
        if (!nextClassColumnExists($db, $tableName, $columnName)) {
            echo json_encode(tutorMockNextClass());
            exit;
        }
    }

    $attendanceExists = nextClassTableExists($db, 'attendance')
        && nextClassColumnExists($db, 'attendance', 'timetable_id')
        && nextClassColumnExists($db, 'attendance', 'marked_at');

    if ($attendanceExists) {
        $stmt = $db->prepare("
            SELECT t.id, t.class_id, c.name, c.grade, DATE_FORMAT(t.start_time, '%H:%i') AS start_time, 0 AS attendance_submitted
            FROM timetable t
            JOIN classes c ON t.class_id = c.id
            WHERE t.tutor_id = ? AND t.class_date = CURDATE()
              AND t.id NOT IN (
                SELECT DISTINCT timetable_id FROM attendance
                WHERE DATE(marked_at) = CURDATE()
              )
            ORDER BY t.start_time ASC
            LIMIT 1
        ");
        $stmt->execute([$tutorId]);
        $nextClass = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($nextClass) {
            echo json_encode($nextClass);
            exit;
        }

        $submittedStmt = $db->prepare("
            SELECT t.id, t.class_id, c.name, c.grade, DATE_FORMAT(t.start_time, '%H:%i') AS start_time, 1 AS attendance_submitted
            FROM timetable t
            JOIN classes c ON t.class_id = c.id
            WHERE t.tutor_id = ? AND t.class_date = CURDATE()
              AND t.id IN (
                SELECT DISTINCT timetable_id FROM attendance
                WHERE DATE(marked_at) = CURDATE()
              )
            ORDER BY t.start_time ASC
            LIMIT 1
        ");
        $submittedStmt->execute([$tutorId]);
        $submittedClass = $submittedStmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode($submittedClass ?: tutorMockNextClass());
        exit;
    }

    $stmt = $db->prepare("
        SELECT t.id, t.class_id, c.name, c.grade, DATE_FORMAT(t.start_time, '%H:%i') AS start_time, 0 AS attendance_submitted
        FROM timetable t
        JOIN classes c ON t.class_id = c.id
        WHERE t.tutor_id = ? AND t.class_date = CURDATE()
        ORDER BY t.start_time ASC
        LIMIT 1
    ");
    $stmt->execute([$tutorId]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: tutorMockNextClass());
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

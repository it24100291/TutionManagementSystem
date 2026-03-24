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

function scheduleTableExists(PDO $db, string $tableName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?
    ");
    $stmt->execute([$tableName]);
    return (int) $stmt->fetchColumn() > 0;
}

function scheduleColumnExists(PDO $db, string $tableName, string $columnName): bool {
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
        if (!scheduleTableExists($db, $tableName)) {
            echo json_encode(tutorMockTodaySchedule());
            exit;
        }
    }

    $requiredColumns = [
        ['timetable', 'id'],
        ['timetable', 'tutor_id'],
        ['timetable', 'class_id'],
        ['timetable', 'class_date'],
        ['timetable', 'start_time'],
        ['timetable', 'duration_hours'],
        ['timetable', 'room'],
        ['timetable', 'status'],
        ['classes', 'id'],
        ['classes', 'name'],
        ['classes', 'grade'],
    ];

    foreach ($requiredColumns as [$tableName, $columnName]) {
        if (!scheduleColumnExists($db, $tableName, $columnName)) {
            echo json_encode(tutorMockTodaySchedule());
            exit;
        }
    }

    $hasStudentsTable = scheduleTableExists($db, 'students');
    $hasStudentClassColumn = $hasStudentsTable && scheduleColumnExists($db, 'students', 'class_id');
    $studentCountSql = ($hasStudentsTable && $hasStudentClassColumn) ? 'COUNT(s.id)' : '0';
    $studentJoinSql = ($hasStudentsTable && $hasStudentClassColumn) ? 'LEFT JOIN students s ON s.class_id = c.id' : '';

    $stmt = $db->prepare("
        SELECT
            t.id,
            c.name AS class_name,
            c.grade,
            DATE_FORMAT(t.start_time, '%H:%i') AS start_time,
            t.duration_hours,
            t.room,
            {$studentCountSql} AS student_count,
            t.status
        FROM timetable t
        JOIN classes c ON t.class_id = c.id
        {$studentJoinSql}
        WHERE t.tutor_id = ? AND t.class_date = CURDATE()
        GROUP BY t.id, c.name, c.grade, t.start_time, t.duration_hours, t.room, t.status
        ORDER BY t.start_time ASC
    ");
    $stmt->execute([$tutorId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(empty($rows) ? tutorMockTodaySchedule() : $rows);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

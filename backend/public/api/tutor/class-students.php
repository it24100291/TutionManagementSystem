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

$classId = isset($_GET['class_id']) ? trim((string) $_GET['class_id']) : '';
$timetableId = isset($_GET['timetable_id']) ? trim((string) $_GET['timetable_id']) : '';

if ($classId === '' || !ctype_digit($classId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid class_id']);
    exit;
}

function classStudentsTableExists(PDO $db, string $tableName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?
    ");
    $stmt->execute([$tableName]);
    return (int) $stmt->fetchColumn() > 0;
}

function classStudentsColumnExists(PDO $db, string $tableName, string $columnName): bool {
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
    $classIdInt = (int) $classId;

    if (!classStudentsTableExists($db, 'students') || !classStudentsColumnExists($db, 'students', 'id') || !classStudentsColumnExists($db, 'students', 'class_id')) {
        echo json_encode(tutorMockClassStudents());
        exit;
    }

    $nameExpression = classStudentsColumnExists($db, 'students', 'first_name') && classStudentsColumnExists($db, 'students', 'last_name')
        ? "CONCAT(first_name, ' ', last_name)"
        : (classStudentsColumnExists($db, 'users', 'full_name') && classStudentsColumnExists($db, 'students', 'user_id')
            ? 'u.full_name'
            : "'Student'");

    $joinUsersSql = ($nameExpression === 'u.full_name') ? 'LEFT JOIN users u ON u.id = s.user_id' : '';
    $statusFilterSql = classStudentsColumnExists($db, 'students', 'status') ? "AND s.status = 'active'" : '';

    $attendanceJoinSql = '';
    if (
        $timetableId !== '' &&
        ctype_digit($timetableId) &&
        classStudentsTableExists($db, 'attendance') &&
        classStudentsColumnExists($db, 'attendance', 'student_id') &&
        classStudentsColumnExists($db, 'attendance', 'timetable_id') &&
        classStudentsColumnExists($db, 'attendance', 'status') &&
        classStudentsColumnExists($db, 'attendance', 'marked_at')
    ) {
        $attendanceJoinSql = 'LEFT JOIN attendance a ON a.student_id = s.id AND a.timetable_id = :timetable_id AND DATE(a.marked_at) = CURDATE()';
    }

    $sql = "
        SELECT
            s.id,
            {$nameExpression} AS name" .
            ($attendanceJoinSql !== '' ? ",
            a.status AS attendance_status" : ",
            NULL AS attendance_status") . "
        FROM students s
        {$joinUsersSql}
        {$attendanceJoinSql}
        WHERE s.class_id = :class_id
        {$statusFilterSql}
        ORDER BY name ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':class_id', $classIdInt, PDO::PARAM_INT);
    if ($attendanceJoinSql !== '') {
        $stmt->bindValue(':timetable_id', (int) $timetableId, PDO::PARAM_INT);
    }
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(empty($rows) ? tutorMockClassStudents() : $rows);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

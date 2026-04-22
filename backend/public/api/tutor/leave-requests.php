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

function leaveTableExists(PDO $db, string $tableName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?
    ");
    $stmt->execute([$tableName]);
    return (int) $stmt->fetchColumn() > 0;
}

function leaveColumnExists(PDO $db, string $tableName, string $columnName): bool {
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

    $requiredTables = ['leave_requests', 'students', 'classes', 'timetable'];
    foreach ($requiredTables as $tableName) {
        if (!leaveTableExists($db, $tableName)) {
            echo json_encode([]);
            exit;
        }
    }

    $studentNameExpression = null;
    $studentUserJoin = '';
    if (
        leaveColumnExists($db, 'students', 'first_name') &&
        leaveColumnExists($db, 'students', 'last_name')
    ) {
        $studentNameExpression = "TRIM(CONCAT(COALESCE(NULLIF(s.first_name, ''), ''), ' ', COALESCE(NULLIF(s.last_name, ''), '')))";
    } elseif (
        leaveColumnExists($db, 'students', 'user_id') &&
        leaveTableExists($db, 'users') &&
        leaveColumnExists($db, 'users', 'id') &&
        leaveColumnExists($db, 'users', 'full_name')
    ) {
        $studentUserJoin = ' LEFT JOIN users u ON u.id = s.user_id ';
        $studentNameExpression = 'u.full_name';
    } elseif (leaveColumnExists($db, 'students', 'full_name')) {
        $studentNameExpression = 's.full_name';
    } else {
        echo json_encode([]);
        exit;
    }

    $requiredColumns = [
        ['leave_requests', 'id'],
        ['leave_requests', 'student_id'],
        ['leave_requests', 'absence_date'],
        ['leave_requests', 'reason'],
        ['leave_requests', 'status'],
        ['leave_requests', 'created_at'],
        ['students', 'id'],
        ['students', 'class_id'],
        ['classes', 'id'],
        ['classes', 'name'],
        ['timetable', 'class_id'],
        ['timetable', 'tutor_id'],
    ];

    foreach ($requiredColumns as [$tableName, $columnName]) {
        if (!leaveColumnExists($db, $tableName, $columnName)) {
            echo json_encode([]);
            exit;
        }
    }

    $gradeExpression = leaveColumnExists($db, 'classes', 'grade')
        ? "CONCAT(c.name, ' · ', c.grade)"
        : 'c.name';

    $timetableDateFilter = leaveColumnExists($db, 'timetable', 'class_date')
        ? ' AND t.class_date = lr.absence_date'
        : '';

    $stmt = $db->prepare("
        SELECT DISTINCT
            lr.id,
            lr.student_id,
            {$studentNameExpression} AS student_name,
            {$gradeExpression} AS class_name,
            lr.absence_date,
            lr.reason,
            lr.status,
            lr.created_at
        FROM leave_requests lr
        JOIN students s ON s.id = lr.student_id
        JOIN classes c ON c.id = s.class_id
        {$studentUserJoin}
        WHERE EXISTS (
            SELECT 1
            FROM timetable t
            WHERE t.class_id = c.id AND t.tutor_id = ? {$timetableDateFilter}
        )
        ORDER BY
            CASE
                WHEN LOWER(lr.status) = 'pending' THEN 0
                WHEN LOWER(lr.status) = 'approved' THEN 1
                WHEN LOWER(lr.status) = 'denied' THEN 2
                ELSE 3
            END,
            lr.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$tutorId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows ?: []);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

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

function tutorStudentsTableExists(PDO $db, string $tableName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?
    ");
    $stmt->execute([$tableName]);
    return (int) $stmt->fetchColumn() > 0;
}

function tutorStudentsColumnExists(PDO $db, string $tableName, string $columnName): bool {
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

    $requiredTables = ['students', 'classes', 'timetable'];
    foreach ($requiredTables as $tableName) {
        if (!tutorStudentsTableExists($db, $tableName)) {
            echo json_encode(tutorMockStudents());
            exit;
        }
    }

    $studentNameExpression = null;
    if (
        tutorStudentsColumnExists($db, 'students', 'first_name') &&
        tutorStudentsColumnExists($db, 'students', 'last_name')
    ) {
        $studentNameExpression = "CONCAT(COALESCE(NULLIF(s.first_name, ''), ''), ' ', COALESCE(NULLIF(s.last_name, ''), ''))";
    } elseif (
        tutorStudentsColumnExists($db, 'students', 'user_id') &&
        tutorStudentsTableExists($db, 'users') &&
        tutorStudentsColumnExists($db, 'users', 'id') &&
        tutorStudentsColumnExists($db, 'users', 'full_name')
    ) {
        $studentNameExpression = "u.full_name";
    } elseif (tutorStudentsColumnExists($db, 'students', 'full_name')) {
        $studentNameExpression = "s.full_name";
    } else {
        echo json_encode(tutorMockStudents());
        exit;
    }

    $requiredColumns = [
        ['students', 'id'],
        ['students', 'class_id'],
        ['classes', 'id'],
        ['classes', 'name'],
        ['classes', 'grade'],
        ['timetable', 'class_id'],
        ['timetable', 'tutor_id'],
    ];

    foreach ($requiredColumns as [$tableName, $columnName]) {
        if (!tutorStudentsColumnExists($db, $tableName, $columnName)) {
            echo json_encode(tutorMockStudents());
            exit;
        }
    }

    $hasAttendance = tutorStudentsTableExists($db, 'attendance')
        && tutorStudentsColumnExists($db, 'attendance', 'student_id')
        && tutorStudentsColumnExists($db, 'attendance', 'status')
        && tutorStudentsColumnExists($db, 'attendance', 'id');

    $hasTerms = tutorStudentsTableExists($db, 'terms')
        && tutorStudentsColumnExists($db, 'terms', 'id')
        && tutorStudentsColumnExists($db, 'terms', 'is_current')
        && tutorStudentsColumnExists($db, 'timetable', 'term_id');

    $studentStatusFilter = '';
    if (tutorStudentsColumnExists($db, 'students', 'status')) {
      $studentStatusFilter = " AND LOWER(COALESCE(s.status, 'active')) = 'active'";
    }

    $userJoin = '';
    if (str_contains($studentNameExpression, 'u.')) {
        $userJoin = ' LEFT JOIN users u ON u.id = s.user_id ';
    }

    $attendanceSelect = $hasAttendance
        ? "COALESCE(ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(a.id), 0)) * 100, 0), 0) AS attendance_percent"
        : "0 AS attendance_percent";
    $attendanceJoin = $hasAttendance ? ' LEFT JOIN attendance a ON a.student_id = s.id ' : '';
    $termFilter = $hasTerms ? " AND t.term_id = (SELECT id FROM terms WHERE is_current = 1 LIMIT 1)" : '';

    $sql = "
        SELECT
            s.id,
            TRIM({$studentNameExpression}) AS name,
            c.grade,
            c.name AS class_name,
            {$attendanceSelect}
        FROM students s
        JOIN classes c ON s.class_id = c.id
        JOIN timetable t ON t.class_id = c.id
        {$userJoin}
        {$attendanceJoin}
        WHERE t.tutor_id = ?
        {$termFilter}
        {$studentStatusFilter}
        GROUP BY s.id, name, c.grade, c.name
        ORDER BY name ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$tutorId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(empty($rows) ? tutorMockStudents() : $rows);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

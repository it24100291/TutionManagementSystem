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

function performanceTableExists(PDO $db, string $tableName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?
    ");
    $stmt->execute([$tableName]);
    return (int) $stmt->fetchColumn() > 0;
}

function performanceColumnExists(PDO $db, string $tableName, string $columnName): bool {
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

    $requiredTables = ['exam_results', 'timetable', 'classes', 'terms'];
    foreach ($requiredTables as $tableName) {
        if (!performanceTableExists($db, $tableName)) {
            echo json_encode(tutorMockClassPerformance());
            exit;
        }
    }

    $requiredColumns = [
        ['exam_results', 'marks'],
        ['exam_results', 'student_id'],
        ['exam_results', 'class_id'],
        ['exam_results', 'term_id'],
        ['timetable', 'tutor_id'],
        ['timetable', 'class_id'],
        ['classes', 'id'],
        ['classes', 'name'],
        ['classes', 'grade'],
        ['terms', 'id'],
        ['terms', 'is_current'],
    ];

    foreach ($requiredColumns as [$tableName, $columnName]) {
        if (!performanceColumnExists($db, $tableName, $columnName)) {
            echo json_encode(tutorMockClassPerformance());
            exit;
        }
    }

    $stmt = $db->prepare("
        SELECT
            c.name AS class_name,
            c.grade,
            ROUND(AVG(er.marks), 0) AS avg_score,
            COUNT(DISTINCT er.student_id) AS student_count
        FROM exam_results er
        JOIN timetable t ON er.class_id = t.class_id
        JOIN classes c ON c.id = t.class_id
        WHERE t.tutor_id = ?
          AND er.term_id = (SELECT id FROM terms WHERE is_current = 1 LIMIT 1)
        GROUP BY c.id, c.name, c.grade
        ORDER BY avg_score DESC
    ");
    $stmt->execute([$tutorId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(empty($rows) ? tutorMockClassPerformance() : $rows);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

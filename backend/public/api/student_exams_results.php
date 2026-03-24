<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../src/config/env.php';
require_once __DIR__ . '/../../src/config/db.php';
require_once __DIR__ . '/student_mock_data.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$inputStudentId = isset($_GET['student_id']) ? trim((string) $_GET['student_id']) : '';
if ($inputStudentId === '' && isset($_SESSION['student_id'])) {
    $inputStudentId = trim((string) $_SESSION['student_id']);
}
if ($inputStudentId === '' && isset($_SESSION['user']['id'])) {
    $inputStudentId = trim((string) $_SESSION['user']['id']);
}

if ($inputStudentId === '' || !ctype_digit($inputStudentId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid student_id']);
    exit;
}

function studentExamsTableExists(PDO $db, string $tableName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?
    ");
    $stmt->execute([$tableName]);
    return (int) $stmt->fetchColumn() > 0;
}

function studentExamsColumnExists(PDO $db, string $tableName, string $columnName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
    ");
    $stmt->execute([$tableName, $columnName]);
    return (int) $stmt->fetchColumn() > 0;
}

function resolveExamStudentEntityId(PDO $db, int $userId): int {
    if (
        studentExamsTableExists($db, 'students') &&
        studentExamsColumnExists($db, 'students', 'id') &&
        studentExamsColumnExists($db, 'students', 'user_id')
    ) {
        $stmt = $db->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $studentRowId = $stmt->fetchColumn();
        if ($studentRowId !== false) {
            return (int) $studentRowId;
        }
    }

    return $userId;
}

try {
    $db = getDB();
    $userId = (int) $inputStudentId;
    $studentId = resolveExamStudentEntityId($db, $userId);

    $result = [
        'average_mark' => 0,
        'results' => [],
    ];

    if (
        studentExamsTableExists($db, 'exam_results') &&
        studentExamsColumnExists($db, 'exam_results', 'student_id')
    ) {
        $examNameExpr = studentExamsColumnExists($db, 'exam_results', 'exam_name')
            ? 'er.exam_name'
            : (studentExamsColumnExists($db, 'exam_results', 'name') ? 'er.name' : "'Exam'");
        $subjectExpr = studentExamsColumnExists($db, 'exam_results', 'subject')
            ? 'er.subject'
            : "'Subject'";
        $marksExpr = studentExamsColumnExists($db, 'exam_results', 'marks')
            ? 'COALESCE(er.marks, 0)'
            : '0';
        $totalMarksExpr = studentExamsColumnExists($db, 'exam_results', 'total_marks')
            ? 'COALESCE(er.total_marks, 100)'
            : '100';
        $gradeExpr = studentExamsColumnExists($db, 'exam_results', 'grade')
            ? 'COALESCE(er.grade, \'-\')'
            : "'-'";
        $orderExpr = studentExamsColumnExists($db, 'exam_results', 'id') ? 'er.id DESC' : '1 DESC';

        $stmt = $db->prepare("
            SELECT
                {$examNameExpr} AS exam_name,
                {$subjectExpr} AS subject,
                {$marksExpr} AS marks_obtained,
                {$totalMarksExpr} AS total_marks,
                {$gradeExpr} AS grade
            FROM exam_results er
            WHERE er.student_id = ?
            ORDER BY {$orderExpr}
        ");
        $stmt->execute([$studentId]);
        $result['results'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($result['results']) > 0) {
            $totalPercent = 0;
            foreach ($result['results'] as $row) {
                $totalMarks = max(1, (float) ($row['total_marks'] ?? 0));
                $marks = (float) ($row['marks_obtained'] ?? 0);
                $totalPercent += ($marks / $totalMarks) * 100;
            }
            $result['average_mark'] = (int) round($totalPercent / count($result['results']));
        }
    }

    $shouldUseMock =
        (int) ($result['average_mark'] ?? 0) === 0 &&
        empty($result['results']);

    echo json_encode($shouldUseMock ? studentMockExams($studentId) : $result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

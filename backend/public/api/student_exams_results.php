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
$inputTerm = trim((string) ($_GET['term'] ?? ''));
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

function normalizeStudentExamTerm(string $term): string
{
    $value = trim($term);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^term\s*(\d+)$/i', $value, $matches)) {
        return 'Term ' . $matches[1];
    }

    return $value;
}

function calculateStudentExamGrade(float $marks, float $totalMarks): string
{
    $percentage = $totalMarks > 0 ? ($marks / $totalMarks) * 100 : 0;

    if ($percentage >= 75) {
        return 'A';
    }
    if ($percentage >= 65) {
        return 'B';
    }
    if ($percentage >= 55) {
        return 'C';
    }
    if ($percentage >= 35) {
        return 'S';
    }

    return 'F';
}

function getStudentReportSubjects(): array
{
    return ['Tamil', 'Maths', 'Science', 'Religion', 'English', 'Civics', 'History', 'Geography', 'ICT', 'Health Science', 'Sinhala'];
}

function expandStudentResultsToSubjects(array $rows, string $selectedTerm): array
{
    $rowsBySubject = [];
    foreach ($rows as $row) {
        $subject = trim((string) ($row['subject'] ?? ''));
        if ($subject === '') {
            continue;
        }
        $rowsBySubject[$subject] = $row;
    }

    $expanded = [];
    foreach (getStudentReportSubjects() as $subject) {
        $row = $rowsBySubject[$subject] ?? [];
        $expanded[] = [
            'exam_name' => (string) ($row['exam_name'] ?? ''),
            'term' => normalizeStudentExamTerm((string) ($row['term'] ?? $selectedTerm)),
            'subject' => $subject,
            'marks_obtained' => array_key_exists('marks_obtained', $row) ? $row['marks_obtained'] : '',
            'highest_marks' => array_key_exists('highest_marks', $row) ? $row['highest_marks'] : '',
            'total_marks' => array_key_exists('total_marks', $row) ? $row['total_marks'] : 100,
            'grade' => (string) ($row['grade'] ?? ''),
        ];
    }

    return $expanded;
}

try {
    $db = getDB();
    $userId = (int) $inputStudentId;
    $studentId = resolveExamStudentEntityId($db, $userId);

    $result = [
        'available_terms' => ['Term 1', 'Term 2', 'Term 3'],
        'selected_term' => $inputTerm !== '' ? normalizeStudentExamTerm($inputTerm) : 'Term 1',
        'average_mark' => 0,
        'total_marks_obtained' => 0,
        'results' => [],
    ];

    $selectedTerm = (string) ($result['selected_term'] ?? 'Term 1');

    if (
        studentExamsTableExists($db, 'exam_marks') &&
        studentExamsTableExists($db, 'exams') &&
        studentExamsColumnExists($db, 'exam_marks', 'student_id') &&
        studentExamsColumnExists($db, 'exam_marks', 'exam_id') &&
        studentExamsColumnExists($db, 'exam_marks', 'subject') &&
        studentExamsColumnExists($db, 'exam_marks', 'marks') &&
        studentExamsColumnExists($db, 'exams', 'id') &&
        studentExamsColumnExists($db, 'exams', 'exam_name') &&
        studentExamsColumnExists($db, 'exams', 'term')
    ) {
        $termStmt = $db->prepare("
            SELECT DISTINCT e.term
            FROM exam_marks em
            JOIN exams e ON e.id = em.exam_id
            WHERE em.student_id = ?
            ORDER BY e.term ASC
        ");
        $termStmt->execute([$studentId]);
        $availableTerms = array_values(array_unique(array_filter(array_map(
            static fn ($term) => normalizeStudentExamTerm((string) $term),
            $termStmt->fetchAll(PDO::FETCH_COLUMN) ?: []
        ))));

        if (!empty($availableTerms)) {
            $result['available_terms'] = $availableTerms;
            if (!in_array($selectedTerm, $availableTerms, true)) {
                $selectedTerm = $availableTerms[0];
                $result['selected_term'] = $selectedTerm;
            }
        }

        $stmt = $db->prepare("
            SELECT
                e.exam_name,
                e.term,
                em.subject,
                em.marks AS marks_obtained,
                (
                    SELECT MAX(em2.marks)
                    FROM exam_marks em2
                    WHERE em2.exam_id = em.exam_id
                      AND em2.subject = em.subject
                ) AS highest_marks
            FROM exam_marks em
            JOIN exams e ON e.id = em.exam_id
            WHERE em.student_id = ? AND e.term = ?
            ORDER BY e.exam_date DESC, e.id DESC, em.subject ASC
        ");
        $stmt->execute([$studentId, $selectedTerm]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $normalizedRows = array_map(static function (array $row): array {
            $marks = (float) ($row['marks_obtained'] ?? 0);
            $totalMarks = 100.0;

            return [
                'exam_name' => (string) ($row['exam_name'] ?? 'Exam'),
                'term' => normalizeStudentExamTerm((string) ($row['term'] ?? '')),
                'subject' => (string) ($row['subject'] ?? 'Subject'),
                'marks_obtained' => (int) round($marks),
                'highest_marks' => isset($row['highest_marks']) && $row['highest_marks'] !== null ? (int) round((float) $row['highest_marks']) : '',
                'total_marks' => (int) $totalMarks,
                'grade' => calculateStudentExamGrade($marks, $totalMarks),
            ];
        }, $rows);
        $result['results'] = expandStudentResultsToSubjects($normalizedRows, $selectedTerm);

        if (count($normalizedRows) > 0) {
            $totalPercent = 0;
            $totalMarksObtained = 0;
            foreach ($normalizedRows as $row) {
                $totalMarks = max(1, (float) ($row['total_marks'] ?? 0));
                $marks = (float) ($row['marks_obtained'] ?? 0);
                $totalPercent += ($marks / $totalMarks) * 100;
                $totalMarksObtained += (int) round($marks);
            }
            $result['average_mark'] = (int) round($totalPercent / count($normalizedRows));
            $result['total_marks_obtained'] = $totalMarksObtained;
        }
    }

    if (
        empty($result['results']) &&
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
        $termExpr = studentExamsColumnExists($db, 'exam_results', 'term')
            ? 'er.term'
            : (studentExamsColumnExists($db, 'exam_results', 'term_name') ? 'er.term_name' : "'Term 1'");
        $orderExpr = studentExamsColumnExists($db, 'exam_results', 'id') ? 'er.id DESC' : '1 DESC';
        $termFilterSql = $inputTerm !== '' && (
            studentExamsColumnExists($db, 'exam_results', 'term') ||
            studentExamsColumnExists($db, 'exam_results', 'term_name')
        ) ? " AND {$termExpr} = ?" : '';

        $stmt = $db->prepare("
            SELECT
                {$examNameExpr} AS exam_name,
                {$termExpr} AS term,
                {$subjectExpr} AS subject,
                {$marksExpr} AS marks_obtained,
                {$marksExpr} AS highest_marks,
                {$totalMarksExpr} AS total_marks,
                {$gradeExpr} AS grade
            FROM exam_results er
            WHERE er.student_id = ?
            {$termFilterSql}
            ORDER BY {$orderExpr}
        ");
        $params = [$studentId];
        if ($termFilterSql !== '') {
            $params[] = $selectedTerm;
        }
        $stmt->execute($params);
        $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $normalizedRows = array_map(static function (array $row): array {
            return [
                'exam_name' => (string) ($row['exam_name'] ?? ''),
                'term' => normalizeStudentExamTerm((string) ($row['term'] ?? '')),
                'subject' => (string) ($row['subject'] ?? ''),
                'marks_obtained' => $row['marks_obtained'] ?? '',
                'highest_marks' => $row['highest_marks'] ?? '',
                'total_marks' => $row['total_marks'] ?? 100,
                'grade' => (string) ($row['grade'] ?? ''),
            ];
        }, $rawRows);
        $result['results'] = expandStudentResultsToSubjects($normalizedRows, $selectedTerm);

        if (studentExamsColumnExists($db, 'exam_results', 'term') || studentExamsColumnExists($db, 'exam_results', 'term_name')) {
            $termListStmt = $db->prepare("
                SELECT DISTINCT {$termExpr} AS term
                FROM exam_results er
                WHERE er.student_id = ?
                ORDER BY term ASC
            ");
            $termListStmt->execute([$studentId]);
            $availableTerms = array_values(array_unique(array_filter(array_map(
                static fn ($term) => normalizeStudentExamTerm((string) $term),
                $termListStmt->fetchAll(PDO::FETCH_COLUMN) ?: []
            ))));
            if (!empty($availableTerms)) {
                $result['available_terms'] = $availableTerms;
            }
        }

        if (count($normalizedRows) > 0) {
            $totalPercent = 0;
            $totalMarksObtained = 0;
            foreach ($normalizedRows as $row) {
                $totalMarks = max(1, (float) ($row['total_marks'] ?? 0));
                $marks = (float) ($row['marks_obtained'] ?? 0);
                $totalPercent += ($marks / $totalMarks) * 100;
                $totalMarksObtained += $marks === '' ? 0 : (int) round((float) $marks);
            }
            $result['average_mark'] = (int) round($totalPercent / count($normalizedRows));
            $result['total_marks_obtained'] = $totalMarksObtained;
        }
    }

    $shouldUseMock =
        (int) ($result['average_mark'] ?? 0) === 0 &&
        (int) ($result['total_marks_obtained'] ?? 0) === 0 &&
        empty($result['results']);

    echo json_encode($shouldUseMock ? studentMockExams($studentId, $selectedTerm) : $result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

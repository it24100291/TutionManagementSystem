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

function studentAttendanceTableExists(PDO $db, string $tableName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?
    ");
    $stmt->execute([$tableName]);
    return (int) $stmt->fetchColumn() > 0;
}

function studentAttendanceColumnExists(PDO $db, string $tableName, string $columnName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
    ");
    $stmt->execute([$tableName, $columnName]);
    return (int) $stmt->fetchColumn() > 0;
}

function resolveAttendanceStudentEntityId(PDO $db, int $userId): int {
    if (
        studentAttendanceTableExists($db, 'students') &&
        studentAttendanceColumnExists($db, 'students', 'id') &&
        studentAttendanceColumnExists($db, 'students', 'user_id')
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
    $studentId = resolveAttendanceStudentEntityId($db, $userId);

    $result = [
        'total_classes_held' => 0,
        'total_present' => 0,
        'total_absent' => 0,
        'attendance_percentage' => 0,
        'history' => [],
    ];

    if (
        studentAttendanceTableExists($db, 'attendance') &&
        studentAttendanceColumnExists($db, 'attendance', 'student_id') &&
        studentAttendanceColumnExists($db, 'attendance', 'status') &&
        studentAttendanceColumnExists($db, 'attendance', 'id')
    ) {
        $stmt = $db->prepare("
            SELECT
                COUNT(*) AS total_count,
                SUM(CASE WHEN LOWER(status) IN ('present', 'excused') THEN 1 ELSE 0 END) AS present_count,
                SUM(CASE WHEN LOWER(status) = 'absent' THEN 1 ELSE 0 END) AS absent_count
            FROM attendance
            WHERE student_id = ?
        ");
        $stmt->execute([$studentId]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        $total = (int) ($summary['total_count'] ?? 0);
        $present = (int) ($summary['present_count'] ?? 0);
        $absent = (int) ($summary['absent_count'] ?? 0);

        $result['total_classes_held'] = $total;
        $result['total_present'] = $present;
        $result['total_absent'] = $absent;
        $result['attendance_percentage'] = $total > 0 ? (int) round(($present / $total) * 100) : 0;

        $subjectExpr = "'Class Session'";
        $joins = '';
        if (
            studentAttendanceColumnExists($db, 'attendance', 'timetable_id') &&
            studentAttendanceTableExists($db, 'timetable') &&
            studentAttendanceColumnExists($db, 'timetable', 'id') &&
            studentAttendanceColumnExists($db, 'timetable', 'class_id') &&
            studentAttendanceTableExists($db, 'classes') &&
            studentAttendanceColumnExists($db, 'classes', 'id') &&
            studentAttendanceColumnExists($db, 'classes', 'name')
        ) {
            $joins = '
                LEFT JOIN timetable t ON t.id = a.timetable_id
                LEFT JOIN classes c ON c.id = t.class_id
            ';
            $subjectExpr = 'COALESCE(c.name, \'Class Session\')';
        }

        $dateExpr = studentAttendanceColumnExists($db, 'attendance', 'marked_at')
            ? "DATE_FORMAT(a.marked_at, '%Y-%m-%d')"
            : "''";
        $orderExpr = studentAttendanceColumnExists($db, 'attendance', 'marked_at')
            ? 'a.marked_at DESC'
            : 'a.id DESC';

        $stmt = $db->prepare("
            SELECT
                {$dateExpr} AS date,
                {$subjectExpr} AS subject,
                a.status
            FROM attendance a
            {$joins}
            WHERE a.student_id = ?
            ORDER BY {$orderExpr}
        ");
        $stmt->execute([$studentId]);
        $result['history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $shouldUseMock =
        (int) ($result['total_classes_held'] ?? 0) === 0 &&
        empty($result['history']);

    echo json_encode($shouldUseMock ? studentMockAttendance($studentId) : $result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

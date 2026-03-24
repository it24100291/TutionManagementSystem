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

function studentOverviewTableExists(PDO $db, string $tableName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?
    ");
    $stmt->execute([$tableName]);
    return (int) $stmt->fetchColumn() > 0;
}

function studentOverviewColumnExists(PDO $db, string $tableName, string $columnName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
    ");
    $stmt->execute([$tableName, $columnName]);
    return (int) $stmt->fetchColumn() > 0;
}

function resolveStudentEntityId(PDO $db, int $userId): int {
    if (
        studentOverviewTableExists($db, 'students') &&
        studentOverviewColumnExists($db, 'students', 'id') &&
        studentOverviewColumnExists($db, 'students', 'user_id')
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
    $studentId = resolveStudentEntityId($db, $userId);

    $result = [
        'total_enrolled_classes' => 0,
        'attendance_percentage' => 0,
        'pending_payments_count' => 0,
        'upcoming_exams_count' => 0,
        'todays_classes' => [],
        'announcements' => [],
    ];

    if (
        studentOverviewTableExists($db, 'student_class') &&
        studentOverviewColumnExists($db, 'student_class', 'student_id')
    ) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM student_class WHERE student_id = ?");
        $stmt->execute([$studentId]);
        $result['total_enrolled_classes'] = (int) $stmt->fetchColumn();
    } elseif (
        studentOverviewTableExists($db, 'students') &&
        studentOverviewColumnExists($db, 'students', 'id') &&
        studentOverviewColumnExists($db, 'students', 'class_id')
    ) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM students WHERE id = ? AND class_id IS NOT NULL");
        $stmt->execute([$studentId]);
        $result['total_enrolled_classes'] = (int) $stmt->fetchColumn();
    }

    if (
        studentOverviewTableExists($db, 'attendance') &&
        studentOverviewColumnExists($db, 'attendance', 'student_id') &&
        studentOverviewColumnExists($db, 'attendance', 'status') &&
        studentOverviewColumnExists($db, 'attendance', 'id')
    ) {
        $stmt = $db->prepare("
            SELECT
                COUNT(*) AS total_count,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_count
            FROM attendance
            WHERE student_id = ?
        ");
        $stmt->execute([$studentId]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = (int) ($attendance['total_count'] ?? 0);
        $present = (int) ($attendance['present_count'] ?? 0);
        $result['attendance_percentage'] = $total > 0 ? (int) round(($present / $total) * 100) : 0;
    }

    if (
        studentOverviewTableExists($db, 'payments') &&
        studentOverviewColumnExists($db, 'payments', 'student_id') &&
        studentOverviewColumnExists($db, 'payments', 'status')
    ) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM payments WHERE student_id = ? AND status = 'Unpaid'");
        $stmt->execute([$studentId]);
        $result['pending_payments_count'] = (int) $stmt->fetchColumn();
    } elseif (
        studentOverviewTableExists($db, 'fee_payments') &&
        studentOverviewColumnExists($db, 'fee_payments', 'student_id') &&
        studentOverviewColumnExists($db, 'fee_payments', 'status')
    ) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM fee_payments WHERE student_id = ? AND LOWER(status) = 'unpaid'");
        $stmt->execute([$studentId]);
        $result['pending_payments_count'] = (int) $stmt->fetchColumn();
    }

    if (
        studentOverviewTableExists($db, 'students') &&
        studentOverviewColumnExists($db, 'students', 'id') &&
        studentOverviewColumnExists($db, 'students', 'class_id') &&
        studentOverviewTableExists($db, 'exams') &&
        studentOverviewColumnExists($db, 'exams', 'class_id')
    ) {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM exams
            WHERE class_id IN (
                SELECT class_id FROM students WHERE id = ? AND class_id IS NOT NULL
            )
        ");
        $stmt->execute([$studentId]);
        $result['upcoming_exams_count'] = (int) $stmt->fetchColumn();
    }

    if (
        studentOverviewTableExists($db, 'students') &&
        studentOverviewColumnExists($db, 'students', 'id') &&
        studentOverviewColumnExists($db, 'students', 'class_id') &&
        studentOverviewTableExists($db, 'timetable') &&
        studentOverviewColumnExists($db, 'timetable', 'class_id') &&
        studentOverviewColumnExists($db, 'timetable', 'class_date')
    ) {
        $classNameExpr = "'Class'";
        if (
            studentOverviewTableExists($db, 'classes') &&
            studentOverviewColumnExists($db, 'classes', 'id') &&
            studentOverviewColumnExists($db, 'classes', 'name')
        ) {
            $classNameExpr = 'c.name';
        }

        $gradeExpr = "''";
        if (
            studentOverviewTableExists($db, 'classes') &&
            studentOverviewColumnExists($db, 'classes', 'grade')
        ) {
            $gradeExpr = 'c.grade';
        }

        $roomExpr = studentOverviewColumnExists($db, 'timetable', 'room') ? 't.room' : "''";
        $startExpr = studentOverviewColumnExists($db, 'timetable', 'start_time') ? 't.start_time' : "''";
        $classJoin = studentOverviewTableExists($db, 'classes') ? ' LEFT JOIN classes c ON c.id = t.class_id ' : '';

        $stmt = $db->prepare("
            SELECT
                {$classNameExpr} AS class_name,
                {$gradeExpr} AS grade,
                {$startExpr} AS start_time,
                {$roomExpr} AS room
            FROM timetable t
            {$classJoin}
            WHERE t.class_id IN (
                SELECT class_id FROM students WHERE id = ? AND class_id IS NOT NULL
            )
              AND DATE(t.class_date) = CURDATE()
            ORDER BY " . (studentOverviewColumnExists($db, 'timetable', 'start_time') ? 't.start_time ASC' : 't.id ASC')
        );
        $stmt->execute([$studentId]);
        $result['todays_classes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (studentOverviewTableExists($db, 'announcements')) {
        $titleExpr = studentOverviewColumnExists($db, 'announcements', 'title') ? 'title' : "'Announcement'";
        $messageExpr = studentOverviewColumnExists($db, 'announcements', 'message')
            ? 'message'
            : (studentOverviewColumnExists($db, 'announcements', 'content') ? 'content' : "''");
        $dateExpr = studentOverviewColumnExists($db, 'announcements', 'created_at')
            ? "DATE_FORMAT(created_at, '%Y-%m-%d')"
            : "''";

        $stmt = $db->query("
            SELECT
                {$titleExpr} AS title,
                {$messageExpr} AS message,
                {$dateExpr} AS created_at
            FROM announcements
            ORDER BY " . (studentOverviewColumnExists($db, 'announcements', 'created_at') ? 'created_at DESC' : '1 DESC') . "
            LIMIT 5
        ");
        $result['announcements'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $shouldUseMock =
        (int) ($result['total_enrolled_classes'] ?? 0) === 0 &&
        (int) ($result['attendance_percentage'] ?? 0) === 0 &&
        (int) ($result['pending_payments_count'] ?? 0) === 0 &&
        (int) ($result['upcoming_exams_count'] ?? 0) === 0 &&
        empty($result['todays_classes']) &&
        empty($result['announcements']);

    echo json_encode($shouldUseMock ? studentMockOverview($studentId) : $result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

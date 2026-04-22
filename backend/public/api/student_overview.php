<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../src/config/env.php';
require_once __DIR__ . '/../../src/config/db.php';

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

function resolveStudentOverviewGrade(PDO $db, int $userId, int $studentId): ?string {
    if (
        studentOverviewTableExists($db, 'students') &&
        studentOverviewColumnExists($db, 'students', 'grade')
    ) {
        if (studentOverviewColumnExists($db, 'students', 'user_id')) {
            $stmt = $db->prepare("SELECT grade FROM students WHERE user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $grade = $stmt->fetchColumn();
            if ($grade !== false && trim((string) $grade) !== '') {
                return trim((string) $grade);
            }
        }

        if (studentOverviewColumnExists($db, 'students', 'id')) {
            $stmt = $db->prepare("SELECT grade FROM students WHERE id = ? LIMIT 1");
            $stmt->execute([$studentId]);
            $grade = $stmt->fetchColumn();
            if ($grade !== false && trim((string) $grade) !== '') {
                return trim((string) $grade);
            }
        }
    }

    return null;
}

function normalizeStudentOverviewGrade(?string $grade): ?string {
    $value = trim((string) $grade);
    if ($value === '') {
        return null;
    }

    if (preg_match('/^Grade\s*(\d+)$/i', $value, $matches)) {
        return 'Grade ' . $matches[1];
    }

    if (ctype_digit($value)) {
        return 'Grade ' . $value;
    }

    return $value;
}

function buildStudentOverviewGradeVariants(?string $grade): array {
    $value = normalizeStudentOverviewGrade($grade);
    if ($value === null) {
        return [];
    }

    $variants = [$value];
    if (preg_match('/^Grade\s*(\d+)$/i', $value, $matches)) {
        $variants[] = $matches[1];
    }

    return array_values(array_unique(array_filter($variants)));
}

function studentOverviewTodayName(): string {
    return date('l');
}

try {
    $db = getDB();
    $userId = (int) $inputStudentId;
    $studentId = resolveStudentEntityId($db, $userId);
    $studentGrade = normalizeStudentOverviewGrade(resolveStudentOverviewGrade($db, $userId, $studentId));
    $gradeVariants = buildStudentOverviewGradeVariants($studentGrade);

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
    } elseif (
        !empty($gradeVariants) &&
        studentOverviewTableExists($db, 'timetable') &&
        studentOverviewColumnExists($db, 'timetable', 'grade')
    ) {
        $placeholders = implode(',', array_fill(0, count($gradeVariants), '?'));
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM timetable
            WHERE grade IN ({$placeholders})
        ");
        $stmt->execute($gradeVariants);
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
        (
            studentOverviewColumnExists($db, 'payments', 'student_id') ||
            studentOverviewColumnExists($db, 'payments', 'user_id')
        ) &&
        studentOverviewColumnExists($db, 'payments', 'status')
    ) {
        $keyColumn = studentOverviewColumnExists($db, 'payments', 'student_id') ? 'student_id' : 'user_id';
        $keyValue = $keyColumn === 'student_id' ? $studentId : $userId;
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM payments
            WHERE {$keyColumn} = ?
              AND LOWER(status) IN ('unpaid', 'pending')
        ");
        $stmt->execute([$keyValue]);
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
        studentOverviewTableExists($db, 'exams') &&
        !empty($gradeVariants)
    ) {
        if (studentOverviewColumnExists($db, 'exams', 'grade')) {
            $placeholders = implode(',', array_fill(0, count($gradeVariants), '?'));
            $dateFilter = studentOverviewColumnExists($db, 'exams', 'exam_date')
                ? ' AND exam_date >= CURDATE()'
                : '';
            $stmt = $db->prepare("
                SELECT COUNT(*)
                FROM exams
                WHERE grade IN ({$placeholders}){$dateFilter}
            ");
            $stmt->execute($gradeVariants);
            $result['upcoming_exams_count'] = (int) $stmt->fetchColumn();
        } elseif (
            studentOverviewColumnExists($db, 'exams', 'class_id') &&
            studentOverviewTableExists($db, 'students') &&
            studentOverviewColumnExists($db, 'students', 'id') &&
            studentOverviewColumnExists($db, 'students', 'class_id')
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
    }

    if (
        studentOverviewTableExists($db, 'timetable') &&
        !empty($gradeVariants)
    ) {
        if (
            studentOverviewColumnExists($db, 'timetable', 'grade') &&
            studentOverviewColumnExists($db, 'timetable', 'day') &&
            studentOverviewColumnExists($db, 'timetable', 'time_slot')
        ) {
            $placeholders = implode(',', array_fill(0, count($gradeVariants), '?'));
            $roomExpr = studentOverviewColumnExists($db, 'timetable', 'room') ? 'COALESCE(room, \'\')' : "''";
            $subjectExpr = studentOverviewColumnExists($db, 'timetable', 'subject') ? 'subject' : "'Class'";
            $stmt = $db->prepare("
                SELECT
                    {$subjectExpr} AS class_name,
                    grade,
                    time_slot AS start_time,
                    {$roomExpr} AS room
                FROM timetable
                WHERE grade IN ({$placeholders})
                  AND day = ?
                ORDER BY STR_TO_DATE(SUBSTRING_INDEX(time_slot, ' - ', 1), '%H:%i') ASC, id ASC
            ");
            $params = $gradeVariants;
            $params[] = studentOverviewTodayName();
            $stmt->execute($params);
            $result['todays_classes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif (
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

    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

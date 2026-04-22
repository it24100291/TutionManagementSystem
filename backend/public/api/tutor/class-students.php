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

$classId = isset($_GET['class_id']) ? trim((string) $_GET['class_id']) : '';
$grade = isset($_GET['grade']) ? trim((string) $_GET['grade']) : '';
$timetableId = isset($_GET['timetable_id']) ? trim((string) $_GET['timetable_id']) : '';
$inputTutorId = isset($_GET['tutor_id']) ? trim((string) $_GET['tutor_id']) : '';

if ($inputTutorId === '' && isset($_SESSION['tutor_id'])) {
    $inputTutorId = trim((string) $_SESSION['tutor_id']);
}
if ($inputTutorId === '' && isset($_SESSION['user']['id'])) {
    $inputTutorId = trim((string) $_SESSION['user']['id']);
}

if ($classId === '' && $grade === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid class identifier']);
    exit;
}

if ($classId !== '' && !ctype_digit($classId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid class_id']);
    exit;
}

if ($inputTutorId === '' || !ctype_digit($inputTutorId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid tutor_id']);
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

function resolveClassStudentsTutorIds(PDO $db, int $rawTutorId): array {
    $resolvedIds = [$rawTutorId];

    if (
        classStudentsTableExists($db, 'tutors') &&
        classStudentsColumnExists($db, 'tutors', 'id') &&
        classStudentsColumnExists($db, 'tutors', 'user_id')
    ) {
        $resolveStmt = $db->prepare("SELECT id, user_id FROM tutors WHERE id = ? OR user_id = ?");
        $resolveStmt->execute([$rawTutorId, $rawTutorId]);

        foreach ($resolveStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $resolvedIds[] = (int) ($row['id'] ?? 0);
            $resolvedIds[] = (int) ($row['user_id'] ?? 0);
        }
    }

    return array_values(array_unique(array_filter($resolvedIds)));
}

function classStudentsBuildGradeVariants(string $grade): array {
    $value = trim($grade);
    if ($value === '') {
        return [];
    }

    $variants = [$value];
    if (preg_match('/^Grade\s+(\d+)$/i', $value, $matches)) {
        $variants[] = $matches[1];
    } elseif (preg_match('/^\d+$/', $value)) {
        $variants[] = 'Grade ' . $value;
    }

    return array_values(array_unique(array_filter(array_map('trim', $variants))));
}

try {
    $db = getDB();
    $classIdInt = $classId !== '' ? (int) $classId : 0;
    $tutorIds = resolveClassStudentsTutorIds($db, (int) $inputTutorId);

    if (
        !classStudentsTableExists($db, 'students') ||
        !classStudentsColumnExists($db, 'students', 'id') ||
        (
            !classStudentsColumnExists($db, 'students', 'class_id') &&
            !classStudentsColumnExists($db, 'students', 'grade')
        )
    ) {
        echo json_encode([]);
        exit;
    }

    $hasRecurringTimetableColumns =
        classStudentsTableExists($db, 'timetable') &&
        classStudentsColumnExists($db, 'timetable', 'id') &&
        classStudentsColumnExists($db, 'timetable', 'grade') &&
        classStudentsColumnExists($db, 'timetable', 'day') &&
        classStudentsColumnExists($db, 'timetable', 'time_slot') &&
        classStudentsColumnExists($db, 'timetable', 'tutor_id');

    $hasDatedTimetableColumns =
        $timetableId !== '' &&
        ctype_digit($timetableId) &&
        classStudentsTableExists($db, 'timetable') &&
        classStudentsColumnExists($db, 'timetable', 'id') &&
        classStudentsColumnExists($db, 'timetable', 'class_id') &&
        classStudentsColumnExists($db, 'timetable', 'tutor_id') &&
        classStudentsColumnExists($db, 'timetable', 'class_date') &&
        classStudentsColumnExists($db, 'timetable', 'start_time');

    if ($hasDatedTimetableColumns) {
        $placeholders = implode(',', array_fill(0, count($tutorIds), '?'));
        $accessStmt = $db->prepare("
            SELECT t.id
            FROM timetable t
            WHERE t.id = ?
              AND t.class_id = ?
              AND t.class_date = CURDATE()
              AND t.tutor_id IN ({$placeholders})
              AND t.id = (
                  SELECT t2.id
                  FROM timetable t2
                  WHERE t2.class_date = CURDATE()
                    AND t2.tutor_id IN ({$placeholders})
                  ORDER BY t2.start_time ASC, t2.id ASC
                  LIMIT 1
              )
            LIMIT 1
        ");
        $accessStmt->execute(array_merge([(int) $timetableId, $classIdInt], $tutorIds, $tutorIds));

        if (!$accessStmt->fetchColumn()) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only access attendance for your first class today.']);
            exit;
        }
    }

    if (
        $timetableId !== '' &&
        ctype_digit($timetableId) &&
        $hasRecurringTimetableColumns &&
        classStudentsColumnExists($db, 'students', 'grade')
    ) {
        $placeholders = implode(',', array_fill(0, count($tutorIds), '?'));
        $gradeStmt = $db->prepare("
            SELECT t.grade
            FROM timetable t
            WHERE t.id = ?
              AND t.tutor_id IN ({$placeholders})
              AND t.day = ?
              AND t.id = (
                  SELECT t2.id
                  FROM timetable t2
                  WHERE t2.day = ?
                    AND t2.tutor_id IN ({$placeholders})
                  ORDER BY STR_TO_DATE(TRIM(SUBSTRING_INDEX(t2.time_slot, '-', 1)), '%H:%i') ASC, t2.id ASC
                  LIMIT 1
              )
            LIMIT 1
        ");
        $today = date('l');
        $gradeStmt->execute(array_merge([(int) $timetableId], $tutorIds, [$today, $today], $tutorIds));
        $resolvedGrade = $gradeStmt->fetchColumn();

        if ($resolvedGrade === false || trim((string) $resolvedGrade) === '') {
            http_response_code(403);
            echo json_encode(['error' => 'You can only access attendance for your first class today.']);
            exit;
        }

        $grade = trim((string) $resolvedGrade);
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

    $filterSql = '';
    $queryParams = [];
    if ($classId !== '' && classStudentsColumnExists($db, 'students', 'class_id')) {
        $filterSql = 's.class_id = :class_id';
        $queryParams[':class_id'] = $classIdInt;
    } elseif ($grade !== '' && classStudentsColumnExists($db, 'students', 'grade')) {
        $gradeVariants = classStudentsBuildGradeVariants($grade);
        if (empty($gradeVariants)) {
            echo json_encode([]);
            exit;
        }
        $gradePlaceholders = [];
        foreach ($gradeVariants as $index => $gradeVariant) {
            $paramName = ':grade_' . $index;
            $gradePlaceholders[] = $paramName;
            $queryParams[$paramName] = $gradeVariant;
        }

        $filterSql = "s.grade IN (" . implode(',', $gradePlaceholders) . ")";
    } else {
        echo json_encode([]);
        exit;
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
        WHERE {$filterSql}
        {$statusFilterSql}
        ORDER BY name ASC
    ";

    $stmt = $db->prepare($sql);
    if ($attendanceJoinSql !== '') {
        $queryParams[':timetable_id'] = (int) $timetableId;
    }
    foreach ($queryParams as $paramName => $paramValue) {
        $paramType = is_int($paramValue) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($paramName, $paramValue, $paramType);
    }
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows ?: []);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

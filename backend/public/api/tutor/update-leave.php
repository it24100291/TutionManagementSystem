<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../src/config/env.php';
require_once __DIR__ . '/../../../src/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'POST';
if (!in_array($method, ['POST', 'PATCH'], true)) {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

$leaveId = isset($data['leave_id']) ? trim((string) $data['leave_id']) : '';
$status = isset($data['status']) ? strtolower(trim((string) $data['status'])) : '';
$denyReason = isset($data['deny_reason']) ? trim((string) $data['deny_reason']) : '';
$inputTutorId = isset($data['tutor_id']) ? trim((string) $data['tutor_id']) : '';

if ($inputTutorId === '' && isset($_SESSION['tutor_id'])) {
    $inputTutorId = trim((string) $_SESSION['tutor_id']);
}
if ($inputTutorId === '' && isset($_SESSION['user']['id'])) {
    $inputTutorId = trim((string) $_SESSION['user']['id']);
}

if ($leaveId === '' || !ctype_digit($leaveId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid leave_id']);
    exit;
}

if (!in_array($status, ['approved', 'denied'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid status']);
    exit;
}

if ($inputTutorId === '' || !ctype_digit($inputTutorId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid tutor_id']);
    exit;
}

function updateLeaveTableExists(PDO $db, string $tableName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?
    ");
    $stmt->execute([$tableName]);
    return (int) $stmt->fetchColumn() > 0;
}

function updateLeaveColumnExists(PDO $db, string $tableName, string $columnName): bool {
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
    $leaveRequestId = (int) $leaveId;

    $requiredTables = ['leave_requests', 'students', 'classes', 'timetable'];
    foreach ($requiredTables as $tableName) {
        if (!updateLeaveTableExists($db, $tableName)) {
            http_response_code(404);
            echo json_encode(['error' => 'Leave request data is not available']);
            exit;
        }
    }

    $timetableDateFilter = updateLeaveColumnExists($db, 'timetable', 'class_date')
        ? ' AND t.class_date = lr.absence_date'
        : '';

    $stmt = $db->prepare("
        SELECT
            lr.id,
            lr.student_id,
            lr.absence_date,
            s.class_id
        FROM leave_requests lr
        JOIN students s ON s.id = lr.student_id
        WHERE lr.id = ?
          AND EXISTS (
            SELECT 1
            FROM timetable t
            WHERE t.class_id = s.class_id AND t.tutor_id = ? {$timetableDateFilter}
          )
        LIMIT 1
    ");
    $stmt->execute([$leaveRequestId, $tutorId]);
    $leaveRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leaveRow) {
        http_response_code(404);
        echo json_encode(['error' => 'Leave request not found']);
        exit;
    }

    $db->beginTransaction();

    if (
        updateLeaveColumnExists($db, 'leave_requests', 'deny_reason') &&
        updateLeaveColumnExists($db, 'leave_requests', 'updated_at')
    ) {
        $stmt = $db->prepare("
            UPDATE leave_requests
            SET status = ?, deny_reason = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $denyReason !== '' ? $denyReason : null, $leaveRequestId]);
    } elseif (updateLeaveColumnExists($db, 'leave_requests', 'updated_at')) {
        $stmt = $db->prepare("
            UPDATE leave_requests
            SET status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $leaveRequestId]);
    } else {
        $stmt = $db->prepare("
            UPDATE leave_requests
            SET status = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $leaveRequestId]);
    }

    if (
        $status === 'approved' &&
        updateLeaveTableExists($db, 'attendance') &&
        updateLeaveColumnExists($db, 'attendance', 'student_id') &&
        updateLeaveColumnExists($db, 'attendance', 'status') &&
        updateLeaveColumnExists($db, 'attendance', 'marked_at')
    ) {
        $attendanceDate = (string) $leaveRow['absence_date'];
        $studentId = (int) $leaveRow['student_id'];

        if (updateLeaveColumnExists($db, 'attendance', 'timetable_id')) {
            $stmt = $db->prepare("
                UPDATE attendance
                SET status = 'excused'
                WHERE student_id = ?
                  AND DATE(marked_at) = ?
            ");
            $stmt->execute([$studentId, $attendanceDate]);
        } else {
            $stmt = $db->prepare("
                UPDATE attendance
                SET status = 'excused'
                WHERE student_id = ?
                  AND DATE(marked_at) = ?
            ");
            $stmt->execute([$studentId, $attendanceDate]);
        }
    }

    $db->commit();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

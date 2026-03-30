<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../src/config/env.php';
require_once __DIR__ . '/../../../src/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'POST') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$inputTutorId = isset($data['tutor_id']) ? trim((string) $data['tutor_id']) : '';
$inputTimetableId = isset($data['timetable_id']) ? trim((string) $data['timetable_id']) : '';
$records = $data['records'] ?? null;

if ($inputTutorId === '' && isset($_SESSION['tutor_id'])) {
    $inputTutorId = trim((string) $_SESSION['tutor_id']);
}
if ($inputTutorId === '' && isset($_SESSION['user']['id'])) {
    $inputTutorId = trim((string) $_SESSION['user']['id']);
}

if (
    $inputTutorId === '' || !ctype_digit($inputTutorId) ||
    $inputTimetableId === '' || !ctype_digit($inputTimetableId) ||
    !is_array($records) || count($records) === 0
) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid tutor_id, timetable_id, or records']);
    exit;
}

try {
    $db = getDB();
    $tutorId = (int) $inputTutorId;
    $timetableId = (int) $inputTimetableId;

    $db->exec("
        CREATE TABLE IF NOT EXISTS attendance (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            student_id INT UNSIGNED NOT NULL,
            timetable_id INT UNSIGNED NOT NULL,
            tutor_id INT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL,
            marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $db->beginTransaction();

    $existingStmt = $db->prepare("
        SELECT COUNT(*) FROM attendance
        WHERE timetable_id = ? AND tutor_id = ? AND DATE(marked_at) = CURDATE()
    ");
    $existingStmt->execute([$timetableId, $tutorId]);
    if ((int) $existingStmt->fetchColumn() > 0) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        http_response_code(400);
        echo json_encode(['error' => 'Attendance already submitted for this class today']);
        exit;
    }

    $insertStmt = $db->prepare("
        INSERT INTO attendance (student_id, timetable_id, tutor_id, status, marked_at)
        VALUES (?, ?, ?, ?, NOW())
    ");

    $count = 0;
    foreach ($records as $record) {
        $studentId = isset($record['student_id']) ? trim((string) $record['student_id']) : '';
        $status = isset($record['status']) ? trim((string) $record['status']) : '';

        if ($studentId === '' || !ctype_digit($studentId) || !in_array($status, ['present', 'absent'], true)) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            http_response_code(400);
            echo json_encode(['error' => 'Invalid attendance record payload']);
            exit;
        }

        $insertStmt->execute([(int) $studentId, $timetableId, $tutorId, $status]);
        $count++;
    }

    $db->commit();
    echo json_encode(['success' => true, 'count' => $count, 'message' => "Attendance submitted for {$count} students."]);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

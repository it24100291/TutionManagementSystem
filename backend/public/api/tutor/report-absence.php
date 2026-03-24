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

if ($inputTutorId === '' && isset($_SESSION['tutor_id'])) {
    $inputTutorId = trim((string) $_SESSION['tutor_id']);
}
if ($inputTutorId === '' && isset($_SESSION['user']['id'])) {
    $inputTutorId = trim((string) $_SESSION['user']['id']);
}

if ($inputTutorId === '' || !ctype_digit($inputTutorId) || $inputTimetableId === '' || !ctype_digit($inputTimetableId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid tutor_id or timetable_id']);
    exit;
}

try {
    $db = getDB();
    $tutorId = (int) $inputTutorId;
    $timetableId = (int) $inputTimetableId;

    $db->beginTransaction();

    $db->exec("
        CREATE TABLE IF NOT EXISTS tutor_absences (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tutor_id INT UNSIGNED NOT NULL,
            timetable_id INT UNSIGNED NOT NULL,
            reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $checkStmt = $db->prepare("SELECT COUNT(*) FROM timetable WHERE id = ? AND tutor_id = ?");
    $checkStmt->execute([$timetableId, $tutorId]);
    if (!(int) $checkStmt->fetchColumn()) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Timetable record not found']);
        exit;
    }

    $insertStmt = $db->prepare("
        INSERT INTO tutor_absences (tutor_id, timetable_id, reported_at)
        VALUES (?, ?, NOW())
    ");
    $insertStmt->execute([$tutorId, $timetableId]);

    $updateStmt = $db->prepare("UPDATE timetable SET status = 'absent_reported' WHERE id = ? AND tutor_id = ?");
    $updateStmt->execute([$timetableId, $tutorId]);

    $db->commit();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

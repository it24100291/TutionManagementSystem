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

function tutorClassesTableExists(PDO $db, string $tableName): bool {
    $stmt = $db->prepare(" 
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?
    ");
    $stmt->execute([$tableName]);
    return (int) $stmt->fetchColumn() > 0;
}

function tutorClassesColumnExists(PDO $db, string $tableName, string $columnName): bool {
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

    if (!tutorClassesTableExists($db, 'timetable')) {
        echo json_encode(['rows' => []]);
        exit;
    }

    $requiredColumns = ['id', 'grade', 'day', 'time_slot', 'subject', 'tutor_id'];
    foreach ($requiredColumns as $columnName) {
        if (!tutorClassesColumnExists($db, 'timetable', $columnName)) {
            echo json_encode(['rows' => []]);
            exit;
        }
    }

    $resolvedIds = [];
    $rawTutorId = (int) $inputTutorId;
    $resolvedIds[] = $rawTutorId;

    if (tutorClassesTableExists($db, 'tutors') && tutorClassesColumnExists($db, 'tutors', 'id') && tutorClassesColumnExists($db, 'tutors', 'user_id')) {
        $resolveStmt = $db->prepare("SELECT id, user_id FROM tutors WHERE id = ? OR user_id = ? LIMIT 1");
        $resolveStmt->execute([$rawTutorId, $rawTutorId]);
        $resolvedTutor = $resolveStmt->fetch(PDO::FETCH_ASSOC);
        if ($resolvedTutor) {
            $resolvedIds[] = (int) ($resolvedTutor['id'] ?? 0);
            $resolvedIds[] = (int) ($resolvedTutor['user_id'] ?? 0);
        }
    }

    $resolvedIds = array_values(array_unique(array_filter($resolvedIds)));
    $placeholders = implode(',', array_fill(0, count($resolvedIds), '?'));
    $selectRoom = tutorClassesColumnExists($db, 'timetable', 'room') ? 'COALESCE(room, "N/A") AS room' : '"N/A" AS room';

    $stmt = $db->prepare(" 
        SELECT
            id,
            grade,
            subject,
            day,
            time_slot,
            {$selectRoom}
        FROM timetable
        WHERE tutor_id IN ({$placeholders})
        ORDER BY FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') ASC,
                 STR_TO_DATE(SUBSTRING_INDEX(time_slot, ' - ', 1), '%H:%i') ASC,
                 id ASC
    ");
    $stmt->execute($resolvedIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    echo json_encode([
        'rows' => array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'subject' => (string) ($row['subject'] ?? ''),
                'grade' => (string) ($row['grade'] ?? ''),
                'time_slot' => (string) ($row['time_slot'] ?? ''),
                'day' => (string) ($row['day'] ?? ''),
                'room' => (string) ($row['room'] ?? 'N/A'),
            ];
        }, $rows),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

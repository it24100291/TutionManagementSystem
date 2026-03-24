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

function studentTimetableTableExists(PDO $db, string $tableName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?
    ");
    $stmt->execute([$tableName]);
    return (int) $stmt->fetchColumn() > 0;
}

function studentTimetableColumnExists(PDO $db, string $tableName, string $columnName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
    ");
    $stmt->execute([$tableName, $columnName]);
    return (int) $stmt->fetchColumn() > 0;
}

function resolveStudentGrade(PDO $db, int $userId): ?string {
    if (
        studentTimetableTableExists($db, 'students') &&
        studentTimetableColumnExists($db, 'students', 'user_id') &&
        studentTimetableColumnExists($db, 'students', 'grade')
    ) {
        $stmt = $db->prepare("SELECT grade FROM students WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $grade = $stmt->fetchColumn();
        if ($grade !== false && trim((string) $grade) !== '') {
            return trim((string) $grade);
        }
    }

    if (
        studentTimetableTableExists($db, 'students') &&
        studentTimetableColumnExists($db, 'students', 'id') &&
        studentTimetableColumnExists($db, 'students', 'grade')
    ) {
        $stmt = $db->prepare("SELECT grade FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $grade = $stmt->fetchColumn();
        if ($grade !== false && trim((string) $grade) !== '') {
            return trim((string) $grade);
        }
    }

    return null;
}

try {
    $db = getDB();
    $userId = (int) $inputStudentId;
    $grade = resolveStudentGrade($db, $userId);

    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $timeSlots = [
        '08:00 - 09:00',
        '09:00 - 10:00',
        '10:00 - 11:00',
        '14:00 - 15:00',
        '15:00 - 16:00',
        '16:00 - 17:00',
        '17:00 - 18:00',
    ];
    $dayTimeSlots = [
        'Monday' => ['15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00'],
        'Tuesday' => ['15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00'],
        'Wednesday' => ['15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00'],
        'Thursday' => ['15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00'],
        'Friday' => [],
        'Saturday' => ['08:00 - 09:00', '09:00 - 10:00', '10:00 - 11:00', '14:00 - 15:00', '15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00'],
        'Sunday' => ['08:00 - 09:00', '09:00 - 10:00', '10:00 - 11:00', '14:00 - 15:00', '15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00'],
    ];

    $entries = [];
    $storagePath = __DIR__ . '/../../storage/timetable.json';
    if (file_exists($storagePath)) {
        $decoded = json_decode((string) file_get_contents($storagePath), true);
        if (is_array($decoded) && $grade !== null) {
            foreach ($decoded as $entry) {
                if (
                    is_array($entry) &&
                    (($entry['grade'] ?? '') === $grade)
                ) {
                    $entries[] = [
                        'day' => (string) ($entry['day'] ?? ''),
                        'time' => (string) ($entry['time'] ?? ''),
                        'subject' => (string) ($entry['subject'] ?? ''),
                        'teacher' => (string) ($entry['teacher'] ?? ''),
                        'room' => (string) ($entry['room'] ?? ''),
                    ];
                }
            }
        }
    }

    $result = [
        'grade' => $grade,
        'days' => $days,
        'time_slots' => $timeSlots,
        'day_time_slots' => $dayTimeSlots,
        'entries' => $entries,
    ];

    $shouldUseMock = $grade === null || empty($entries);

    echo json_encode($shouldUseMock ? studentMockTimetable($userId, $grade) : $result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

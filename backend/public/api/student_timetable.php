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

function normalizeStudentTimetableGrade(?string $grade): ?string {
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

function ensureStudentTimetableTable(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS timetable (
            id INT AUTO_INCREMENT PRIMARY KEY,
            grade VARCHAR(50) NOT NULL,
            day VARCHAR(20) NOT NULL,
            time_slot VARCHAR(50) NOT NULL,
            subject VARCHAR(100) NOT NULL,
            tutor_id INT NOT NULL,
            room VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_grade_day_slot (grade, day, time_slot)
        )
    ");

    $count = (int) $db->query("SELECT COUNT(*) FROM timetable")->fetchColumn();
    if ($count > 0) {
        return;
    }

    $legacyPath = __DIR__ . '/../../storage/timetable.json';
    if (!file_exists($legacyPath)) {
        return;
    }

    $decoded = json_decode((string) file_get_contents($legacyPath), true);
    if (!is_array($decoded)) {
        return;
    }

    $stmt = $db->prepare("
        INSERT INTO timetable (grade, day, time_slot, subject, tutor_id, room)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($decoded as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $grade = trim((string) ($entry['grade'] ?? ''));
        $day = trim((string) ($entry['day'] ?? ''));
        $time = trim((string) ($entry['time'] ?? ''));
        $subject = trim((string) ($entry['subject'] ?? ''));
        $tutorId = trim((string) ($entry['teacher_id'] ?? ''));
        $room = trim((string) ($entry['room'] ?? ''));

        if ($grade === '' || $day === '' || $time === '' || $subject === '' || $tutorId === '' || $room === '') {
            continue;
        }

        $stmt->execute([$grade, $day, $time, $subject, (int) $tutorId, $room]);
    }
}

try {
    $db = getDB();
    ensureStudentTimetableTable($db);
    $userId = (int) $inputStudentId;
    $grade = normalizeStudentTimetableGrade(resolveStudentGrade($db, $userId));

    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $timeSlots = [
        '07:00 - 08:00',
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
        'Saturday' => ['07:00 - 08:00', '08:00 - 09:00', '09:00 - 10:00', '10:00 - 11:00', '14:00 - 15:00', '15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00'],
        'Sunday' => ['07:00 - 08:00', '08:00 - 09:00', '09:00 - 10:00', '10:00 - 11:00', '14:00 - 15:00', '15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00'],
    ];

    $entries = [];
    if ($grade !== null) {
        $stmt = $db->prepare("
            SELECT
                tt.day,
                tt.time_slot AS time,
                tt.subject,
                COALESCE(u.full_name, CONCAT('Tutor #', tt.tutor_id)) AS teacher,
                tt.room
            FROM timetable tt
            LEFT JOIN users u ON u.id = tt.tutor_id
            WHERE tt.grade = ?
            ORDER BY FIELD(tt.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                     tt.time_slot
        ");
        $stmt->execute([$grade]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $result = [
        'grade' => $grade,
        'days' => $days,
        'time_slots' => $timeSlots,
        'day_time_slots' => $dayTimeSlots,
        'entries' => $entries,
    ];

    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

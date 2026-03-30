<?php
class TimetableController {
    private array $grades = ['Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11'];
    private array $rooms = ['Hall 1', 'Hall 2', 'Hall 3', 'Hall 4', 'Hall 5', 'Hall 6'];
    private array $legacyTeachers = [
        ['id' => 'mr_perera', 'name' => 'Mr. Perera', 'subject' => 'Mathematics'],
        ['id' => 'ms_silva', 'name' => 'Ms. Silva', 'subject' => 'Science'],
        ['id' => 'mr_fernando', 'name' => 'Mr. Fernando', 'subject' => 'English'],
        ['id' => 'mrs_jayasinghe', 'name' => 'Mrs. Jayasinghe', 'subject' => 'History'],
        ['id' => 'mr_kumar', 'name' => 'Mr. Kumar', 'subject' => 'ICT']
    ];
    private array $defaultSubjects = ['Tamil', 'Mathematics', 'Science', 'Religion', 'English', 'Civics', 'History', 'Geography', 'ICT', 'Health Science', 'Sinhala', 'Commerce'];
    private array $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    private array $allTimeSlots = [
        '07:00 - 08:00',
        '08:00 - 09:00',
        '09:00 - 10:00',
        '10:00 - 11:00',
        '14:00 - 15:00',
        '15:00 - 16:00',
        '16:00 - 17:00',
        '17:00 - 18:00'
    ];
    private array $dayTimeSlots = [
        'Monday' => ['15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00'],
        'Tuesday' => ['15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00'],
        'Wednesday' => ['15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00'],
        'Thursday' => ['15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00'],
        'Friday' => [],
        'Saturday' => ['07:00 - 08:00', '08:00 - 09:00', '09:00 - 10:00', '10:00 - 11:00', '14:00 - 15:00', '15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00'],
        'Sunday' => ['07:00 - 08:00', '08:00 - 09:00', '09:00 - 10:00', '10:00 - 11:00', '14:00 - 15:00', '15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00']
    ];

    private function ensureTimetableTable(): void {
        $db = getDB();
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
        $this->migrateLegacyStorageIfNeeded($db);
    }

    private function migrateLegacyStorageIfNeeded(PDO $db): void {
        $count = (int) $db->query("SELECT COUNT(*) FROM timetable")->fetchColumn();
        if ($count > 0) {
            return;
        }

        $path = $this->storagePath();
        if (!file_exists($path)) {
            return;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return;
        }

        $entries = $this->sanitizeEntries($decoded);
        if (empty($entries)) {
            return;
        }

        $stmt = $db->prepare("
            INSERT INTO timetable (grade, day, time_slot, subject, tutor_id, room)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($entries as $entry) {
            $stmt->execute([
                $entry['grade'],
                $entry['day'],
                $entry['time'],
                $entry['subject'],
                (int) $entry['teacher_id'],
                $entry['room'],
            ]);
        }
    }

    public function getTimetable() {
        $this->ensureTimetableTable();
        $teachers = $this->getTeachers();
        $subjects = $this->getSubjects();
        $entries = $this->withDerivedTimes($this->loadEntries());
        $selectedGrade = isset($_GET['grade']) && in_array($_GET['grade'], $this->grades, true) ? $_GET['grade'] : $this->grades[0];
        $selectedTeacher = isset($_GET['teacher']) ? trim($_GET['teacher']) : '';

        $filteredEntries = array_values(array_filter($entries, function ($entry) use ($selectedGrade, $selectedTeacher) {
            if ($entry['grade'] !== $selectedGrade) {
                return false;
            }

            if ($selectedTeacher !== '' && $entry['teacher_id'] !== $selectedTeacher) {
                return false;
            }

            return true;
        }));

        Response::success([
            'grades' => $this->grades,
            'teachers' => $teachers,
            'subjects' => $subjects,
            'rooms' => $this->rooms,
            'days' => $this->days,
            'time_slots' => $this->allTimeSlots,
            'day_time_slots' => $this->dayTimeSlots,
            'selected_grade' => $selectedGrade,
            'selected_teacher' => $selectedTeacher,
            'entries' => $filteredEntries
        ]);
    }

    public function saveCell() {
        $this->ensureTimetableTable();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            Response::error('Invalid payload');
        }

        $grade = isset($data['grade']) ? trim($data['grade']) : '';
        $day = isset($data['day']) ? trim($data['day']) : '';
        $time = isset($data['time']) ? trim($data['time']) : '';
        $teacherId = isset($data['teacher_id']) ? trim($data['teacher_id']) : '';
        $subject = isset($data['subject']) ? trim($data['subject']) : '';
        $room = isset($data['room']) ? trim($data['room']) : '';
        $originalDay = isset($data['original_day']) ? trim($data['original_day']) : $day;
        $originalTime = isset($data['original_time']) ? trim($data['original_time']) : $time;

        if (!in_array($grade, $this->grades, true)) {
            Response::error('Invalid grade selected');
        }

        if (!in_array($day, $this->days, true) || !in_array($time, $this->allTimeSlots, true)) {
            Response::error('Invalid timetable slot selected');
        }

        if ($teacherId === '' && $subject === '' && $room === '') {
            $this->deleteCell($grade, $day, $time);
            Response::success(['message' => 'Timetable cell cleared']);
        }

        $teacher = $this->findTeacherById($teacherId);
        if (!$teacher) {
            Response::error('Invalid teacher selected');
        }

        if ($subject === '' || !in_array($subject, $this->getSubjects(), true)) {
            Response::error('Invalid subject selected');
        }

        if ($room === '' || !in_array($room, $this->rooms, true)) {
            Response::error('Invalid room selected');
        }

        $entries = $this->loadEntries();
        $targetIndex = $this->findEntryIndex($entries, $grade, $day, $time);
        $originalIndex = $this->findEntryIndex($entries, $grade, $originalDay, $originalTime);
        $teacherConflict = $this->findTeacherConflict($entries, $teacherId, $day, $time, $grade, $originalDay, $originalTime);
        $roomConflict = $this->findRoomConflict($entries, $room, $day, $time, $grade, $originalDay, $originalTime);

        if ($targetIndex !== null && $targetIndex !== $originalIndex) {
            Response::error('This class already has a subject in the selected time slot');
        }

        if ($teacherConflict !== null) {
            Response::error(sprintf(
                '%s is already assigned to %s on %s at %s',
                $teacher['name'],
                $teacherConflict['grade'],
                $day,
                $time
            ));
        }

        if ($roomConflict !== null) {
            Response::error(sprintf(
                '%s is already assigned to %s on %s at %s',
                $room,
                $roomConflict['grade'],
                $day,
                $time
            ));
        }

        $payload = [
            'grade' => $grade,
            'teacher_id' => $teacherId,
            'teacher' => $teacher['name'],
            'subject' => $subject,
            'day' => $day,
            'time' => $time,
            'room' => $room
        ];

        if ($originalIndex !== null) {
            $entries[$originalIndex] = $payload;
        } elseif ($targetIndex !== null) {
            $entries[$targetIndex] = $payload;
        } else {
            $entries[] = $payload;
        }

        $this->saveEntries($entries);
        Response::success(['message' => 'Timetable updated']);
    }

    private function deleteCell(string $grade, string $day, string $time): void {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM timetable WHERE grade = ? AND day = ? AND time_slot = ?");
        $stmt->execute([$grade, $day, $time]);
    }

    private function findEntryIndex(array $entries, string $grade, string $day, string $time): ?int {
        foreach ($entries as $index => $entry) {
            if ($entry['grade'] === $grade && $entry['day'] === $day && $entry['time'] === $time) {
                return $index;
            }
        }

        return null;
    }

    private function findTeacherConflict(
        array $entries,
        string $teacherId,
        string $day,
        string $time,
        string $grade,
        string $originalDay,
        string $originalTime
    ): ?array {
        foreach ($entries as $entry) {
            if (($entry['teacher_id'] ?? '') !== $teacherId) {
                continue;
            }

            if (($entry['day'] ?? '') !== $day || ($entry['time'] ?? '') !== $time) {
                continue;
            }

            $isSameOriginalCell =
                ($entry['grade'] ?? '') === $grade &&
                ($entry['day'] ?? '') === $originalDay &&
                ($entry['time'] ?? '') === $originalTime;

            if ($isSameOriginalCell) {
                continue;
            }

            return $entry;
        }

        return null;
    }

    private function findRoomConflict(
        array $entries,
        string $room,
        string $day,
        string $time,
        string $grade,
        string $originalDay,
        string $originalTime
    ): ?array {
        foreach ($entries as $entry) {
            if (($entry['room'] ?? '') !== $room) {
                continue;
            }

            if (($entry['day'] ?? '') !== $day || ($entry['time'] ?? '') !== $time) {
                continue;
            }

            $isSameOriginalCell =
                ($entry['grade'] ?? '') === $grade &&
                ($entry['day'] ?? '') === $originalDay &&
                ($entry['time'] ?? '') === $originalTime;

            if ($isSameOriginalCell) {
                continue;
            }

            return $entry;
        }

        return null;
    }

    private function isValidSlotForDay(string $day, string $time): bool {
        return isset($this->dayTimeSlots[$day]) && in_array($time, $this->dayTimeSlots[$day], true);
    }

    private function findTeacherById(string $teacherId): ?array {
        foreach ($this->getTeachers() as $teacher) {
            if ($teacher['id'] === $teacherId) {
                return $teacher;
            }
        }
        return null;
    }

    private function getTeachers(): array {
        try {
            $db = getDB();
            $stmt = $db->query("
                SELECT
                    t.user_id AS id,
                    u.full_name AS name,
                    t.subject
                FROM tutors t
                INNER JOIN users u ON u.id = t.user_id
                WHERE COALESCE(u.status, 'ACTIVE') IN ('ACTIVE', 'Approved', 'APPROVED')
                ORDER BY u.full_name ASC
            ");

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                return array_map(static function ($row) {
                    return [
                        'id' => (string) $row['id'],
                        'name' => trim((string) ($row['name'] ?? 'Tutor')),
                        'subject' => trim((string) ($row['subject'] ?? ''))
                    ];
                }, $rows);
            }
        } catch (Throwable $e) {
            // Fall back to legacy teachers if DB tutors are not available.
        }

        return $this->legacyTeachers;
    }

    private function getSubjects(): array {
        $subjects = $this->defaultSubjects;
        foreach ($this->getTeachers() as $teacher) {
            $subject = trim((string) ($teacher['subject'] ?? ''));
            if ($subject !== '') {
                $subjects[] = $subject;
            }
        }

        $subjects = array_values(array_unique($subjects));
        sort($subjects);
        return $subjects;
    }

    private function storagePath(): string {
        return __DIR__ . '/../../storage/timetable.json';
    }

    private function loadEntries(): array {
        $this->ensureTimetableTable();
        $db = getDB();
        $stmt = $db->query("
            SELECT
                grade,
                day,
                time_slot AS time,
                subject,
                tutor_id AS teacher_id,
                room
            FROM timetable
        ");

        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_values($this->sanitizeEntries($entries));
    }

    private function sanitizeEntries(array $entries): array {
        $valid = [];

        foreach ($entries as $entry) {
            $grade = isset($entry['grade']) ? trim((string) $entry['grade']) : '';
            $day = isset($entry['day']) ? trim((string) $entry['day']) : '';
            $time = isset($entry['time']) ? trim((string) $entry['time']) : '';
            $teacherId = isset($entry['teacher_id']) ? trim((string) $entry['teacher_id']) : '';
            $subject = isset($entry['subject']) ? trim((string) $entry['subject']) : '';
            $room = isset($entry['room']) ? trim((string) $entry['room']) : '';

            $teacher = $this->findTeacherById($teacherId);
            if (
                !in_array($grade, $this->grades, true) ||
                !in_array($day, $this->days, true) ||
                !$this->isValidSlotForDay($day, $time) ||
                !$teacher ||
                !in_array($subject, $this->getSubjects(), true) ||
                !in_array($room, $this->rooms, true)
            ) {
                continue;
            }

            $key = $grade . '|' . $day . '|' . $time;
            $valid[$key] = [
                'grade' => $grade,
                'teacher_id' => $teacherId,
                'teacher' => $teacher['name'],
                'subject' => $subject,
                'day' => $day,
                'time' => $time,
                'room' => $room
            ];
        }

        return array_values($valid);
    }

    private function saveEntries(array $entries): void {
        $this->ensureTimetableTable();
        $db = getDB();
        $db->beginTransaction();

        try {
            $db->exec("DELETE FROM timetable");
            $stmt = $db->prepare("
                INSERT INTO timetable (grade, day, time_slot, subject, tutor_id, room)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($entries as $entry) {
                $stmt->execute([
                    $entry['grade'],
                    $entry['day'],
                    $entry['time'],
                    $entry['subject'],
                    (int) $entry['teacher_id'],
                    $entry['room'],
                ]);
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private function defaultEntries(): array {
        return [];
    }

    private function withDerivedTimes(array $entries): array {
        return array_map(function ($entry) {
            [$startTime, $endTime] = array_map('trim', explode('-', $entry['time']));
            $entry['start_time'] = $startTime;
            $entry['end_time'] = $endTime;
            return $entry;
        }, $entries);
    }
}



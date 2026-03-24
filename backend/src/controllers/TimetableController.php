<?php
class TimetableController {
    private array $grades = ['Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11'];
    private array $rooms = ['Hall 1', 'Hall 2', 'Hall 3', 'Hall 4', 'Hall 5', 'Hall 6'];
    private array $teachers = [
        ['id' => 'mr_perera', 'name' => 'Mr. Perera', 'subject' => 'Mathematics'],
        ['id' => 'ms_silva', 'name' => 'Ms. Silva', 'subject' => 'Science'],
        ['id' => 'mr_fernando', 'name' => 'Mr. Fernando', 'subject' => 'English'],
        ['id' => 'mrs_jayasinghe', 'name' => 'Mrs. Jayasinghe', 'subject' => 'History'],
        ['id' => 'mr_kumar', 'name' => 'Mr. Kumar', 'subject' => 'ICT']
    ];
    private array $subjects = ['Mathematics', 'Science', 'English', 'History', 'ICT', 'Commerce', 'Art'];
    private array $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    private array $allTimeSlots = [
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
        'Saturday' => ['08:00 - 09:00', '09:00 - 10:00', '10:00 - 11:00', '14:00 - 15:00', '15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00'],
        'Sunday' => ['08:00 - 09:00', '09:00 - 10:00', '10:00 - 11:00', '14:00 - 15:00', '15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00']
    ];

    public function getTimetable() {
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
            'teachers' => $this->teachers,
            'subjects' => $this->subjects,
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

        if (!in_array($day, $this->days, true) || !$this->isValidSlotForDay($day, $time)) {
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

        if ($subject === '' || !in_array($subject, $this->subjects, true)) {
            Response::error('Invalid subject selected');
        }

        if ($room === '' || !in_array($room, $this->rooms, true)) {
            Response::error('Invalid room selected');
        }

        $entries = $this->loadEntries();
        $targetIndex = $this->findEntryIndex($entries, $grade, $day, $time);
        $originalIndex = $this->findEntryIndex($entries, $grade, $originalDay, $originalTime);

        if ($targetIndex !== null && $targetIndex !== $originalIndex) {
            Response::error('This class already has a subject in the selected time slot');
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
        $entries = array_values(array_filter($this->loadEntries(), function ($entry) use ($grade, $day, $time) {
            return !($entry['grade'] === $grade && $entry['day'] === $day && $entry['time'] === $time);
        }));
        $this->saveEntries($entries);
    }

    private function findEntryIndex(array $entries, string $grade, string $day, string $time): ?int {
        foreach ($entries as $index => $entry) {
            if ($entry['grade'] === $grade && $entry['day'] === $day && $entry['time'] === $time) {
                return $index;
            }
        }

        return null;
    }

    private function isValidSlotForDay(string $day, string $time): bool {
        return isset($this->dayTimeSlots[$day]) && in_array($time, $this->dayTimeSlots[$day], true);
    }

    private function findTeacherById(string $teacherId): ?array {
        foreach ($this->teachers as $teacher) {
            if ($teacher['id'] === $teacherId) {
                return $teacher;
            }
        }
        return null;
    }

    private function storagePath(): string {
        return __DIR__ . '/../../storage/timetable.json';
    }

    private function loadEntries(): array {
        $path = $this->storagePath();
        if (!file_exists($path)) {
            $this->saveEntries($this->defaultEntries());
        }

        $contents = file_get_contents($path);
        $entries = json_decode($contents, true);
        if (!is_array($entries)) {
            $entries = $this->defaultEntries();
        }

        $entries = $this->sanitizeEntries($entries);
        if (count($entries) === 0) {
            $entries = $this->defaultEntries();
            $this->saveEntries($entries);
        }

        return array_values($entries);
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
                !in_array($subject, $this->subjects, true) ||
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
        $path = $this->storagePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, json_encode(array_values($entries), JSON_PRETTY_PRINT));
    }

    private function defaultEntries(): array {
        return [
            ['grade' => 'Grade 6', 'teacher_id' => 'mr_perera', 'teacher' => 'Mr. Perera', 'subject' => 'Mathematics', 'day' => 'Monday', 'time' => '15:00 - 16:00', 'room' => 'Hall 1'],
            ['grade' => 'Grade 6', 'teacher_id' => 'ms_silva', 'teacher' => 'Ms. Silva', 'subject' => 'Science', 'day' => 'Monday', 'time' => '16:00 - 17:00', 'room' => 'Hall 2'],
            ['grade' => 'Grade 6', 'teacher_id' => 'mr_fernando', 'teacher' => 'Mr. Fernando', 'subject' => 'English', 'day' => 'Tuesday', 'time' => '17:00 - 18:00', 'room' => 'Hall 3'],
            ['grade' => 'Grade 7', 'teacher_id' => 'mrs_jayasinghe', 'teacher' => 'Mrs. Jayasinghe', 'subject' => 'History', 'day' => 'Wednesday', 'time' => '15:00 - 16:00', 'room' => 'Hall 4'],
            ['grade' => 'Grade 8', 'teacher_id' => 'mr_kumar', 'teacher' => 'Mr. Kumar', 'subject' => 'ICT', 'day' => 'Thursday', 'time' => '16:00 - 17:00', 'room' => 'Hall 5'],
            ['grade' => 'Grade 8', 'teacher_id' => 'mr_fernando', 'teacher' => 'Mr. Fernando', 'subject' => 'English', 'day' => 'Saturday', 'time' => '08:00 - 09:00', 'room' => 'Hall 1'],
            ['grade' => 'Grade 9', 'teacher_id' => 'ms_silva', 'teacher' => 'Ms. Silva', 'subject' => 'Science', 'day' => 'Saturday', 'time' => '14:00 - 15:00', 'room' => 'Hall 6'],
            ['grade' => 'Grade 10', 'teacher_id' => 'mr_perera', 'teacher' => 'Mr. Perera', 'subject' => 'Mathematics', 'day' => 'Sunday', 'time' => '17:00 - 18:00', 'room' => 'Hall 2']
        ];
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

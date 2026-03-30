<?php

class ExaminationController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDB();
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS exams (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                exam_name VARCHAR(255) NOT NULL,
                exam_date DATE NOT NULL,
                grade VARCHAR(50) NOT NULL,
                term VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS exam_subjects (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                exam_id INT UNSIGNED NOT NULL,
                subject VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_exam_subjects_exam
                    FOREIGN KEY (exam_id) REFERENCES exams(id)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                UNIQUE KEY uniq_exam_subjects_exam_subject (exam_id, subject)
            )
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS exam_marks (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                exam_id INT UNSIGNED NOT NULL,
                student_id INT UNSIGNED NOT NULL,
                subject VARCHAR(100) NOT NULL,
                marks DECIMAL(5,2) NOT NULL,
                tutor_id INT UNSIGNED NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_exam_marks_exam
                    FOREIGN KEY (exam_id) REFERENCES exams(id)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT fk_exam_marks_student
                    FOREIGN KEY (student_id) REFERENCES students(id)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT fk_exam_marks_tutor
                    FOREIGN KEY (tutor_id) REFERENCES tutors(id)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE,
                UNIQUE KEY uniq_exam_marks_exam_student_subject (exam_id, student_id, subject)
            )
        ");
    }

    public function getAdminExams(): void
    {
        $stmt = $this->db->query("
            SELECT
                e.id,
                e.exam_name,
                e.exam_date,
                e.grade,
                e.term,
                COALESCE(GROUP_CONCAT(es.subject ORDER BY es.subject SEPARATOR ','), '') AS subjects
            FROM exams e
            LEFT JOIN exam_subjects es ON es.exam_id = e.id
            GROUP BY e.id, e.exam_name, e.exam_date, e.grade, e.term
            ORDER BY e.exam_date DESC, e.id DESC
        ");

        $rows = array_map(function ($row) {
            $subjects = trim((string) ($row['subjects'] ?? ''));
            $row['subjects'] = $subjects === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $subjects))));
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));

        Response::success($rows);
    }

    public function createExam(): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $examName = trim((string) ($data['exam_name'] ?? ''));
        $examDate = trim((string) ($data['exam_date'] ?? ''));
        $grade = trim((string) ($data['grade'] ?? ''));
        $term = trim((string) ($data['term'] ?? ''));
        $subjects = isset($data['subjects']) && is_array($data['subjects']) ? $data['subjects'] : [];

        if ($examName === '' || $examDate === '' || $grade === '' || $term === '') {
            Response::error('Please fill all examination fields.');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $examDate)) {
            Response::error('Invalid examination date.');
        }

        $subjects = array_values(array_unique(array_filter(array_map(
            static fn ($subject) => trim((string) $subject),
            $subjects
        ))));

        if (empty($subjects)) {
            Response::error('Please assign at least one subject to the examination.');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO exams (exam_name, exam_date, grade, term)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$examName, $examDate, $grade, $term]);
            $examId = (int) $this->db->lastInsertId();

            $subjectStmt = $this->db->prepare("
                INSERT INTO exam_subjects (exam_id, subject)
                VALUES (?, ?)
            ");

            foreach ($subjects as $subject) {
                $subjectStmt->execute([$examId, $subject]);
            }

            $this->db->commit();
            Response::success(['message' => 'Examination created successfully.'], 201);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Response::error($e->getMessage(), 500);
        }
    }

    public function getAdminResults(): void
    {
        $examId = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0;
        if ($examId <= 0) {
            Response::error('Invalid exam_id.');
        }

        $exam = $this->getExamById($examId);
        if (!$exam) {
            Response::error('Examination not found.', 404);
        }

        $subjects = $this->getExamSubjects($examId);
        $students = $this->getStudentsForExamGrade((string) $exam['grade']);
        $marks = $this->getMarksForExam($examId);

        $classMap = [];
        foreach ($students as $student) {
            $className = trim((string) ($student['class_name'] ?? '')) ?: 'Unassigned Class';
            if (!isset($classMap[$className])) {
                $classMap[$className] = [];
            }

            $studentMarks = [];
            foreach ($subjects as $subject) {
                $studentMarks[$subject] = $marks[$student['id']][$subject] ?? '';
            }

            $classMap[$className][] = [
                'student_id' => (int) $student['id'],
                'student_name' => $student['student_name'],
                'marks' => $studentMarks,
            ];
        }

        $classes = [];
        foreach ($classMap as $className => $rows) {
            usort($rows, static fn ($a, $b) => strcmp($a['student_name'], $b['student_name']));
            $classes[] = [
                'class_name' => $className,
                'rows' => $rows,
            ];
        }

        usort($classes, static fn ($a, $b) => strcmp($a['class_name'], $b['class_name']));

        Response::success([
            'exam' => $exam,
            'subjects' => $subjects,
            'classes' => $classes,
        ]);
    }

    public function getTutorOptions(): void
    {
        $tutor = $this->resolveTutorContext();
        $stmt = $this->db->prepare("
            SELECT
                e.id,
                e.exam_name,
                e.exam_date,
                e.grade,
                e.term
            FROM exams e
            JOIN exam_subjects es ON es.exam_id = e.id
            WHERE es.subject = ?
            ORDER BY e.exam_date DESC, e.id DESC
        ");
        $stmt->execute([$tutor['subject']]);
        $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success([
            'subject' => $tutor['subject'],
            'exams' => $exams,
        ]);
    }

    public function getTutorStudents(): void
    {
        $tutor = $this->resolveTutorContext();
        $examId = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0;
        $selectedClass = trim((string) ($_GET['class_name'] ?? ''));

        if ($examId <= 0) {
            Response::error('Invalid exam_id.');
        }

        $exam = $this->getExamById($examId);
        if (!$exam) {
            Response::error('Examination not found.', 404);
        }

        $this->ensureTutorSubjectAllowedForExam($examId, $tutor['subject']);
        $students = $this->getStudentsForExamGrade((string) $exam['grade']);
        $marks = $this->getMarksForExamAndSubject($examId, $tutor['subject']);

        $availableClasses = [];
        $rows = [];
        foreach ($students as $student) {
            $className = trim((string) ($student['class_name'] ?? '')) ?: 'Unassigned Class';
            $availableClasses[$className] = true;

            if ($selectedClass !== '' && $selectedClass !== $className) {
                continue;
            }

            $rows[] = [
                'student_id' => (int) $student['id'],
                'student_name' => $student['student_name'],
                'class_name' => $className,
                'marks' => $marks[(int) $student['id']] ?? '',
            ];
        }

        usort($rows, static fn ($a, $b) => strcmp($a['student_name'], $b['student_name']));
        $classes = array_keys($availableClasses);
        sort($classes);

        Response::success([
            'exam' => $exam,
            'subject' => $tutor['subject'],
            'available_classes' => $classes,
            'students' => $rows,
        ]);
    }


    public function getTutorGradeStudents(): void
    {
        $this->resolveTutorContext();
        $grade = trim((string) ($_GET['grade'] ?? ''));

        if ($grade === '') {
            Response::error('Invalid grade.');
        }

        $students = $this->getStudentsForExamGrade($grade);
        $availableClasses = [];
        $rows = [];

        foreach ($students as $student) {
            $className = trim((string) ($student['class_name'] ?? '')) ?: 'Unassigned Class';
            $availableClasses[$className] = true;
            $rows[] = [
                'student_id' => (int) $student['id'],
                'student_name' => $student['student_name'],
                'class_name' => $className,
                'marks' => '',
            ];
        }

        usort($rows, static fn ($a, $b) => strcmp($a['student_name'], $b['student_name']));
        $classes = array_keys($availableClasses);
        sort($classes);

        Response::success([
            'grade' => $grade,
            'available_classes' => $classes,
            'students' => $rows,
        ]);
    }
    public function saveTutorMarks(): void
    {
        $tutor = $this->resolveTutorContext();
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $examId = (int) ($data['exam_id'] ?? 0);
        $marks = isset($data['marks']) && is_array($data['marks']) ? $data['marks'] : [];

        if ($examId <= 0) {
            Response::error('Invalid exam_id.');
        }
        if (empty($marks)) {
            Response::error('Please enter marks before saving.');
        }

        $exam = $this->getExamById($examId);
        if (!$exam) {
            Response::error('Examination not found.', 404);
        }

        $this->ensureTutorSubjectAllowedForExam($examId, $tutor['subject']);
        $allowedStudents = $this->getStudentIdSetForGrade((string) $exam['grade']);
        if (empty($allowedStudents)) {
            Response::error('No students found for this examination grade.');
        }

        $stmt = $this->db->prepare("
            INSERT INTO exam_marks (exam_id, student_id, subject, marks, tutor_id)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                marks = VALUES(marks),
                tutor_id = VALUES(tutor_id),
                updated_at = CURRENT_TIMESTAMP
        ");

        $this->db->beginTransaction();
        try {
            foreach ($marks as $row) {
                $studentId = (int) ($row['student_id'] ?? 0);
                $value = trim((string) ($row['marks'] ?? ''));

                if ($studentId <= 0 || !isset($allowedStudents[$studentId])) {
                    Response::error('Unauthorized student selection.');
                }
                if ($value === '' || !is_numeric($value)) {
                    Response::error('Marks must be numeric.');
                }

                $numericMarks = (float) $value;
                if ($numericMarks < 0 || $numericMarks > 100) {
                    Response::error('Marks must be between 0 and 100.');
                }

                $stmt->execute([$examId, $studentId, $tutor['subject'], $numericMarks, $tutor['tutor_id']]);
            }

            $this->db->commit();
            Response::success(['message' => 'Marks saved successfully.']);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Response::error($e->getMessage(), 500);
        }
    }

    private function resolveTutorContext(): array
    {
        $inputTutorId = isset($_GET['tutor_id']) ? trim((string) $_GET['tutor_id']) : '';
        if ($inputTutorId === '' && isset($_SESSION['user']['id'])) {
            $inputTutorId = trim((string) $_SESSION['user']['id']);
        }

        if ($inputTutorId === '' || !ctype_digit($inputTutorId)) {
            Response::error('Invalid tutor_id.');
        }

        $stmt = $this->db->prepare("
            SELECT t.id AS tutor_id, t.user_id, t.subject, u.full_name
            FROM tutors t
            LEFT JOIN users u ON u.id = t.user_id
            WHERE t.user_id = ? OR t.id = ?
            ORDER BY CASE WHEN t.user_id = ? THEN 0 ELSE 1 END
            LIMIT 1
        ");
        $id = (int) $inputTutorId;
        $stmt->execute([$id, $id, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            Response::error('Tutor profile not found.', 404);
        }

        return [
            'tutor_id' => (int) $row['tutor_id'],
            'user_id' => (int) $row['user_id'],
            'subject' => trim((string) ($row['subject'] ?? '')),
            'full_name' => trim((string) ($row['full_name'] ?? 'Tutor')),
        ];
    }

    private function getExamById(int $examId): ?array
    {
        $stmt = $this->db->prepare("SELECT id, exam_name, exam_date, grade, term FROM exams WHERE id = ? LIMIT 1");
        $stmt->execute([$examId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function getExamSubjects(int $examId): array
    {
        $stmt = $this->db->prepare("SELECT subject FROM exam_subjects WHERE exam_id = ? ORDER BY subject ASC");
        $stmt->execute([$examId]);
        return array_map(static fn ($row) => (string) $row['subject'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function ensureTutorSubjectAllowedForExam(int $examId, string $subject): void
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM exam_subjects WHERE exam_id = ? AND subject = ?");
        $stmt->execute([$examId, $subject]);
        if ((int) $stmt->fetchColumn() === 0) {
            Response::error('You are not allowed to enter marks for this examination.', 403);
        }
    }

    private function getStudentsForExamGrade(string $grade): array
    {
        $variants = $this->buildGradeVariants($grade);
        $placeholders = implode(',', array_fill(0, count($variants), '?'));

        $stmt = $this->db->prepare("
            SELECT
                s.id,
                COALESCE(u.full_name, CONCAT('Student #', s.id)) AS student_name,
                COALESCE(NULLIF(s.school_name, ''), 'Unassigned Class') AS class_name,
                s.grade
            FROM students s
            LEFT JOIN users u ON u.id = s.user_id
            WHERE s.grade IN ({$placeholders})
            ORDER BY class_name ASC, student_name ASC
        ");
        $stmt->execute($variants);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getStudentIdSetForGrade(string $grade): array
    {
        $students = $this->getStudentsForExamGrade($grade);
        $result = [];
        foreach ($students as $student) {
            $result[(int) $student['id']] = true;
        }
        return $result;
    }

    private function getMarksForExam(int $examId): array
    {
        $stmt = $this->db->prepare("
            SELECT student_id, subject, marks
            FROM exam_marks
            WHERE exam_id = ?
        ");
        $stmt->execute([$examId]);

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(int) $row['student_id']][(string) $row['subject']] = (string) $row['marks'];
        }
        return $result;
    }

    private function getMarksForExamAndSubject(int $examId, string $subject): array
    {
        $stmt = $this->db->prepare("
            SELECT student_id, marks
            FROM exam_marks
            WHERE exam_id = ? AND subject = ?
        ");
        $stmt->execute([$examId, $subject]);

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(int) $row['student_id']] = (string) $row['marks'];
        }
        return $result;
    }

    private function buildGradeVariants(string $grade): array
    {
        $value = trim($grade);
        $stripped = preg_replace('/^Grade\s+/i', '', $value);
        $variants = array_values(array_unique(array_filter([$value, $stripped, 'Grade ' . $stripped])));
        return $variants;
    }
}


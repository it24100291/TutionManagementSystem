<?php
class DashboardController {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function getSummary() {
        Response::success([
            'totalStudents' => $this->countRows('students'),
            'totalTutors' => $this->countRows('tutors'),
            'totalClasses' => $this->countRows('classes'),
            'totalIncome' => $this->sumPayments(),
            'pendingPayments' => $this->countPendingPayments(),
            'activeUsers' => $this->countActiveUsers(),
            'salaryDetails' => $this->sumSalaryPayments(),
            'attendanceRecords' => $this->countAttendanceRecords(),
        ]);
    }

    public function getDetail($metric) {
        $allowedMetrics = [
            'students',
            'tutors',
            'classes',
            'paid-payments',
            'pending-payments',
            'student-payment-details',
            'active-users',
            'salary-details',
            'attendance-records',
        ];

        if (!in_array($metric, $allowedMetrics, true)) {
            Response::error('Dashboard detail not found', 404);
        }

        Response::success($this->fetchMetricRows($metric));
    }

    public function createDetail($metric) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        return match ($metric) {
            'classes' => $this->createClass($data),
            'paid-payments', 'pending-payments' => $this->createPayment($data),
            default => Response::error('Create is not available for this list', 400),
        };
    }

    public function updateDetail($metric, $id) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        return match ($metric) {
            'students' => $this->updateStudent($id, $data),
            'tutors' => $this->updateTutor($id, $data),
            'classes' => $this->updateClass($id, $data),
            'paid-payments', 'pending-payments' => $this->updatePayment($id, $data),
            'student-payment-details' => $this->updatePayment($id, $data),
            'active-users' => $this->updateActiveUser($id, $data),
            'salary-details' => $this->updateSalaryDetail($id, $data),
            default => Response::error('Update is not available for this list', 400),
        };
    }

    public function deleteDetail($metric, $id) {
        return match ($metric) {
            'students', 'tutors', 'active-users' => $this->deleteUser($id),
            'classes' => $this->deleteFromTable('classes', $id),
            'paid-payments', 'pending-payments' => $this->deleteFromTable('payments', $id),
            default => Response::error('Delete is not available for this list', 400),
        };
    }

    private function tableExists($tableName) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS table_count
            FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ?
        ");
        $stmt->execute([$tableName]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function countRows($tableName) {
        if (!$this->tableExists($tableName)) {
            return 0;
        }

        return (int) $this->db->query("SELECT COUNT(*) FROM {$tableName}")->fetchColumn();
    }

    private function columnExists($tableName, $columnName) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS column_count
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
        ");
        $stmt->execute([$tableName, $columnName]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function sumPayments() {
        if (!$this->tableExists('payments')) {
            return 0;
        }

        $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'Paid'");
        $stmt->execute();
        return (float) $stmt->fetchColumn();
    }

    private function countPendingPayments() {
        if (!$this->tableExists('payments')) {
            return 0;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM payments WHERE status = 'Unpaid'");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    private function countActiveUsers() {
        if (!$this->tableExists('users')) {
            return 0;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE status = 'ACTIVE'");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    private function sumSalaryPayments() {
        if (!$this->tableExists('salary_payments')) {
            return 0;
        }

        $statusFilter = $this->columnExists('salary_payments', 'status')
            ? " WHERE LOWER(status) = 'paid'"
            : '';

        $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) FROM salary_payments{$statusFilter}");
        $stmt->execute();
        return (float) $stmt->fetchColumn();
    }

    private function countAttendanceRecords() {
        if (!$this->tableExists('attendance')) {
            return 0;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM attendance");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    private function fetchMetricRows($metric) {
        $selectedMonth = isset($_GET['month']) ? max(1, min(12, (int) $_GET['month'])) : (int) date('m');
        $selectedYear = isset($_GET['year']) ? max(2000, min(2100, (int) $_GET['year'])) : (int) date('Y');

        return match ($metric) {
            'students' => $this->fetchStudents(),
            'tutors' => $this->fetchTutors(),
            'classes' => $this->fetchClasses(),
            'paid-payments' => $this->fetchPaymentsByStatus('Paid'),
            'pending-payments' => $this->fetchPaymentsByStatus('Unpaid'),
            'student-payment-details' => $this->fetchAllStudentPayments(),
            'active-users' => $this->fetchActiveUsers(),
            'salary-details' => $this->fetchSalaryDetails($selectedMonth, $selectedYear),
            'attendance-records' => $this->fetchAttendanceRecords(),
            default => [],
        };
    }

    private function fetchStudents() {
        if (!$this->tableExists('students')) {
            return [];
        }

        $stmt = $this->db->query("
            SELECT
                u.id,
                u.full_name,
                u.email,
                u.phone,
                u.status,
                u.created_at,
                s.school_name,
                s.grade,
                s.guardian_name
            FROM students s
            JOIN users u ON u.id = s.user_id
            ORDER BY u.created_at DESC
        ");

        return $stmt->fetchAll();
    }

    private function fetchTutors() {
        if (!$this->tableExists('tutors')) {
            return [];
        }

        $stmt = $this->db->query("
            SELECT
                u.id,
                u.full_name,
                u.email,
                u.phone,
                u.status,
                u.created_at,
                t.nic_number,
                t.subject
            FROM tutors t
            JOIN users u ON u.id = t.user_id
            ORDER BY u.created_at DESC
        ");

        return $stmt->fetchAll();
    }

    private function fetchClasses() {
        if (!$this->tableExists('classes')) {
            return [];
        }

        $stmt = $this->db->query("SELECT * FROM classes ORDER BY id DESC");
        return $stmt->fetchAll();
    }

    private function fetchPaymentsByStatus($status) {
        return array_values(array_filter(
            $this->fetchAllStudentPayments(),
            static fn ($row) => strcasecmp((string) ($row['status'] ?? ''), (string) $status) === 0
        ));
    }

    private function fetchAllStudentPayments() {
        if (!$this->tableExists('payments')) {
            return [];
        }

        $stmt = $this->db->query("SELECT * FROM payments ORDER BY id DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(function ($row) {
            $userId = isset($row['user_id']) ? (int) $row['user_id'] : 0;
            $studentId = isset($row['student_id']) ? (int) $row['student_id'] : 0;
            $fullName = '';
            $grade = '';

            if (
                $userId > 0 &&
                $this->tableExists('users') &&
                $this->tableExists('students') &&
                $this->columnExists('students', 'user_id')
            ) {
                $userStmt = $this->db->prepare("
                    SELECT u.full_name, s.grade
                    FROM users u
                    LEFT JOIN students s ON s.user_id = u.id
                    WHERE u.id = ?
                    LIMIT 1
                ");
                $userStmt->execute([$userId]);
                $userRow = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $fullName = (string) ($userRow['full_name'] ?? '');
                $grade = (string) ($userRow['grade'] ?? '');
            }

            if ($fullName === '' && $studentId > 0 && $this->tableExists('students') && $this->tableExists('users') && $this->columnExists('students', 'user_id')) {
                $studentStmt = $this->db->prepare("SELECT u.full_name, s.grade FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = ? LIMIT 1");
                $studentStmt->execute([$studentId]);
                $studentRow = $studentStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $fullName = (string) ($studentRow['full_name'] ?? '');
                $grade = (string) ($studentRow['grade'] ?? '');
            }

            $paymentMonth = (string) ($row['payment_month'] ?? $row['month'] ?? '');
            if ($paymentMonth !== '' && preg_match('/^\d{4}-\d{2}$/', $paymentMonth)) {
                $date = DateTime::createFromFormat('Y-m', $paymentMonth);
                if ($date) {
                    $paymentMonth = $date->format('F Y');
                }
            }

            return [
                'id' => (int) ($row['id'] ?? 0),
                'user_id' => $userId,
                'full_name' => $fullName !== '' ? $fullName : 'Student',
                'grade' => $grade !== '' ? $grade : 'N/A',
                'payment_month' => $paymentMonth !== '' ? $paymentMonth : 'N/A',
                'amount' => (float) ($row['amount'] ?? 0),
                'receipt_path' => (string) ($row['receipt_path'] ?? ''),
                'status' => (string) ($row['status'] ?? 'Unpaid'),
                'created_at' => $row['created_at'] ?? null,
            ];
        }, $rows);
    }

    private function fetchActiveUsers() {
        if (!$this->tableExists('users')) {
            return [];
        }

        $stmt = $this->db->query("
            SELECT
                u.id,
                u.full_name,
                u.email,
                u.phone,
                u.status,
                r.name AS role,
                u.created_at
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.status = 'ACTIVE'
            ORDER BY u.created_at DESC
        ");
        return $stmt->fetchAll();
    }

    private function fetchSalaryDetails(int $selectedMonth, int $selectedYear) {
        if (!$this->tableExists('tutors') || !$this->tableExists('users')) {
            return [];
        }

        $hasSubject = $this->columnExists('tutors', 'subject');
        $hasTutorUserId = $this->columnExists('tutors', 'user_id');
        $stmt = $this->db->query("
            SELECT
                t.id AS tutor_profile_id,
                " . ($hasTutorUserId ? 't.user_id' : 't.id') . " AS tutor_user_id,
                " . ($hasTutorUserId ? 'u.full_name' : "CONCAT('Tutor #', t.id)") . " AS full_name,
                " . ($hasSubject ? 't.subject' : "''") . " AS subject,
                " . ($hasTutorUserId ? 'u.created_at' : 'NULL') . " AS created_at
            FROM tutors t
            " . ($hasTutorUserId ? 'INNER JOIN users u ON u.id = t.user_id' : '') . "
            ORDER BY full_name ASC
        ");

        $tutors = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $monthValue = sprintf('%04d-%02d', $selectedYear, $selectedMonth);

        return array_map(function ($tutor) use ($monthValue) {
            $hoursThisMonth = $this->calculateTutorMonthlyHours(
                (int) ($tutor['tutor_profile_id'] ?? 0),
                (int) ($tutor['tutor_user_id'] ?? 0)
            );
            $ratePerHour = $this->resolveTutorRateFromSubject((string) ($tutor['subject'] ?? ''));
            $baseAmount = $hoursThisMonth * $ratePerHour;
            $payment = $this->fetchTutorMonthlyPayment((int) ($tutor['tutor_profile_id'] ?? 0), $monthValue);

            return [
                'id' => (int) ($tutor['tutor_profile_id'] ?? 0),
                'full_name' => $tutor['full_name'] ?? 'Tutor',
                'subject' => $tutor['subject'] ?? '',
                'payment_month' => $monthValue,
                'hours_this_month' => $hoursThisMonth,
                'rate_per_hour' => $ratePerHour,
                'amount' => $payment['amount'] ?? $baseAmount,
                'status' => $payment['status'] ?? 'Pending',
                'created_at' => $payment['created_at'] ?? ($tutor['created_at'] ?? null),
            ];
        }, $tutors);
    }

    private function ensureSalaryPaymentsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS salary_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tutor_id INT NOT NULL,
                payment_month DATE NOT NULL,
                amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'Pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
    }

    private function calculateTutorMonthlyHours(int $tutorProfileId, int $tutorUserId): int
    {
        if (
            !$this->tableExists('timetable') ||
            !$this->columnExists('timetable', 'tutor_id') ||
            !$this->columnExists('timetable', 'time_slot')
        ) {
            return 0;
        }

        $ids = array_values(array_unique(array_filter([$tutorProfileId, $tutorUserId])));
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("
            SELECT DISTINCT day, time_slot
            FROM timetable
            WHERE tutor_id IN ({$placeholders})
        ");
        $stmt->execute($ids);
        $slots = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $weeklyHours = 0.0;
        foreach ($slots as $slot) {
            $weeklyHours += $this->parseTimeSlotHours((string) ($slot['time_slot'] ?? ''));
        }

        return (int) round($weeklyHours * 4);
    }

    private function fetchTutorMonthlyPayment(int $tutorProfileId, string $monthValue): ?array
    {
        if (
            !$this->tableExists('salary_payments') ||
            !$this->columnExists('salary_payments', 'tutor_id') ||
            !$this->columnExists('salary_payments', 'payment_month')
        ) {
            return null;
        }

        $selectAmount = $this->columnExists('salary_payments', 'amount') ? 'amount' : '0 AS amount';
        $selectStatus = $this->columnExists('salary_payments', 'status') ? 'status' : "'Pending' AS status";
        $selectCreatedAt = $this->columnExists('salary_payments', 'created_at') ? 'created_at' : 'NULL AS created_at';

        $stmt = $this->db->prepare("
            SELECT
                {$selectAmount},
                {$selectStatus},
                {$selectCreatedAt}
            FROM salary_payments
            WHERE tutor_id = ?
              AND DATE_FORMAT(payment_month, '%Y-%m') = ?
            ORDER BY payment_month DESC
            LIMIT 1
        ");
        $stmt->execute([$tutorProfileId, $monthValue]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'amount' => (float) ($row['amount'] ?? 0),
            'status' => trim((string) ($row['status'] ?? '')) ?: 'Pending',
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    private function resolveTutorRateFromSubject(string $subject): int
    {
        $normalized = strtolower(trim($subject));
        if (in_array($normalized, ['science', 'mathematics', 'maths', 'ict'], true)) {
            return 800;
        }

        return 700;
    }

    private function parseTimeSlotHours(string $timeSlot): float
    {
        $parts = array_map('trim', explode('-', $timeSlot));
        if (count($parts) !== 2) {
            return 0.0;
        }

        $start = DateTime::createFromFormat('H:i', $parts[0]);
        $end = DateTime::createFromFormat('H:i', $parts[1]);
        if (!$start || !$end) {
            return 0.0;
        }

        $startMinutes = ((int) $start->format('H') * 60) + (int) $start->format('i');
        $endMinutes = ((int) $end->format('H') * 60) + (int) $end->format('i');
        $durationMinutes = $endMinutes - $startMinutes;

        return $durationMinutes > 0 ? $durationMinutes / 60 : 0.0;
    }

    private function fetchAttendanceRecords() {
        if (!$this->tableExists('attendance')) {
            return [];
        }

        $hasTimetable = $this->tableExists('timetable');
        $hasClasses = $this->tableExists('classes');
        $hasStudents = $this->tableExists('students');
        $hasStudentUsers = $this->tableExists('users');
        $hasTutors = $this->tableExists('tutors');
        $hasTutorUsers = $this->tableExists('users');

        $joins = '';
        if ($hasTimetable && $this->columnExists('attendance', 'timetable_id')) {
            $joins .= ' LEFT JOIN timetable tt ON tt.id = a.timetable_id';
        }
        if ($hasClasses && $hasTimetable && $this->columnExists('timetable', 'class_id')) {
            $joins .= ' LEFT JOIN classes c ON c.id = tt.class_id';
        }
        if ($hasStudents && $this->columnExists('attendance', 'student_id')) {
            $joins .= ' LEFT JOIN students s ON s.id = a.student_id';
        }
        if ($hasStudentUsers && $hasStudents && $this->columnExists('students', 'user_id')) {
            $joins .= ' LEFT JOIN users su ON su.id = s.user_id';
        }
        if ($hasTutors && $this->columnExists('attendance', 'tutor_id')) {
            $joins .= ' LEFT JOIN tutors t ON t.id = a.tutor_id';
        }
        if ($hasTutorUsers && $this->columnExists('attendance', 'tutor_id')) {
            $joins .= ' LEFT JOIN users tu_direct ON tu_direct.id = a.tutor_id';
        }
        if ($hasTutorUsers && $hasTutors && $this->columnExists('tutors', 'user_id')) {
            $joins .= ' LEFT JOIN users tu_profile ON tu_profile.id = t.user_id';
        }

        $studentName = $hasStudentUsers && $hasStudents ? 'su.full_name' : "CONCAT('Student #', a.student_id)";
        $tutorName = $hasTutorUsers
            ? "COALESCE(tu_direct.full_name, tu_profile.full_name, CONCAT('Tutor #', a.tutor_id))"
            : "CONCAT('Tutor #', a.tutor_id)";
        $className = $hasClasses
            ? "COALESCE(NULLIF(c.title, ''), c.name, 'N/A')"
            : ($hasStudents && $this->columnExists('students', 'school_name') ? "COALESCE(NULLIF(s.school_name, ''), 'N/A')" : "'N/A'");
        $gradeValue = $hasClasses && $this->columnExists('classes', 'grade')
            ? 'c.grade'
            : ($hasStudents && $this->columnExists('students', 'grade') ? 's.grade' : "''");
        $markedAt = $this->columnExists('attendance', 'marked_at') ? 'a.marked_at' : 'NULL';
        $status = $this->columnExists('attendance', 'status') ? 'a.status' : "'N/A'";

        $stmt = $this->db->query("
            SELECT
                a.id,
                {$studentName} AS student_name,
                {$className} AS class_name,
                {$gradeValue} AS grade,
                {$tutorName} AS tutor_name,
                {$status} AS status,
                {$markedAt} AS marked_at
            FROM attendance a
            {$joins}
            ORDER BY a.id DESC
        ");

        return $stmt->fetchAll();
    }

    private function createClass(array $data) {
        if (!$this->tableExists('classes')) {
            Response::error('Classes table not found', 404);
        }

        $name = trim((string) ($data['name'] ?? ''));
        $title = trim((string) ($data['title'] ?? ''));
        $subject = trim((string) ($data['subject'] ?? ''));
        $grade = trim((string) ($data['grade'] ?? ''));

        if ($name === '' || $title === '' || $subject === '' || $grade === '') {
            Response::error('Please fill all class fields');
        }

        $stmt = $this->db->prepare("INSERT INTO classes (name, title, subject, grade) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $title, $subject, $grade]);
        Response::success(['message' => 'Class created']);
    }

    private function createPayment(array $data) {
        if (!$this->tableExists('payments')) {
            Response::error('Payments table not found', 404);
        }

        $userId = trim((string) ($data['user_id'] ?? ''));
        $amount = trim((string) ($data['amount'] ?? ''));
        $status = trim((string) ($data['status'] ?? ''));

        if ($userId === '' || $amount === '' || $status === '') {
            Response::error('Please fill all payment fields');
        }
        if (!ctype_digit($userId)) {
            Response::error('User ID must be numeric');
        }
        if (!is_numeric($amount)) {
            Response::error('Amount must be numeric');
        }
        if (!in_array($status, ['Paid', 'Pending', 'Unpaid'], true)) {
            Response::error('Invalid payment status');
        }

        $stmt = $this->db->prepare("INSERT INTO payments (user_id, amount, status) VALUES (?, ?, ?)");
        $stmt->execute([(int) $userId, (float) $amount, $status]);
        Response::success(['message' => 'Payment created']);
    }

    private function updateStudent($id, array $data) {
        if (!$this->tableExists('students')) {
            Response::error('Students table not found', 404);
        }

        $this->ensureUserExists($id);
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $status = trim((string) ($data['status'] ?? ''));
        $schoolName = trim((string) ($data['school_name'] ?? ''));
        $grade = trim((string) ($data['grade'] ?? ''));
        $guardianName = trim((string) ($data['guardian_name'] ?? ''));

        if ($fullName === '' || $email === '' || $status === '' || $schoolName === '' || $grade === '' || $guardianName === '') {
            Response::error('Please fill all student fields');
        }

        $stmt = $this->db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, status = ? WHERE id = ?");
        $stmt->execute([$fullName, $email, $phone !== '' ? $phone : null, $status, $id]);

        $stmt = $this->db->prepare("UPDATE students SET school_name = ?, grade = ?, guardian_name = ? WHERE user_id = ?");
        $stmt->execute([$schoolName, $grade, $guardianName, $id]);
        Response::success(['message' => 'Student updated']);
    }

    private function updateTutor($id, array $data) {
        if (!$this->tableExists('tutors')) {
            Response::error('Tutors table not found', 404);
        }

        $this->ensureUserExists($id);
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $status = trim((string) ($data['status'] ?? ''));
        $nicNumber = trim((string) ($data['nic_number'] ?? ''));
        $subject = trim((string) ($data['subject'] ?? ''));

        if ($fullName === '' || $email === '' || $status === '' || $nicNumber === '' || $subject === '') {
            Response::error('Please fill all tutor fields');
        }

        $stmt = $this->db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, status = ? WHERE id = ?");
        $stmt->execute([$fullName, $email, $phone !== '' ? $phone : null, $status, $id]);

        $stmt = $this->db->prepare("UPDATE tutors SET nic_number = ?, subject = ? WHERE user_id = ?");
        $stmt->execute([$nicNumber, $subject, $id]);
        Response::success(['message' => 'Tutor updated']);
    }

    private function updateClass($id, array $data) {
        if (!$this->tableExists('classes')) {
            Response::error('Classes table not found', 404);
        }

        $name = trim((string) ($data['name'] ?? ''));
        $title = trim((string) ($data['title'] ?? ''));
        $subject = trim((string) ($data['subject'] ?? ''));
        $grade = trim((string) ($data['grade'] ?? ''));

        if ($name === '' || $title === '' || $subject === '' || $grade === '') {
            Response::error('Please fill all class fields');
        }

        $stmt = $this->db->prepare("UPDATE classes SET name = ?, title = ?, subject = ?, grade = ? WHERE id = ?");
        $stmt->execute([$name, $title, $subject, $grade, $id]);
        Response::success(['message' => 'Class updated']);
    }

    private function updatePayment($id, array $data) {
        if (!$this->tableExists('payments')) {
            Response::error('Payments table not found', 404);
        }

        $userId = trim((string) ($data['user_id'] ?? ''));
        $amount = trim((string) ($data['amount'] ?? ''));
        $status = trim((string) ($data['status'] ?? ''));

        if ($userId === '' || $amount === '' || $status === '') {
            Response::error('Please fill all payment fields');
        }
        if (!ctype_digit($userId)) {
            Response::error('User ID must be numeric');
        }
        if (!is_numeric($amount)) {
            Response::error('Amount must be numeric');
        }
        if (!in_array($status, ['Paid', 'Unpaid'], true)) {
            Response::error('Invalid payment status');
        }

        $stmt = $this->db->prepare("UPDATE payments SET user_id = ?, amount = ?, status = ? WHERE id = ?");
        $stmt->execute([(int) $userId, (float) $amount, $status, $id]);
        Response::success(['message' => 'Payment updated']);
    }

    private function updateSalaryDetail($id, array $data)
    {
        if (!$this->tableExists('tutors')) {
            Response::error('Tutors table not found', 404);
        }

        $this->ensureSalaryPaymentsTable();

        $month = max(1, min(12, (int) ($data['month'] ?? date('m'))));
        $year = max(2000, min(2100, (int) ($data['year'] ?? date('Y'))));
        $amount = (float) ($data['amount'] ?? 0);
        $status = trim((string) ($data['status'] ?? 'Paid'));
        $paymentMonth = sprintf('%04d-%02d-01', $year, $month);

        if (!in_array(strtolower($status), ['paid', 'pending'], true)) {
            Response::error('Invalid salary status');
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM tutors WHERE id = ?");
        $stmt->execute([$id]);
        if (!(int) $stmt->fetchColumn()) {
            Response::error('Tutor not found', 404);
        }

        $stmt = $this->db->prepare("
            SELECT id
            FROM salary_payments
            WHERE tutor_id = ?
              AND DATE_FORMAT(payment_month, '%Y-%m') = ?
            LIMIT 1
        ");
        $stmt->execute([$id, sprintf('%04d-%02d', $year, $month)]);
        $paymentId = $stmt->fetchColumn();

        if ($paymentId) {
            $stmt = $this->db->prepare("
                UPDATE salary_payments
                SET amount = ?, status = ?, payment_month = ?
                WHERE id = ?
            ");
            $stmt->execute([$amount, ucfirst(strtolower($status)), $paymentMonth, $paymentId]);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO salary_payments (tutor_id, payment_month, amount, status)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$id, $paymentMonth, $amount, ucfirst(strtolower($status))]);
        }

        Response::success(['message' => 'Salary status updated']);
    }

    private function updateActiveUser($id, array $data) {
        $this->ensureUserExists($id);

        $fullName = trim((string) ($data['full_name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $status = trim((string) ($data['status'] ?? ''));

        if ($fullName === '' || $email === '' || $status === '') {
            Response::error('Please fill all user fields');
        }

        $stmt = $this->db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, status = ? WHERE id = ?");
        $stmt->execute([$fullName, $email, $phone !== '' ? $phone : null, $status, $id]);
        Response::success(['message' => 'User updated']);
    }

    private function deleteUser($id) {
        $this->ensureUserExists($id);

        try {
            $this->db->beginTransaction();

            if ($this->tableExists('login_approvals')) {
                $stmt = $this->db->prepare("DELETE FROM login_approvals WHERE user_id = ? OR admin_id = ?");
                $stmt->execute([$id, $id]);
            }

            $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }

        Response::success(['message' => 'Record deleted']);
    }

    private function deleteFromTable($tableName, $id) {
        if (!$this->tableExists($tableName)) {
            Response::error(ucfirst($tableName) . ' table not found', 404);
        }

        $stmt = $this->db->prepare("DELETE FROM {$tableName} WHERE id = ?");
        $stmt->execute([$id]);
        Response::success(['message' => 'Record deleted']);
    }

    private function ensureUserExists($id) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
        $stmt->execute([$id]);
        if (!(int) $stmt->fetchColumn()) {
            Response::error('Record not found', 404);
        }
    }
}





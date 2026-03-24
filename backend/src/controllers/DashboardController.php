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
        ]);
    }

    public function getDetail($metric) {
        $allowedMetrics = [
            'students',
            'tutors',
            'classes',
            'paid-payments',
            'pending-payments',
            'active-users',
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
            'active-users' => $this->updateActiveUser($id, $data),
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

    private function fetchMetricRows($metric) {
        return match ($metric) {
            'students' => $this->fetchStudents(),
            'tutors' => $this->fetchTutors(),
            'classes' => $this->fetchClasses(),
            'paid-payments' => $this->fetchPaymentsByStatus('Paid'),
            'pending-payments' => $this->fetchPaymentsByStatus('Unpaid'),
            'active-users' => $this->fetchActiveUsers(),
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
        if (!$this->tableExists('payments')) {
            return [];
        }

        $stmt = $this->db->prepare("SELECT * FROM payments WHERE status = ? ORDER BY id DESC");
        $stmt->execute([$status]);
        return $stmt->fetchAll();
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
        if (!in_array($status, ['Paid', 'Unpaid'], true)) {
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

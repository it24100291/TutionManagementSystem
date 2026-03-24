<?php
class User {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    public function beginTransaction() {
        $this->db->beginTransaction();
    }

    public function commit() {
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }
    }

    public function rollBack() {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function ensureRegistrationSchema() {
        $this->ensureUserColumns();
        $this->ensureTutorTable();
        $this->ensureStudentTable();
    }

    public function create($fullName, $email, $passwordHash, $roleId, $phone = null, $dob = null, $gender = null, $address = null) {
        $stmt = $this->db->prepare("INSERT INTO users (full_name, email, password_hash, role_id, phone, dob, gender, address, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')");
        $stmt->execute([$fullName, $email, $passwordHash, $roleId, $phone, $dob, $gender, $address]);
        return $this->db->lastInsertId();
    }

    public function createTutorProfile($userId, array $data) {
        $stmt = $this->db->prepare("INSERT INTO tutors (user_id, nic_number, subject) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $data['nic_number'], $data['subject']]);
    }

    public function createStudentProfile($userId, array $data) {
        $stmt = $this->db->prepare("INSERT INTO students (user_id, school_name, grade, siblings_count, guardian_name, guardian_job, guardian_nic) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $data['school_name'],
            $data['grade'],
            $data['siblings_count'],
            $data['guardian_name'],
            $data['guardian_job'],
            $data['guardian_nic']
        ]);
    }
    
    public function getRoleIdByName($roleName) {
        $stmt = $this->db->prepare("SELECT id FROM roles WHERE name = ?");
        $stmt->execute([$roleName]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    }
    
    public function getPendingUsers() {
        $stmt = $this->db->query("SELECT u.id, u.full_name, u.email, r.name as role, u.created_at FROM users u JOIN roles r ON u.role_id = r.id WHERE u.status = 'PENDING' ORDER BY u.created_at DESC");
        return $stmt->fetchAll();
    }

    public function getRegisteredStudents() {
        $stmt = $this->db->query("
            SELECT
                u.id,
                u.full_name,
                u.email,
                u.phone,
                u.dob,
                u.gender,
                u.address,
                u.status,
                u.created_at,
                s.school_name,
                s.grade,
                s.siblings_count,
                s.guardian_name,
                s.guardian_job,
                s.guardian_nic
            FROM students s
            JOIN users u ON u.id = s.user_id
            JOIN roles r ON r.id = u.role_id
            WHERE r.name = 'STUDENT'
            ORDER BY u.created_at DESC
        ");
        return $stmt->fetchAll();
    }
    
    public function findByEmail($email) {
        $stmt = $this->db->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
    
    public function findById($id) {
        $stmt = $this->db->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function findProfileById($id) {
        $this->ensureRegistrationSchema();

        $profile = $this->findById($id);
        if (!$profile) {
            return null;
        }

        if ($profile['role_name'] === 'TUTOR') {
            $stmt = $this->db->prepare("SELECT nic_number, subject FROM tutors WHERE user_id = ?");
            $stmt->execute([$id]);
            $details = $stmt->fetch();
            if ($details) {
                $profile = array_merge($profile, $details);
            }
        }

        if ($profile['role_name'] === 'STUDENT') {
            $stmt = $this->db->prepare("SELECT school_name, grade, siblings_count, guardian_name, guardian_job, guardian_nic FROM students WHERE user_id = ?");
            $stmt->execute([$id]);
            $details = $stmt->fetch();
            if ($details) {
                $profile = array_merge($profile, $details);
            }
        }

        return $profile;
    }
    
    public function getAll() {
        $stmt = $this->db->query("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.created_at DESC");
        return $stmt->fetchAll();
    }
    
    public function update($id, $data) {
        $fields = [];
        $values = [];
        
        foreach (['full_name', 'phone', 'dob', 'gender', 'address', 'avatar_url'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) return;
        
        $values[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
    }

    public function updateTutorProfile($userId, array $data) {
        $checkStmt = $this->db->prepare("SELECT COUNT(*) FROM tutors WHERE user_id = ?");
        $checkStmt->execute([$userId]);
        $exists = (int) $checkStmt->fetchColumn() > 0;

        if ($exists) {
            $stmt = $this->db->prepare("
                UPDATE tutors
                SET nic_number = ?, subject = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $data['nic_number'],
                $data['subject'],
                $userId
            ]);
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO tutors (user_id, nic_number, subject)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $data['nic_number'],
            $data['subject']
        ]);
    }

    public function updateStudentProfile($userId, array $data) {
        $checkStmt = $this->db->prepare("SELECT COUNT(*) FROM students WHERE user_id = ?");
        $checkStmt->execute([$userId]);
        $exists = (int) $checkStmt->fetchColumn() > 0;

        if ($exists) {
            $stmt = $this->db->prepare("
                UPDATE students
                SET school_name = ?, grade = ?, siblings_count = ?, guardian_name = ?, guardian_job = ?, guardian_nic = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $data['school_name'],
                $data['grade'],
                $data['siblings_count'],
                $data['guardian_name'],
                $data['guardian_job'],
                $data['guardian_nic'],
                $userId
            ]);
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO students (user_id, school_name, grade, siblings_count, guardian_name, guardian_job, guardian_nic)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $data['school_name'],
            $data['grade'],
            $data['siblings_count'],
            $data['guardian_name'],
            $data['guardian_job'],
            $data['guardian_nic']
        ]);
    }
    
    public function updateStatus($id, $status) {
        $stmt = $this->db->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
    }
    
    public function logApproval($adminId, $userId, $decision, $reason) {
        $stmt = $this->db->prepare("INSERT INTO login_approvals (admin_id, user_id, decision, reason) VALUES (?, ?, ?, ?)");
        $stmt->execute([$adminId, $userId, $decision, $reason]);
    }

    private function ensureUserColumns() {
        $requiredColumns = [
            'dob' => "ALTER TABLE users ADD COLUMN dob DATE NULL AFTER phone",
            'gender' => "ALTER TABLE users ADD COLUMN gender VARCHAR(20) NULL AFTER dob",
            'avatar_url' => "ALTER TABLE users ADD COLUMN avatar_url VARCHAR(500) NULL AFTER address"
        ];

        foreach ($requiredColumns as $column => $sql) {
            $escapedColumn = str_replace("'", "''", $column);
            $stmt = $this->db->query("SHOW COLUMNS FROM users LIKE '{$escapedColumn}'");
            if (!$stmt->fetch()) {
                $this->db->exec($sql);
            }
        }
    }

    private function ensureTutorTable() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS tutors (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL UNIQUE,
                nic_number VARCHAR(50) NOT NULL,
                subject VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_tutors_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON UPDATE CASCADE
                    ON DELETE CASCADE
            )
        ");
    }

    private function ensureStudentTable() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS students (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL UNIQUE,
                school_name VARCHAR(255) NOT NULL,
                grade VARCHAR(50) NOT NULL,
                siblings_count INT UNSIGNED NOT NULL DEFAULT 0,
                guardian_name VARCHAR(255) NOT NULL,
                guardian_job VARCHAR(255) NOT NULL,
                guardian_nic VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_students_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON UPDATE CASCADE
                    ON DELETE CASCADE
            )
        ");
    }
}

<?php
class AuthController {
    public function register() {
        $data = json_decode(file_get_contents('php://input'), true);

        $fullName = isset($data['full_name']) ? trim($data['full_name']) : '';
        $email = isset($data['email']) ? trim($data['email']) : '';
        $password = isset($data['password']) ? $data['password'] : '';
        $role = isset($data['role']) ? strtoupper(trim($data['role'])) : '';
        $phone = isset($data['phone']) ? trim($data['phone']) : '';
        $dob = isset($data['dob']) ? trim($data['dob']) : '';
        $gender = isset($data['gender']) ? trim($data['gender']) : '';
        $address = isset($data['address']) ? trim($data['address']) : '';

        if (!$fullName || !$email || !$password || !$role || !$phone || !$dob || !$gender || !$address) {
            Response::error('Missing required fields');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email format');
        }

        if (strlen($password) < 6) {
            Response::error('Password must be at least 6 characters');
        }

        if (!preg_match('/^07\d{8}$/', $phone)) {
            Response::error('Phone number must be a valid Sri Lankan mobile number in 07XXXXXXXX format');
        }

        if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
            Response::error('Invalid gender selected');
        }

        if (!in_array($role, ['TUTOR', 'STUDENT'], true)) {
            Response::error('Invalid role. Must be TUTOR or STUDENT');
        }

        $user = new User();
        $user->ensureRegistrationSchema();

        if ($user->findByEmail($email)) {
            Response::error('Email already exists');
        }

        $roleId = $user->getRoleIdByName($role);
        if (!$roleId) {
            Response::error('Invalid role');
        }

        $details = $this->buildRoleDetails($role, $data);
        $user->beginTransaction();

        try {
            $userId = $user->create(
                $fullName,
                $email,
                password_hash($password, PASSWORD_BCRYPT),
                $roleId,
                $phone,
                $dob,
                $gender,
                $address
            );

            if ($role === 'TUTOR') {
                $user->createTutorProfile($userId, $details);
            } else {
                $user->createStudentProfile($userId, $details);
            }

            $user->commit();
        } catch (Throwable $e) {
            $user->rollBack();
            Response::error($e->getMessage(), 500);
        }

        Response::success(['message' => 'Waiting for admin approval'], 201);
    }

    private function buildRoleDetails(string $role, array $data): array {
        if ($role === 'TUTOR') {
            $nicNumber = isset($data['nic_number']) ? trim($data['nic_number']) : '';
            $subject = isset($data['subject']) ? trim($data['subject']) : '';

            if ($nicNumber === '' || $subject === '') {
                Response::error('Missing tutor required fields');
            }

            return [
                'nic_number' => $nicNumber,
                'subject' => $subject
            ];
        }

        $schoolName = isset($data['school_name']) ? trim($data['school_name']) : '';
        $grade = isset($data['grade']) ? trim($data['grade']) : '';
        $siblingsCount = isset($data['siblings_count']) ? trim((string) $data['siblings_count']) : '';
        $guardianName = isset($data['guardian_name']) ? trim($data['guardian_name']) : '';
        $guardianJob = isset($data['guardian_job']) ? trim($data['guardian_job']) : '';
        $guardianNic = isset($data['guardian_nic']) ? trim($data['guardian_nic']) : '';

        if ($schoolName === '' || $grade === '' || $siblingsCount === '' || $guardianName === '' || $guardianJob === '' || $guardianNic === '') {
            Response::error('Missing student required fields');
        }

        if (!preg_match('/^\d+$/', $siblingsCount)) {
            Response::error('Number of siblings must be numeric');
        }

        return [
            'school_name' => $schoolName,
            'grade' => $grade,
            'siblings_count' => (int) $siblingsCount,
            'guardian_name' => $guardianName,
            'guardian_job' => $guardianJob,
            'guardian_nic' => $guardianNic
        ];
    }
    
    public function login() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $email = isset($data['email']) ? $data['email'] : '';
        $password = isset($data['password']) ? $data['password'] : '';
        
        if (!$email || !$password) {
            Response::error('Missing credentials');
        }
        
        $user = new User();
        $userData = $user->findByEmail($email);
        
        if (!$userData || !password_verify($password, $userData['password_hash'])) {
            Response::error('Invalid credentials', 401);
        }
        
        if ($userData['status'] === 'PENDING') {
            Response::error('Account pending approval', 403);
        }

        if ($userData['status'] === 'REJECTED') {
            Response::error('Account rejected', 403);
        }

        if ($userData['status'] !== 'ACTIVE') {
            Response::error('Account unavailable', 403);
        }
        
        $token = JWTUtil::encode([
            'id' => $userData['id'],
            'email' => $userData['email'],
            'role' => $userData['role_name']
        ]);
        
        Response::success([
            'token' => $token,
            'user' => [
                'id' => $userData['id'],
                'full_name' => $userData['full_name'],
                'email' => $userData['email'],
                'role' => $userData['role_name'],
                'status' => $userData['status']
            ]
        ]);
    }
}

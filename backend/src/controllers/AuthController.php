<?php
class AuthController {
    public function register() {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $normalized = $this->normalizeRegistrationData($data);
        $this->validateOtpRequestData($normalized);

        $verification = new EmailVerification();
        $record = $verification->findByEmail($normalized['email']);
        if (!$record || (int) ($record['is_verified'] ?? 0) !== 1) {
            Response::error('Email is not verified.', 403);
        }


        
        if (strtotime((string) $record['expires_at']) < time()) {
            $verification->deleteByEmail($normalized['email']);
            Response::error('OTP expired. Please request a new one.', 410);
        }

        $this->performRegistration($normalized);
        $verification->deleteByEmail($normalized['email']);
    }

    public function sendOtp() {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $this->processOtpSend($data);
    }

    private function processOtpSend(array $data): void {
        $normalized = $this->normalizeRegistrationData($data);
        $this->validateOtpRequestData($normalized);

        $user = new User();
        $user->ensureRegistrationSchema();
        if ($user->findByEmail($normalized['email'])) {
            Response::error($this->duplicateEmailMessage($normalized['role']), 409);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = (new DateTimeImmutable('+5 minutes'))->format('Y-m-d H:i:s');

        $verification = new EmailVerification();
        $verification->upsert(
            $normalized['email'],
            password_hash($otp, PASSWORD_BCRYPT),
            $expiresAt
        );

        Mailer::sendOtp($normalized['email'], $otp);

        Response::success([
            'message' => 'Verification code sent successfully.',
            'expires_in' => 300,
            'email' => $normalized['email'],
        ]);
    }

    public function verifyOtp() {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $otp = trim((string) ($data['otp'] ?? ''));
        $registration = isset($data['registration']) && is_array($data['registration']) ? $data['registration'] : [];
        $normalized = $this->normalizeRegistrationData($registration);

        if (!preg_match('/^\d{6}$/', $otp)) {
            Response::error('Invalid verification code.');
        }

        $this->validateOtpRequestData($normalized);

        $verification = new EmailVerification();
        $record = $verification->findByEmail($normalized['email']);

        if (!$record) {
            Response::error('OTP expired. Please request a new one.', 410);
        }

        if ((int) $record['attempts'] >= 5) {
            $verification->deleteByEmail($normalized['email']);
            Response::error('OTP expired. Please request a new one.', 429);
        }

        if (strtotime((string) $record['expires_at']) < time()) {
            $verification->deleteByEmail($normalized['email']);
            Response::error('OTP expired. Please request a new one.', 410);
        }

        if (!password_verify($otp, (string) $record['otp_code'])) {
            $verification->incrementAttempts((int) $record['id']);
            Response::error('Invalid verification code.', 400);
        }

        $verification->markVerified((int) $record['id']);
        Response::success([
            'message' => 'Email verified successfully.',
            'email' => $normalized['email'],
        ]);
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
        $parentEmail = isset($data['parent_email']) ? trim((string) $data['parent_email']) : '';
        $guardianName = isset($data['guardian_name']) ? trim($data['guardian_name']) : '';
        $guardianJob = isset($data['guardian_job']) ? trim($data['guardian_job']) : '';
        $guardianNic = isset($data['guardian_nic']) ? trim($data['guardian_nic']) : '';

        if ($schoolName === '' || $grade === '' || $siblingsCount === '' || $guardianName === '' || $guardianJob === '' || $guardianNic === '') {
            Response::error('Missing student required fields');
        }

        if ($parentEmail !== '' && !filter_var($parentEmail, FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid parent email format');
        }

        if (!preg_match('/^\d+$/', $siblingsCount)) {
            Response::error('Number of siblings must be numeric');
        }

        return [
            'school_name' => $schoolName,
            'grade' => $grade,
            'siblings_count' => (int) $siblingsCount,
            'parent_email' => $parentEmail,
            'guardian_name' => $guardianName,
            'guardian_job' => $guardianJob,
            'guardian_nic' => $guardianNic
        ];
    }

    private function normalizeRegistrationData(array $data): array {
        return [
            'full_name' => isset($data['full_name']) ? trim((string) $data['full_name']) : '',
            'email' => isset($data['email']) ? trim((string) $data['email']) : '',
            'password' => isset($data['password']) ? (string) $data['password'] : '',
            'role' => isset($data['role']) ? strtoupper(trim((string) $data['role'])) : '',
            'phone' => isset($data['phone']) ? trim((string) $data['phone']) : '',
            'dob' => isset($data['dob']) ? trim((string) $data['dob']) : '',
            'gender' => isset($data['gender']) ? trim((string) $data['gender']) : '',
            'address' => isset($data['address']) ? trim((string) $data['address']) : '',
            'nic_number' => isset($data['nic_number']) ? trim((string) $data['nic_number']) : '',
            'subject' => isset($data['subject']) ? trim((string) $data['subject']) : '',
            'school_name' => isset($data['school_name']) ? trim((string) $data['school_name']) : '',
            'grade' => isset($data['grade']) ? trim((string) $data['grade']) : '',
            'siblings_count' => isset($data['siblings_count']) ? trim((string) $data['siblings_count']) : '',
            'parent_email' => isset($data['parent_email']) ? trim((string) $data['parent_email']) : '',
            'guardian_name' => isset($data['guardian_name']) ? trim((string) $data['guardian_name']) : '',
            'guardian_job' => isset($data['guardian_job']) ? trim((string) $data['guardian_job']) : '',
            'guardian_nic' => isset($data['guardian_nic']) ? trim((string) $data['guardian_nic']) : '',
        ];
    }

    private function validateRegistrationData(array $data): void {
        $fullName = $data['full_name'];
        $email = $data['email'];
        $password = $data['password'];
        $role = $data['role'];
        $phone = $data['phone'];
        $dob = $data['dob'];
        $gender = $data['gender'];
        $address = $data['address'];

        if (!$fullName || !$email || !$password || !$role || !$phone || !$dob || !$gender || !$address) {
            Response::error('Missing required fields');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email format');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            Response::error('Invalid date of birth format');
        }

        if ($role === 'STUDENT' && !$this->isAtLeastAge($dob, 10)) {
            Response::error('Student must be at least 10 years old.');
        }

        if ($role === 'TUTOR' && !$this->isAtLeastAge($dob, 20)) {
            Response::error('Tutor must be at least 20 years old.');
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

        $this->buildRoleDetails($role, $data);
    }

    private function validateOtpRequestData(array $data): void {
        if ($data['role'] === '' || !in_array($data['role'], ['TUTOR', 'STUDENT'], true)) {
            Response::error('Invalid role. Must be TUTOR or STUDENT');
        }

        if ($data['full_name'] === '' || strlen($data['full_name']) < 3) {
            Response::error('Please enter your full name (minimum 3 characters).');
        }

        if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            Response::error('Please enter a valid email address.');
        }
    }

    private function performRegistration(array $inputData): void {
        $data = $this->normalizeRegistrationData($inputData);
        $this->validateRegistrationData($data);

        $fullName = $data['full_name'];
        $email = $data['email'];
        $password = $data['password'];
        $role = $data['role'];
        $phone = $data['phone'];
        $dob = $data['dob'];
        $gender = $data['gender'];
        $address = $data['address'];

        $user = new User();
        $user->ensureRegistrationSchema();

        if ($user->findByEmail($email)) {
            Response::error($this->duplicateEmailMessage($role), 409);
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

        Response::success(['message' => 'Registration successful. Waiting for admin approval.'], 201);
    }

    private function duplicateEmailMessage(string $role): string {
        if ($role === 'STUDENT') {
            return 'This student login email is already used. Use a unique student email and enter the shared parent email in the parent email field.';
        }

        return 'Email is already registered.';
    }

    private function isAtLeastAge(string $dob, int $minimumAge): bool {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $dob);
        if (!$date || $date->format('Y-m-d') !== $dob) {
            return false;
        }

        $today = new DateTimeImmutable('today');
        return $date <= $today->modify("-{$minimumAge} years");
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

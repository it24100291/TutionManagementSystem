<?php
class UserController {
    private function formatProfileResponse($profile) {
        return [
            'id' => $profile['id'],
            'full_name' => $profile['full_name'],
            'email' => $profile['email'],
            'role' => $profile['role_name'],
            'status' => $profile['status'],
            'phone' => $profile['phone'],
            'dob' => $profile['dob'] ?? null,
            'gender' => $profile['gender'] ?? null,
            'address' => $profile['address'],
            'avatar_url' => $profile['avatar_url'],
            'created_at' => $profile['created_at'],
            'nic_number' => $profile['nic_number'] ?? null,
            'subject' => $profile['subject'] ?? null,
            'school_name' => $profile['school_name'] ?? null,
            'grade' => $profile['grade'] ?? null,
            'siblings_count' => $profile['siblings_count'] ?? null,
            'parent_email' => $profile['parent_email'] ?? null,
            'guardian_name' => $profile['guardian_name'] ?? null,
            'guardian_job' => $profile['guardian_job'] ?? null,
            'guardian_nic' => $profile['guardian_nic'] ?? null
        ];
    }

    public function getProfile() {
        $userId = $_SESSION['user']['id'];
        $user = new User();
        $profile = $user->findProfileById($userId);
        
        if (!$profile) {
            Response::error('User not found', 404);
        }
        
        Response::success($this->formatProfileResponse($profile));
    }
    
    public function updateProfile() {
        $userId = $_SESSION['user']['id'];
        $data = json_decode(file_get_contents('php://input'), true);
        $user = new User();
        $profile = $user->findProfileById($userId);

        if (!$profile) {
            Response::error('User not found', 404);
        }
        
        $allowedFields = ['full_name', 'phone', 'dob', 'gender', 'address', 'avatar_url'];
        $updateData = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $value = trim($data[$field]);
                
                // Validate and sanitize
                if ($field === 'full_name' && strlen($value) > 255) {
                    Response::error('Full name too long (max 255 characters)');
                }
                if ($field === 'phone' && $value !== '' && !preg_match('/^07\d{8}$/', $value)) {
                    Response::error('Phone number must be a valid Sri Lankan mobile number in 07XXXXXXXX format');
                }
                if ($field === 'dob' && $value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    Response::error('Invalid date of birth format');
                }
                if ($field === 'gender' && $value !== '' && !in_array($value, ['Male', 'Female', 'Other'], true)) {
                    Response::error('Invalid gender selected');
                }
                if ($field === 'avatar_url' && strlen($value) > 500) {
                    Response::error('Avatar URL too long (max 500 characters)');
                }
                if ($field === 'address' && strlen($value) > 1000) {
                    Response::error('Address too long (max 1000 characters)');
                }

                if (in_array($field, ['dob', 'gender', 'address', 'avatar_url'], true) && $value === '') {
                    $updateData[$field] = null;
                } else {
                    $updateData[$field] = $value;
                }
            }
        }

        $roleName = $profile['role_name'];
        $roleSpecificUpdated = false;

        if ($roleName === 'TUTOR') {
            $tutorFields = ['nic_number', 'subject'];
            $tutorData = [];
            foreach ($tutorFields as $field) {
                if (!isset($data[$field])) {
                    continue;
                }

                $value = trim((string) $data[$field]);
                if ($value === '') {
                    continue;
                }
                if ($field === 'nic_number' && !preg_match('/^(?:\d{9}[Vv]|\d{12})$/', $value)) {
                    Response::error('Tutor NIC number must be either 9 digits followed by V/v or exactly 12 digits');
                }
                if (strlen($value) > 255) {
                    Response::error('Tutor field value too long');
                }
                $tutorData[$field] = $value;
            }

            if (!empty($tutorData)) {
                if (!isset($tutorData['nic_number'])) {
                    $tutorData['nic_number'] = $profile['nic_number'] ?? '';
                }
                if (!isset($tutorData['subject'])) {
                    $tutorData['subject'] = $profile['subject'] ?? '';
                }

                if ($tutorData['nic_number'] === '' || $tutorData['subject'] === '') {
                    Response::error('Please provide tutor NIC number and subject');
                }
                $user->updateTutorProfile($userId, $tutorData);
                $roleSpecificUpdated = true;
            }
        }

        if ($roleName === 'STUDENT') {
            $studentFields = ['school_name', 'grade', 'siblings_count', 'parent_email', 'guardian_name', 'guardian_job', 'guardian_nic'];
            $studentData = [];
            foreach ($studentFields as $field) {
                if (!isset($data[$field])) {
                    continue;
                }

                $value = trim((string) $data[$field]);
                if ($value === '') {
                    continue;
                }
                if ($field === 'siblings_count' && !ctype_digit($value)) {
                    Response::error('Siblings count must be numeric');
                }
                if ($field === 'parent_email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    Response::error('Parent email must be a valid email address');
                }
                if ($field === 'guardian_nic' && !preg_match('/^(?:\d{9}[Vv]|\d{12})$/', $value)) {
                    Response::error('Guardian NIC number must be either 9 digits followed by V/v or exactly 12 digits');
                }
                if ($field !== 'siblings_count' && strlen($value) > 255) {
                    Response::error('Student field value too long');
                }
                $studentData[$field] = $field === 'siblings_count' ? (int) $value : $value;
            }

            if (!empty($studentData)) {
                $studentData['school_name'] = $studentData['school_name'] ?? ($profile['school_name'] ?? '');
                $studentData['grade'] = $studentData['grade'] ?? ($profile['grade'] ?? '');
                $studentData['siblings_count'] = $studentData['siblings_count'] ?? (int) ($profile['siblings_count'] ?? 0);
                $studentData['parent_email'] = $studentData['parent_email'] ?? ($profile['parent_email'] ?? '');
                $studentData['guardian_name'] = $studentData['guardian_name'] ?? ($profile['guardian_name'] ?? '');
                $studentData['guardian_job'] = $studentData['guardian_job'] ?? ($profile['guardian_job'] ?? '');
                $studentData['guardian_nic'] = $studentData['guardian_nic'] ?? ($profile['guardian_nic'] ?? '');

                if (
                    $studentData['school_name'] === '' ||
                    $studentData['grade'] === '' ||
                    $studentData['parent_email'] === '' ||
                    $studentData['guardian_name'] === '' ||
                    $studentData['guardian_job'] === '' ||
                    $studentData['guardian_nic'] === ''
                ) {
                    Response::error('Please provide all student details');
                }
                $user->updateStudentProfile($userId, $studentData);
                $roleSpecificUpdated = true;
            }
        }
        
        if (empty($updateData) && !$roleSpecificUpdated) {
            Response::error('No valid fields to update');
        }
        
        if (!empty($updateData)) {
            $user->update($userId, $updateData);
        }
        
        // Return updated profile
        $profile = $user->findProfileById($userId);
        Response::success($this->formatProfileResponse($profile));
    }
}

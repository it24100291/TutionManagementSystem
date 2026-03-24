<?php
class AdminController {
    public function getRegisteredStudents() {
        $user = new User();
        $students = $user->getRegisteredStudents();
        Response::success($students);
    }

    public function getPendingUsers() {
        $user = new User();
        $users = $user->getPendingUsers();
        Response::success($users);
    }
    
    public function approveUser($userId) {
        $adminId = $_SESSION['user']['id'];
        $data = json_decode(file_get_contents('php://input'), true);
        
        $decision = isset($data['decision']) ? $data['decision'] : '';
        $reason = isset($data['reason']) ? $data['reason'] : null;
        
        if (!in_array($decision, ['APPROVE', 'REJECT'])) {
            Response::error('Invalid decision. Must be APPROVE or REJECT');
        }
        
        $user = new User();
        $targetUser = $user->findById($userId);
        
        if (!$targetUser) {
            Response::error('User not found', 404);
        }

        if ($targetUser['status'] !== 'PENDING') {
            Response::error('Only pending users can be reviewed', 409);
        }
        
        // Update status based on decision
        $newStatus = $decision === 'APPROVE' ? 'ACTIVE' : 'REJECTED';
        $user->updateStatus($userId, $newStatus);
        
        // Log approval decision
        $user->logApproval($adminId, $userId, $decision, $reason);
        
        // Get updated user
        $updatedUser = $user->findById($userId);
        
        Response::success([
            'message' => $decision === 'APPROVE' ? 'User approved' : 'User rejected',
            'user' => [
                'id' => $updatedUser['id'],
                'full_name' => $updatedUser['full_name'],
                'email' => $updatedUser['email'],
                'role' => $updatedUser['role_name'],
                'status' => $updatedUser['status']
            ]
        ]);
    }
    
    public function getUserById($userId) {
        $user = new User();
        $profile = $user->findById($userId);
        
        if (!$profile) {
            Response::error('User not found', 404);
        }
        
        Response::success([
            'id' => $profile['id'],
            'full_name' => $profile['full_name'],
            'email' => $profile['email'],
            'role' => $profile['role_name'],
            'status' => $profile['status'],
            'phone' => $profile['phone'],
            'address' => $profile['address'],
            'avatar_url' => $profile['avatar_url'],
            'created_at' => $profile['created_at']
        ]);
    }
}

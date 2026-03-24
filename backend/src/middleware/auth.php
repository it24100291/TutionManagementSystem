<?php
function authMiddleware() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = '';

    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    } elseif (isset($headers['authorization'])) {
        $authHeader = $headers['authorization'];
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }
    
    if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        Response::error('Unauthorized', 401);
    }
    
    try {
        $token = $matches[1];
        $decoded = JWTUtil::decode($token);
        
        // Load user from database
        $userModel = new User();
        $user = $userModel->findById($decoded->id);
        
        if (!$user) {
            Response::error('User not found', 401);
        }
        
        // Attach user to session
        $_SESSION['user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role_name'],
            'status' => $user['status']
        ];
    } catch (Throwable $e) {
        Response::error('Invalid token', 401);
    }
}

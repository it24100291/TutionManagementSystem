<?php
function rbacMiddleware($allowedRoles) {
    $user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
    
    if (!$user || !in_array($user['role'], $allowedRoles)) {
        Response::error('Forbidden', 403);
    }
}

<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../src/config/env.php';
require_once __DIR__ . '/../../../src/config/db.php';
require_once __DIR__ . '/../../../src/utils/response.php';
require_once __DIR__ . '/../../../src/utils/jwt.php';
require_once __DIR__ . '/../../../src/middleware/auth.php';
require_once __DIR__ . '/../../../src/middleware/rbac.php';
require_once __DIR__ . '/../../../src/controllers/ExaminationController.php';
require_once __DIR__ . '/../../../src/models/User.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

authMiddleware();
rbacMiddleware(['ADMIN']);

$controller = new ExaminationController();
$controller->getAdminResults();

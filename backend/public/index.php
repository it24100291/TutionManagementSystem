<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/config/env.php';
require_once __DIR__ . '/../src/config/db.php';
require_once __DIR__ . '/../src/utils/response.php';
require_once __DIR__ . '/../src/utils/jwt.php';
require_once __DIR__ . '/../src/middleware/auth.php';
require_once __DIR__ . '/../src/middleware/rbac.php';
require_once __DIR__ . '/../src/controllers/AuthController.php';
require_once __DIR__ . '/../src/controllers/UserController.php';
require_once __DIR__ . '/../src/controllers/AdminController.php';
require_once __DIR__ . '/../src/controllers/SuggestionController.php';
require_once __DIR__ . '/../src/controllers/TimetableController.php';
require_once __DIR__ . '/../src/controllers/DashboardController.php';
require_once __DIR__ . '/../src/models/User.php';
require_once __DIR__ . '/../src/models/Suggestion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$allowedOrigins = array_filter(array_map('trim', explode(',', env('APP_CORS_ORIGIN', 'http://localhost:5173,http://localhost:5174'))));
$requestOrigin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if ($requestOrigin && in_array($requestOrigin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
    header('Vary: Origin');
} elseif (!empty($allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigins[0]);
}
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/api/overview.php' || $path === '/overview.php') {
    require __DIR__ . '/api/overview.php';
    exit;
}

if ($path === '/api/student_overview.php' || $path === '/student_overview.php') {
    require __DIR__ . '/api/student_overview.php';
    exit;
}

if ($path === '/api/student_attendance.php' || $path === '/student_attendance.php') {
    require __DIR__ . '/api/student_attendance.php';
    exit;
}

if ($path === '/api/student_payments.php' || $path === '/student_payments.php') {
    require __DIR__ . '/api/student_payments.php';
    exit;
}

if ($path === '/api/student_exams_results.php' || $path === '/student_exams_results.php') {
    require __DIR__ . '/api/student_exams_results.php';
    exit;
}

if ($path === '/api/student_timetable.php' || $path === '/student_timetable.php') {
    require __DIR__ . '/api/student_timetable.php';
    exit;
}

if ($path === '/api/contact.php' || $path === '/contact.php') {
    require __DIR__ . '/api/contact.php';
    exit;
}

if ($path === '/api/tutor/overview.php' || $path === '/tutor/overview.php') {
    require __DIR__ . '/api/tutor/overview.php';
    exit;
}

if ($path === '/api/tutor/today-schedule.php' || $path === '/tutor/today-schedule.php') {
    require __DIR__ . '/api/tutor/today-schedule.php';
    exit;
}

if ($path === '/api/tutor/report-absence.php' || $path === '/tutor/report-absence.php') {
    require __DIR__ . '/api/tutor/report-absence.php';
    exit;
}

if ($path === '/api/tutor/next-class.php' || $path === '/tutor/next-class.php') {
    require __DIR__ . '/api/tutor/next-class.php';
    exit;
}

if ($path === '/api/tutor/class-students.php' || $path === '/tutor/class-students.php') {
    require __DIR__ . '/api/tutor/class-students.php';
    exit;
}

if ($path === '/api/tutor/submit-attendance.php' || $path === '/tutor/submit-attendance.php') {
    require __DIR__ . '/api/tutor/submit-attendance.php';
    exit;
}

if ($path === '/api/tutor/class-performance.php' || $path === '/tutor/class-performance.php') {
    require __DIR__ . '/api/tutor/class-performance.php';
    exit;
}

if ($path === '/api/tutor/students.php' || $path === '/tutor/students.php') {
    require __DIR__ . '/api/tutor/students.php';
    exit;
}

if ($path === '/api/tutor/leave-requests.php' || $path === '/tutor/leave-requests.php') {
    require __DIR__ . '/api/tutor/leave-requests.php';
    exit;
}

if ($path === '/api/tutor/update-leave.php' || $path === '/tutor/update-leave.php') {
    require __DIR__ . '/api/tutor/update-leave.php';
    exit;
}

if ($path === '/api/tutor/salary-summary.php' || $path === '/tutor/salary-summary.php') {
    require __DIR__ . '/api/tutor/salary-summary.php';
    exit;
}

if ($path === '/api/tutor/salary-slip.php' || $path === '/tutor/salary-slip.php') {
    require __DIR__ . '/api/tutor/salary-slip.php';
    exit;
}

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($scriptDir !== '' && str_starts_with($path, $scriptDir)) {
    $path = substr($path, strlen($scriptDir));
}
$path = preg_replace('#^/index\.php#', '', $path);
$path = preg_replace('#/+#', '/', $path);
$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

$normalizedApiPath = str_starts_with($path, '/api/') || $path === '/api'
    ? $path
    : '/api' . $path;
$normalizedApiPath = rtrim($normalizedApiPath, '/');
if ($normalizedApiPath === '') {
    $normalizedApiPath = '/';
}

$routeCandidates = array_values(array_unique([
    $path,
    $normalizedApiPath,
    preg_replace('#^/api#', '', $normalizedApiPath),
]));

$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');

$matchesRoute = function (string $expected) use ($routeCandidates): bool {
    foreach ($routeCandidates as $candidate) {
        if ($candidate === $expected) {
            return true;
        }
    }
    return false;
};

$matchesPattern = function (string $pattern, &$matches = null) use ($routeCandidates): bool {
    foreach ($routeCandidates as $candidate) {
        if (preg_match($pattern, $candidate, $candidateMatches)) {
            $matches = $candidateMatches;
            return true;
        }
    }
    return false;
};

$containsRouteFragment = function (string $fragment) use ($routeCandidates, $requestUri): bool {
    foreach ($routeCandidates as $candidate) {
        if (str_contains($candidate, $fragment)) {
            return true;
        }
    }

    return str_contains($requestUri, $fragment);
};

// Simple Router
try {
    // Health Check
    if ($method === 'GET' && ($matchesRoute('/api/health') || $matchesRoute('/health'))) {
        $db = getDB();
        $db->query('SELECT 1');
        Response::success(['ok' => true, 'db' => 'ok']);
    }
    
    // Auth Routes
    elseif ($method === 'POST' && ($matchesPattern('#^/(api/)?auth/register/?$#', $matches) || $containsRouteFragment('auth/register'))) {
        $controller = new AuthController();
        $controller->register();
    }
    elseif ($method === 'POST' && ($matchesPattern('#^/(api/)?auth/login/?$#', $matches) || $containsRouteFragment('auth/login'))) {
        $controller = new AuthController();
        $controller->login();
    }
    
    // User Routes
    elseif ($method === 'GET' && ($matchesRoute('/api/me') || $matchesRoute('/me'))) {
        authMiddleware();
        $controller = new UserController();
        $controller->getProfile();
    }
    elseif ($method === 'PUT' && ($matchesRoute('/api/me') || $matchesRoute('/me'))) {
        authMiddleware();
        $controller = new UserController();
        $controller->updateProfile();
    }
    
    // Admin Routes
    elseif ($method === 'GET' && ($matchesRoute('/api/admin/pending-users') || $matchesRoute('/admin/pending-users'))) {
        authMiddleware();
        rbacMiddleware(['ADMIN']);
        $controller = new AdminController();
        $controller->getPendingUsers();
    }
    elseif ($method === 'GET' && ($matchesRoute('/api/admin/students') || $matchesRoute('/admin/students'))) {
        authMiddleware();
        rbacMiddleware(['ADMIN']);
        $controller = new AdminController();
        $controller->getRegisteredStudents();
    }
    elseif ($method === 'POST' && $matchesPattern('#^/(api/)?admin/approve-user/(\d+)$#', $matches)) {
        authMiddleware();
        rbacMiddleware(['ADMIN']);
        $controller = new AdminController();
        $controller->approveUser($matches[2]);
    }
    elseif ($method === 'GET' && $matchesPattern('#^/(api/)?users/(\d+)$#', $matches)) {
        authMiddleware();
        rbacMiddleware(['ADMIN']);
        $controller = new AdminController();
        $controller->getUserById($matches[2]);
    }
    elseif ($method === 'GET' && ($matchesRoute('/api/admin/timetable') || $matchesRoute('/admin/timetable'))) {
        authMiddleware();
        rbacMiddleware(['ADMIN']);
        $controller = new TimetableController();
        $controller->getTimetable();
    }
    elseif (($method === 'POST' || $method === 'PUT') && ($matchesRoute('/api/admin/timetable') || $matchesRoute('/admin/timetable'))) {
        authMiddleware();
        rbacMiddleware(['ADMIN']);
        $controller = new TimetableController();
        $controller->saveCell();
    }
    elseif ($method === 'GET' && ($matchesRoute('/api/admin/dashboard') || $matchesRoute('/admin/dashboard'))) {
        authMiddleware();
        rbacMiddleware(['ADMIN']);
        $controller = new DashboardController();
        $controller->getSummary();
    }
    elseif ($method === 'GET' && $matchesPattern('#^/(api/)?admin/dashboard/([a-z-]+)$#', $matches)) {
        authMiddleware();
        rbacMiddleware(['ADMIN']);
        $controller = new DashboardController();
        $controller->getDetail($matches[2]);
    }
    elseif ($method === 'POST' && $matchesPattern('#^/(api/)?admin/dashboard/([a-z-]+)$#', $matches)) {
        authMiddleware();
        rbacMiddleware(['ADMIN']);
        $controller = new DashboardController();
        $controller->createDetail($matches[2]);
    }
    elseif ($method === 'PUT' && $matchesPattern('#^/(api/)?admin/dashboard/([a-z-]+)/(\d+)$#', $matches)) {
        authMiddleware();
        rbacMiddleware(['ADMIN']);
        $controller = new DashboardController();
        $controller->updateDetail($matches[2], $matches[3]);
    }
    elseif ($method === 'DELETE' && $matchesPattern('#^/(api/)?admin/dashboard/([a-z-]+)/(\d+)$#', $matches)) {
        authMiddleware();
        rbacMiddleware(['ADMIN']);
        $controller = new DashboardController();
        $controller->deleteDetail($matches[2], $matches[3]);
    }
    
    // Suggestion Routes
    elseif ($method === 'POST' && ($matchesRoute('/api/suggestions') || $matchesRoute('/suggestions'))) {
        authMiddleware();
        $controller = new SuggestionController();
        $controller->create();
    }
    elseif ($method === 'GET' && ($matchesRoute('/api/suggestions/mine') || $matchesRoute('/suggestions/mine'))) {
        authMiddleware();
        $controller = new SuggestionController();
        $controller->getMine();
    }
    elseif ($method === 'GET' && ($matchesRoute('/api/admin/suggestions') || $matchesRoute('/admin/suggestions'))) {
        authMiddleware();
        rbacMiddleware(['ADMIN']);
        $controller = new SuggestionController();
        $controller->getAll();
    }
    elseif ($method === 'PUT' && $matchesPattern('#^/(api/)?admin/suggestions/(\d+)$#', $matches)) {
        authMiddleware();
        rbacMiddleware(['ADMIN']);
        $controller = new SuggestionController();
        $controller->update($matches[2]);
    }
    
    else {
        Response::error('Route not found', 404);
    }
} catch (Throwable $e) {
    Response::error($e->getMessage(), 500);
}

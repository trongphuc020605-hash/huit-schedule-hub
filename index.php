<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $reqPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    $fullPath = realpath(__DIR__ . $reqPath);
    $assetsDir = realpath(__DIR__ . '/assets');
    if ($fullPath !== false && $assetsDir !== false && str_starts_with($fullPath, $assetsDir) && is_file($fullPath)) {
        return false;
    }
}

require_once __DIR__ . '/config/bootstrap.php';

use HuitSchedule\Controllers\ApiController;
use HuitSchedule\Controllers\AuthController;
use HuitSchedule\Controllers\BranchController;
use HuitSchedule\Controllers\DashboardController;
use HuitSchedule\Controllers\PortalController;
use HuitSchedule\Controllers\ScheduleController;
use HuitSchedule\Controllers\TeacherController;
use HuitSchedule\Core\Auth;
use HuitSchedule\Core\Flash;
use HuitSchedule\Core\Response;
use HuitSchedule\Core\Router;

$router = new Router();

// Register RBAC middlewares
$router->registerMiddleware('auth', static function (): void {
    if (!Auth::check()) {
        if (str_starts_with(current_path(), '/api/')) {
            Response::jsonError('Vui lòng đăng nhập để sử dụng API.', 401);
        }
        Flash::info('Vui lòng đăng nhập để tiếp tục.');
        Response::redirect('/login');
    }
});

$router->registerMiddleware('admin', static function (): void {
    if (!Auth::check()) {
        if (str_starts_with(current_path(), '/api/')) {
            Response::jsonError('Vui lòng đăng nhập để sử dụng API.', 401);
        }
        Flash::info('Vui lòng đăng nhập bằng tài khoản Quản trị viên.');
        Response::redirect('/login');
    }
    if (!Auth::isAdmin()) {
        if (str_starts_with(current_path(), '/api/')) {
            Response::jsonError('Bạn không có quyền thực hiện thao tác quản trị này.', 403);
        }
        Flash::error('Khu vực này chỉ dành cho Cán bộ Đào tạo (Admin).');
        Response::redirect('/portal');
    }
});

// Landing route
$router->get('/', static function (): void {
    if (!Auth::check()) {
        Response::redirect('/login');
    }
    Response::redirect(Auth::isAdmin() ? '/dashboard' : '/portal');
});

// Authentication
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);

// Admin Dashboard
$router->get('/dashboard', [DashboardController::class, 'index'], ['admin']);

// Branches & Travel Time Matrix (Admin)
$router->get('/branches', [BranchController::class, 'index'], ['admin']);
$router->post('/branches', [BranchController::class, 'store'], ['admin']);
$router->post('/branches/travel', [BranchController::class, 'updateTravel'], ['admin']);
$router->post('/branches/update/{id}', [BranchController::class, 'update'], ['admin']);
$router->post('/branches/delete/{id}', [BranchController::class, 'destroy'], ['admin']);

// Teachers & Availabilities (Admin)
$router->get('/teachers', [TeacherController::class, 'index'], ['admin']);
$router->post('/teachers', [TeacherController::class, 'store'], ['admin']);
$router->post('/teachers/update/{id}', [TeacherController::class, 'update'], ['admin']);
$router->post('/teachers/delete/{id}', [TeacherController::class, 'destroy'], ['admin']);
$router->post('/teachers/{id}/token', [TeacherController::class, 'regenerateToken'], ['admin']);
$router->post('/teachers/{id}/availabilities', [TeacherController::class, 'storeAvailability'], ['admin']);
$router->post('/teachers/{id}/availabilities/delete/{availId}', [TeacherController::class, 'deleteAvailability'], ['admin']);

// Schedules & Interactive Matrix (Admin)
$router->get('/schedules', [ScheduleController::class, 'index'], ['admin']);
$router->get('/schedules/export-csv', [ScheduleController::class, 'exportCsv'], ['admin']);
$router->get('/schedules/create', [ScheduleController::class, 'create'], ['admin']);
$router->post('/schedules', [ScheduleController::class, 'store'], ['admin']);
$router->get('/schedules/edit/{id}', [ScheduleController::class, 'edit'], ['admin']);
$router->post('/schedules/update/{id}', [ScheduleController::class, 'update'], ['admin']);
$router->post('/schedules/auto-fix/{id}', [ScheduleController::class, 'autoFix'], ['admin']);
$router->post('/schedules/delete/{id}', [ScheduleController::class, 'destroy'], ['admin']);
$router->get('/schedules/matrix', [ScheduleController::class, 'matrix'], ['admin']);

// Teacher Personal Portal (Session Auth OR Read-only Personal Token)
$router->get('/portal', [PortalController::class, 'index']);
$router->get('/portal/export-ics', [PortalController::class, 'exportIcs']);
$router->post('/portal/availabilities', [PortalController::class, 'storeAvailability'], ['auth']);
$router->post('/portal/availabilities/delete/{availId}', [PortalController::class, 'deleteAvailability'], ['auth']);

// JSON APIs (Admin)
$router->get('/api/suggest-teachers', [ApiController::class, 'suggestTeachers'], ['admin']);
$router->get('/api/check-conflict', [ApiController::class, 'checkConflict'], ['admin']);
$router->get('/api/suggest-slots', [ApiController::class, 'suggestSlots'], ['admin']);
$router->get('/api/available-rooms', [ApiController::class, 'availableRooms'], ['admin']);
$router->get('/api/dashboard-stats', [ApiController::class, 'dashboardStats'], ['admin']);

$router->dispatch();

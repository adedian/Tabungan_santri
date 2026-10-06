<?php
declare(strict_types=1);

use App\Controllers\AlumniController;
use App\Controllers\AuditController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\PrintController;
use App\Controllers\PromotionController;
use App\Controllers\ReportController;
use App\Controllers\SavingsController;
use App\Controllers\SettingsController;
use App\Controllers\StudentController;
use App\Controllers\SyncController;
use App\Controllers\SystemController;
use App\Controllers\UserController;

/** @var App\Core\Router $router */

$router->get('/', [HomeController::class, 'index']);
$router->get('/health', [SystemController::class, 'health']);
$router->get('/brand/logo', [SettingsController::class, 'logo']); // publik: dipakai halaman login

// Tamu
$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);

// Pengguna login
$router->group(['middleware' => ['auth']], function ($router) {
    $router->post('/logout', [AuthController::class, 'logout']);
    $router->get('/dashboard', [DashboardController::class, 'index'], ['can:dashboard.view']);
    $router->get('/api/dashboard/summary', [DashboardController::class, 'summary'], ['can:dashboard.view']);
    $router->get('/api/dashboard/activity', [DashboardController::class, 'activity'], ['can:dashboard.view']);
    $router->get('/api/sync', [SyncController::class, 'check']);

    // Audit log
    $router->get('/audit', [AuditController::class, 'index'], ['can:audit.view']);
    $router->get('/api/audit/list', [AuditController::class, 'list'], ['can:audit.view']);

    // Pengaturan (Super Admin)
    $router->get('/pengaturan', [SettingsController::class, 'index'], ['can:settings.manage']);
    $router->put('/api/settings', [SettingsController::class, 'update'], ['can:settings.manage']);

    // Pengguna (Super Admin)
    $router->get('/pengguna', [UserController::class, 'index'], ['can:users.manage']);
    $router->get('/api/users', [UserController::class, 'list'], ['can:users.manage']);
    $router->post('/api/users', [UserController::class, 'store'], ['can:users.manage']);
    $router->put('/api/users/{id:int}', [UserController::class, 'update'], ['can:users.manage']);
    $router->put('/api/users/{id:int}/status', [UserController::class, 'status'], ['can:users.manage']);
    $router->put('/api/users/{id:int}/password', [UserController::class, 'password'], ['can:users.manage']);

    // Cetak
    $router->get('/print/rekap/{id:int}', [PrintController::class, 'rekap'], ['can:savings.view']);
    $router->get('/print/rekap', [PrintController::class, 'batch'], ['can:students.view', 'can:savings.view']);
    $router->get('/print/laporan', [PrintController::class, 'laporan'], ['can:reports.view']);
    $router->post('/api/print/log', [PrintController::class, 'log'], ['can:savings.view']);

    // Laporan
    $router->get('/laporan', [ReportController::class, 'index'], ['can:reports.view']);
    $router->get('/laporan/export', [ReportController::class, 'export'], ['can:reports.export']);
    $router->get('/api/reports/summary', [ReportController::class, 'summary'], ['can:reports.view']);
    $router->get('/api/reports/students', [ReportController::class, 'students'], ['can:reports.view']);

    // Tabungan
    $router->get('/tabungan/tambah', [SavingsController::class, 'create'], ['can:savings.create']);
    $router->post('/api/savings/create', [SavingsController::class, 'store'], ['can:savings.create']);
    $router->get('/tabungan', [SavingsController::class, 'index'], ['can:savings.view']);
    $router->get('/tabungan/santri/{id:int}', [SavingsController::class, 'student'], ['can:savings.view']);
    $router->get('/api/savings/student/{id:int}', [SavingsController::class, 'studentProfile'], ['can:savings.view']);
    $router->get('/api/savings/list', [SavingsController::class, 'list'], ['can:savings.view']);
    $router->put('/api/savings/{id:int}', [SavingsController::class, 'update'], ['can:savings.edit']);
    $router->delete('/api/savings/{id:int}', [SavingsController::class, 'destroy'], ['can:savings.delete']);
    $router->post('/api/savings/bulk-delete', [SavingsController::class, 'bulkDestroy'], ['can:savings.delete']);
    $router->get('/api/savings/balance', [SavingsController::class, 'balance'], ['can:savings.view']);
    $router->get('/api/savings/recent', [SavingsController::class, 'recent'], ['can:savings.view']);

    // Kenaikan kelas (Admin ke atas)
    $router->get('/santri/kenaikan', [PromotionController::class, 'index'], ['can:promotions.manage']);
    $router->get('/api/promotions/candidates', [PromotionController::class, 'candidates'], ['can:promotions.manage']);
    $router->post('/api/promotions', [PromotionController::class, 'process'], ['can:promotions.manage']);

    // Tabungan alumni & rekap alumni
    $router->get('/tabungan/alumni', [AlumniController::class, 'index'], ['can:alumni.view']);
    $router->get('/tabungan/alumni/{id:int}', [AlumniController::class, 'show'], ['can:alumni.view', 'can:savings.view']);
    $router->get('/laporan/alumni', [AlumniController::class, 'rekap'], ['can:alumni.view']);
    $router->get('/api/alumni', [AlumniController::class, 'list'], ['can:alumni.view']);

    // Master santri
    $router->get('/santri', [StudentController::class, 'index'], ['can:students.view']);
    $router->get('/api/students', [StudentController::class, 'list'], ['can:students.view']);
    $router->get('/api/students/search', [StudentController::class, 'search'], ['can:students.view']);
    $router->post('/api/students', [StudentController::class, 'store'], ['can:students.manage']);
    $router->post('/api/students/bulk-delete', [StudentController::class, 'bulkDestroy'], ['can:students.manage']);
    $router->put('/api/students/{id:int}', [StudentController::class, 'update'], ['can:students.manage']);
    $router->put('/api/students/{id:int}/status', [StudentController::class, 'status'], ['can:students.manage']);
});

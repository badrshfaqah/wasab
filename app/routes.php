<?php

/** @var App\Core\Router $router */

use App\Controllers\ActivityLogController;
use App\Controllers\AuthController;
use App\Controllers\CalendarController;
use App\Controllers\CompanyController;
use App\Controllers\DashboardController;
use App\Controllers\ModuleController;
use App\Controllers\NotificationController;
use App\Controllers\ProfileController;
use App\Controllers\RoleController;
use App\Controllers\SettingController;
use App\Controllers\UserController;
use App\Core\Middleware;

// ---------- ضيف ----------
$router->get('/login', [AuthController::class, 'showLogin'], [[Middleware::class, 'guest']]);
$router->post('/login', [AuthController::class, 'login'], [[Middleware::class, 'guest']]);
$router->post('/logout', [AuthController::class, 'logout'], [[Middleware::class, 'auth']]);

// ---------- الرئيسية ----------
$router->get('/', [DashboardController::class, 'index'], [[Middleware::class, 'auth']]);

// ---------- التقويم (يجمع أحداث كل الإضافات المفعّلة) ----------
$router->get('/calendar', [CalendarController::class, 'index'], [[Middleware::class, 'auth']]);

// ---------- الملف الشخصي ----------
$router->get('/profile', [ProfileController::class, 'show'], [[Middleware::class, 'auth']]);
$router->post('/profile', [ProfileController::class, 'update'], [[Middleware::class, 'auth']]);

// ---------- الشركات (مدير النظام فقط) ----------
$router->get('/companies', [CompanyController::class, 'index'], [[Middleware::class, 'auth'], [Middleware::class, 'systemAdmin']]);
$router->get('/companies/create', [CompanyController::class, 'create'], [[Middleware::class, 'auth'], [Middleware::class, 'systemAdmin']]);
$router->post('/companies', [CompanyController::class, 'store'], [[Middleware::class, 'auth'], [Middleware::class, 'systemAdmin']]);
$router->get('/companies/{id}/edit', [CompanyController::class, 'edit'], [[Middleware::class, 'auth'], [Middleware::class, 'systemAdmin']]);
$router->post('/companies/{id}', [CompanyController::class, 'update'], [[Middleware::class, 'auth'], [Middleware::class, 'systemAdmin']]);
$router->post('/companies/{id}/delete', [CompanyController::class, 'destroy'], [[Middleware::class, 'auth'], [Middleware::class, 'systemAdmin']]);

// ---------- المستخدمون ----------
$router->get('/users', [UserController::class, 'index'], [[Middleware::class, 'auth']]);
$router->get('/users/create', [UserController::class, 'create'], [[Middleware::class, 'auth']]);
$router->post('/users', [UserController::class, 'store'], [[Middleware::class, 'auth']]);
$router->get('/users/{id}/edit', [UserController::class, 'edit'], [[Middleware::class, 'auth']]);
$router->post('/users/{id}', [UserController::class, 'update'], [[Middleware::class, 'auth']]);
$router->post('/users/{id}/delete', [UserController::class, 'destroy'], [[Middleware::class, 'auth']]);
$router->post('/users/{id}/impersonate', [UserController::class, 'impersonate'], [[Middleware::class, 'auth'], [Middleware::class, 'systemAdmin']]);
$router->post('/impersonate/stop', [UserController::class, 'stopImpersonate'], [[Middleware::class, 'auth']]);

// ---------- الأدوار والصلاحيات ----------
$router->get('/roles', [RoleController::class, 'index'], [[Middleware::class, 'auth']]);
$router->get('/roles/create', [RoleController::class, 'create'], [[Middleware::class, 'auth']]);
$router->post('/roles', [RoleController::class, 'store'], [[Middleware::class, 'auth']]);
$router->get('/roles/{id}/edit', [RoleController::class, 'edit'], [[Middleware::class, 'auth']]);
$router->post('/roles/{id}', [RoleController::class, 'update'], [[Middleware::class, 'auth']]);
$router->post('/roles/{id}/delete', [RoleController::class, 'destroy'], [[Middleware::class, 'auth']]);

// ---------- الإضافات (مدير النظام فقط) ----------
$router->get('/extensions', [ModuleController::class, 'index'], [[Middleware::class, 'auth'], [Middleware::class, 'systemAdmin']]);
$router->post('/extensions/{key}/install', [ModuleController::class, 'install'], [[Middleware::class, 'auth'], [Middleware::class, 'systemAdmin']]);
$router->post('/extensions/{key}/activate', [ModuleController::class, 'activate'], [[Middleware::class, 'auth'], [Middleware::class, 'systemAdmin']]);
$router->post('/extensions/{key}/deactivate', [ModuleController::class, 'deactivate'], [[Middleware::class, 'auth'], [Middleware::class, 'systemAdmin']]);
$router->post('/extensions/{key}/update', [ModuleController::class, 'update'], [[Middleware::class, 'auth'], [Middleware::class, 'systemAdmin']]);
$router->post('/extensions/{key}/remove', [ModuleController::class, 'remove'], [[Middleware::class, 'auth'], [Middleware::class, 'systemAdmin']]);
$router->post('/extensions/update-core-database', [ModuleController::class, 'updateDatabase'], [[Middleware::class, 'auth'], [Middleware::class, 'systemAdmin']]);

// ---------- الإعدادات ----------
$router->get('/settings', [SettingController::class, 'index'], [[Middleware::class, 'auth']]);
$router->post('/settings', [SettingController::class, 'update'], [[Middleware::class, 'auth']]);

// ---------- الإشعارات ----------
$router->get('/notifications', [NotificationController::class, 'index'], [[Middleware::class, 'auth']]);
$router->post('/notifications/read', [NotificationController::class, 'markRead'], [[Middleware::class, 'auth']]);
$router->post('/notifications/read-all', [NotificationController::class, 'markAllRead'], [[Middleware::class, 'auth']]);

// ---------- سجل العمليات ----------
$router->get('/activity-log', [ActivityLogController::class, 'index'], [[Middleware::class, 'auth']]);

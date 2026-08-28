<?php

/** @var App\Core\Router $router */

use App\Controllers\ActivityLogController;
use App\Controllers\AuthController;
use App\Controllers\CalendarController;
use App\Controllers\CompanyController;
use App\Controllers\CronController;
use App\Controllers\DashboardController;
use App\Controllers\InstallController;
use App\Controllers\ModuleController;
use App\Controllers\PaletteController;
use App\Controllers\NotificationController;
use App\Controllers\PrivacyController;
use App\Controllers\ProfileController;
use App\Controllers\PushController;
use App\Controllers\ReportController;
use App\Controllers\RoleController;
use App\Controllers\SearchController;
use App\Controllers\SettingController;
use App\Controllers\UserController;
use App\Controllers\WasabController;
use App\Core\Middleware;

// ---------- ضيف ----------
$router->get('/login', [AuthController::class, 'showLogin'], [[Middleware::class, 'guest']]);
$router->post('/login', [AuthController::class, 'login'], [[Middleware::class, 'guest']]);
$router->post('/logout', [AuthController::class, 'logout'], [[Middleware::class, 'auth']]);

// ---------- الرئيسية ----------
$router->get('/', [DashboardController::class, 'index'], [[Middleware::class, 'auth']]);
$router->post('/dashboard/prefs', [DashboardController::class, 'savePrefs'], [[Middleware::class, 'auth']]);

// ---------- صفحة "وصاب" العامة عن المنتج (بلا تسجيل دخول عمداً - مخصصة للنشر
// كصفحة تعريفية بموقع الشركة، تشمل وصف المنتج وقائمة ميزاته وسجل تحديثاته) ----------
$router->get('/wasab', [WasabController::class, 'index'], []);

// ---------- سياسة خصوصية تطبيق الجوال (بلا تسجيل دخول عمداً - متجر آبل يشترط
// رابطاً عاماً يفتحه المراجع وأي مستخدم قبل التحميل) ----------
$router->get('/privacy', [PrivacyController::class, 'index'], []);

// ---------- التقويم (يجمع أحداث كل الإضافات المفعّلة) ----------
$router->get('/calendar', [CalendarController::class, 'index'], [[Middleware::class, 'auth']]);
$router->post('/calendar/events', [CalendarController::class, 'storeEvent'], [[Middleware::class, 'auth']]);
$router->post('/calendar/events/{id}/delete', [CalendarController::class, 'destroyEvent'], [[Middleware::class, 'auth']]);

// ---------- البحث الموحّد (يجمع نتائج كل الإضافات المفعّلة) ----------
$router->get('/palette/search', [PaletteController::class, 'search'], [[Middleware::class, 'auth']]);
$router->get('/search', [SearchController::class, 'index'], [[Middleware::class, 'auth']]);

// ---------- التقارير (خاصة بمدير الشركة) ----------
$router->get('/reports', [ReportController::class, 'index'], [[Middleware::class, 'auth']]);
$router->get('/reports/monthly', [ReportController::class, 'monthly'], [[Middleware::class, 'auth']]);

// ---------- الملف الشخصي ----------
$router->get('/profile', [ProfileController::class, 'show'], [[Middleware::class, 'auth']]);
$router->post('/profile', [ProfileController::class, 'update'], [[Middleware::class, 'auth']]);
$router->post('/profile/signatures', [ProfileController::class, 'storeSignature'], [[Middleware::class, 'auth']]);
$router->post('/profile/push-prefs', [ProfileController::class, 'updatePushPrefs'], [[Middleware::class, 'auth']]);
$router->post('/profile/signatures/{id}/delete', [ProfileController::class, 'deleteSignature'], [[Middleware::class, 'auth']]);
$router->post('/profile/signatures/{id}/share', [ProfileController::class, 'shareSignature'], [[Middleware::class, 'auth']]);
$router->post('/profile/stamps', [ProfileController::class, 'storeStamp'], [[Middleware::class, 'auth']]);
$router->post('/profile/stamps/{id}/delete', [ProfileController::class, 'deleteStamp'], [[Middleware::class, 'auth']]);
$router->post('/profile/stamps/{id}/share', [ProfileController::class, 'shareStamp'], [[Middleware::class, 'auth']]);

// أختام الشركة (مدير الشركة/النظام) - مكتبة تُربط بقوالب المستندات والنماذج
$router->get('/stamps', [\App\Controllers\StampController::class, 'index'], [[Middleware::class, 'auth']]);
$router->post('/stamps', [\App\Controllers\StampController::class, 'store'], [[Middleware::class, 'auth']]);
$router->post('/stamps/{id}/delete', [\App\Controllers\StampController::class, 'destroy'], [[Middleware::class, 'auth']]);

// دليل تثبيت التطبيق على الجوال (PWA)
$router->get('/get-app', [InstallController::class, 'index'], [[Middleware::class, 'auth']]);

// صفحة «ملفي»: بوابة الموظف الشخصية (تجمع ما يخصّه من الإضافات المفعّلة)
$router->get('/me', [\App\Controllers\MeController::class, 'index'], [[Middleware::class, 'auth']]);

// مركز الموافقات: كل ما ينتظر قرار المستخدم في مكان واحد
$router->get('/approvals', [\App\Controllers\ApprovalsController::class, 'index'], [[Middleware::class, 'auth']]);

// ---------- لوحة مدير النظام (مدير النظام فقط) ----------
$router->get('/admin', [\App\Controllers\AdminController::class, 'dashboard'], [[Middleware::class, 'auth'], [Middleware::class, 'systemAdmin']]);
$router->post('/admin/backup/run', [\App\Controllers\AdminController::class, 'backupRun'], [[Middleware::class, 'auth'], [Middleware::class, 'systemAdmin']]);
$router->post('/admin/backup/full', [\App\Controllers\AdminController::class, 'backupFull'], [[Middleware::class, 'auth'], [Middleware::class, 'systemAdmin']]);
$router->get('/admin/backup/{name}/download', [\App\Controllers\AdminController::class, 'backupDownload'], [[Middleware::class, 'auth'], [Middleware::class, 'systemAdmin']]);

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
$router->post('/extensions/{key}/move-up', [ModuleController::class, 'moveUp'], [[Middleware::class, 'auth'], [Middleware::class, 'systemAdmin']]);
$router->post('/extensions/{key}/move-down', [ModuleController::class, 'moveDown'], [[Middleware::class, 'auth'], [Middleware::class, 'systemAdmin']]);
$router->post('/extensions/update-core-database', [ModuleController::class, 'updateDatabase'], [[Middleware::class, 'auth'], [Middleware::class, 'systemAdmin']]);
$router->post('/extensions/self-update', [ModuleController::class, 'selfUpdate'], [[Middleware::class, 'auth'], [Middleware::class, 'systemAdmin']]);
$router->post('/extensions/finish-update', [ModuleController::class, 'finishUpdate'], [[Middleware::class, 'auth'], [Middleware::class, 'systemAdmin']]);

// ---------- الإعدادات ----------
$router->get('/settings', [SettingController::class, 'index'], [[Middleware::class, 'auth']]);
$router->post('/settings', [SettingController::class, 'update'], [[Middleware::class, 'auth']]);

// ---------- الإشعارات ----------
$router->get('/notifications', [NotificationController::class, 'index'], [[Middleware::class, 'auth']]);
$router->post('/notifications/read', [NotificationController::class, 'markRead'], [[Middleware::class, 'auth']]);
$router->post('/notifications/read-all', [NotificationController::class, 'markAllRead'], [[Middleware::class, 'auth']]);

// ---------- صور الوحدات الحساسة - شعارات الشركات لأي مسجل، والبقية لأعضاء الشركة فقط ----------
$router->get('/media/companies/{file}', [\App\Controllers\MediaController::class, 'serveCompanyLogo'], [[Middleware::class, 'auth']]);
$router->get('/media/{area}/{cid}/{file}', [\App\Controllers\MediaController::class, 'serve'], [[Middleware::class, 'auth']]);

// ---------- ملف تعريف تطبيق الجوال (ديناميكي ليعكس الاسم والشعار المخصصين) ----------
$router->get('/manifest', [\App\Controllers\BrandController::class, 'manifest'], []);
// صور هوية النظام العامة (شعار + أيقونات) عبر PHP - بلا اعتماد على .htaccess
$router->get('/brand/{file}', [\App\Controllers\BrandController::class, 'asset'], []);

// ---------- تشغيل مهام الجدولة عبر الويب (بتوكن، بديل cron CLI) ----------
$router->get('/cron/{token}', [CronController::class, 'run'], []);

// ---------- إشعارات الدفع للجوال (Web Push) ----------
$router->post('/push/subscribe', [PushController::class, 'subscribe'], [[Middleware::class, 'auth']]);
$router->post('/push/unsubscribe', [PushController::class, 'unsubscribe'], [[Middleware::class, 'auth']]);
$router->post('/push/test', [PushController::class, 'test'], [[Middleware::class, 'auth']]);

// ---------- سجل العمليات ----------
$router->get('/activity-log', [ActivityLogController::class, 'index'], [[Middleware::class, 'auth']]);
$router->get('/activity-log/export', [ActivityLogController::class, 'export'], [[Middleware::class, 'auth']]);

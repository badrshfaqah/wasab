<?php

use App\Core\ModuleManager;
use App\Core\Router;
use Modules\Mobileapi\Controllers\ApprovalsApiController;
use Modules\Mobileapi\Controllers\ArchiveApiController;
use Modules\Mobileapi\Controllers\AuthApiController;
use Modules\Mobileapi\Controllers\CheckinsApiController;
use Modules\Mobileapi\Controllers\DocumentsApiController;
use Modules\Mobileapi\Controllers\EmployeeCardApiController;
use Modules\Mobileapi\Controllers\HomeApiController;
use Modules\Mobileapi\Controllers\InboxApiController;
use Modules\Mobileapi\Controllers\MeetingsApiController;
use Modules\Mobileapi\Controllers\NotificationsApiController;
use Modules\Mobileapi\Controllers\ProfileApiController;
use Modules\Mobileapi\Controllers\TasksApiController;
use Modules\Mobileapi\Controllers\TodayApiController;
use Modules\Mobileapi\Support\Api;

/**
 * مسارات واجهة تطبيق الجوال (JSON فقط). كلها تحت /api/v1.
 * المصادقة عبر توكن Bearer (ميدلوير Api::auth) - لا جلسات ولا CSRF.
 * ملاحظة: المسارات الثابتة مسجّلة قبل مسارات {id} لأن الموجّه يطابق بترتيب التسجيل.
 */
return function (Router $router): void {
    $auth = [Api::class, 'auth'];

    // يمنع الوصول لنقاط وحدة معينة إذا عطّلها مدير النظام.
    $requireModule = function (string $key): callable {
        return function () use ($key): bool {
            if (!ModuleManager::isActive($key)) {
                Api::error('هذه الوحدة غير مفعلة حالياً.', 404, 'module_inactive');
                return false;
            }
            return true;
        };
    };

    // ---------- المصادقة ----------
    $router->post('/api/v1/auth/login', [AuthApiController::class, 'login'], []);
    $router->post('/api/v1/auth/logout', [AuthApiController::class, 'logout'], [$auth]);
    $router->get('/api/v1/auth/me', [AuthApiController::class, 'me'], [$auth]);
    $router->post('/api/v1/auth/push-token', [AuthApiController::class, 'pushToken'], [$auth]);

    // ---------- الرئيسية ----------
    $router->get('/api/v1/dashboard', [HomeApiController::class, 'index'], [$auth]);

    // ---------- يومي + البحث الموحد + التقويم ----------
    $router->get('/api/v1/today', [TodayApiController::class, 'index'], [$auth]);
    $router->get('/api/v1/search', [TodayApiController::class, 'search'], [$auth]);
    $router->get('/api/v1/calendar', [TodayApiController::class, 'calendar'], [$auth]);

    // ---------- الإشعارات ----------
    $router->get('/api/v1/notifications', [NotificationsApiController::class, 'index'], [$auth]);
    $router->post('/api/v1/notifications/read', [NotificationsApiController::class, 'markRead'], [$auth]);
    $router->post('/api/v1/notifications/read-all', [NotificationsApiController::class, 'markAllRead'], [$auth]);

    // ---------- الملف الشخصي ----------
    $router->post('/api/v1/profile', [ProfileApiController::class, 'update'], [$auth]);
    $router->get('/api/v1/profile/devices', [ProfileApiController::class, 'devices'], [$auth]);
    $router->post('/api/v1/profile/devices/{id}/revoke', [ProfileApiController::class, 'revokeDevice'], [$auth]);

    // ---------- مركز الموافقات ----------
    // يجمع كل ما ينتظر قرار المستخدم من الوحدات المفعّلة. البتّ متاح هنا لما
    // لا شاشة له في التطبيق (إجازات، مصروفات، خطابات)؛ والبقية تُفتح بشاشاتها.
    $router->get('/api/v1/approvals', [ApprovalsApiController::class, 'index'], [$auth]);
    $router->post('/api/v1/approvals/{type}/{id}/decide', [ApprovalsApiController::class, 'decide'], [$auth]);

    // ---------- أرشيف الملفات ----------
    $archive = [$auth, $requireModule('archive')];
    $router->get('/api/v1/archive', [ArchiveApiController::class, 'index'], $archive);
    $router->post('/api/v1/archive', [ArchiveApiController::class, 'store'], $archive);
    $router->get('/api/v1/archive/{id}/file', [ArchiveApiController::class, 'download'], $archive);
    $router->get('/api/v1/archive/{id}', [ArchiveApiController::class, 'show'], $archive);

    // ---------- البطاقة الشخصية ----------
    $card = [$auth, $requireModule('employees')];
    $router->get('/api/v1/employee-card/photo', [EmployeeCardApiController::class, 'minePhoto'], $card);
    $router->get('/api/v1/employee-card/logo', [EmployeeCardApiController::class, 'logo'], $card);
    $router->get('/api/v1/employee-card/pass', [EmployeeCardApiController::class, 'pass'], $card);
    $router->get('/api/v1/employee-card/{id}/photo', [EmployeeCardApiController::class, 'photo'], $card);
    $router->get('/api/v1/employee-card/{id}', [EmployeeCardApiController::class, 'show'], $card);
    $router->get('/api/v1/employee-card', [EmployeeCardApiController::class, 'mine'], $card);

    // ---------- المهام ----------
    $tasks = [$auth, $requireModule('tasks')];
    $router->get('/api/v1/tasks', [TasksApiController::class, 'index'], $tasks);
    $router->get('/api/v1/tasks/users', [TasksApiController::class, 'companyUsers'], $tasks);
    $router->post('/api/v1/tasks', [TasksApiController::class, 'store'], $tasks);
    $router->get('/api/v1/tasks/{id}', [TasksApiController::class, 'show'], $tasks);
    $router->post('/api/v1/tasks/{id}', [TasksApiController::class, 'update'], $tasks);
    $router->post('/api/v1/tasks/{id}/delete', [TasksApiController::class, 'destroy'], $tasks);
    $router->post('/api/v1/tasks/{id}/status', [TasksApiController::class, 'changeStatus'], $tasks);
    $router->post('/api/v1/tasks/{id}/approve', [TasksApiController::class, 'approve'], $tasks);
    $router->post('/api/v1/tasks/{id}/comments', [TasksApiController::class, 'comment'], $tasks);
    $router->post('/api/v1/tasks/{id}/subtasks', [TasksApiController::class, 'addSubtask'], $tasks);
    $router->post('/api/v1/tasks/{id}/subtasks/{subtaskId}/toggle', [TasksApiController::class, 'toggleSubtask'], $tasks);
    $router->post('/api/v1/tasks/{id}/subtasks/{subtaskId}/delete', [TasksApiController::class, 'deleteSubtask'], $tasks);
    $router->post('/api/v1/tasks/{id}/attachments', [TasksApiController::class, 'uploadAttachment'], $tasks);
    $router->get('/api/v1/tasks/{id}/attachments/{attachmentId}', [TasksApiController::class, 'downloadAttachment'], $tasks);

    // ---------- مركز المراسلات ----------
    $inbox = [$auth, $requireModule('inbox')];
    $router->get('/api/v1/inbox', [InboxApiController::class, 'index'], $inbox);
    $router->get('/api/v1/inbox/{id}', [InboxApiController::class, 'show'], $inbox);
    $router->post('/api/v1/inbox/{id}/read', [InboxApiController::class, 'setRead'], $inbox);
    $router->post('/api/v1/inbox/{id}/delete', [InboxApiController::class, 'destroy'], $inbox);

    // ---------- الاجتماعات ----------
    $meetings = [$auth, $requireModule('meetings')];
    $router->get('/api/v1/meetings', [MeetingsApiController::class, 'index'], $meetings);
    $router->get('/api/v1/meetings/users', [MeetingsApiController::class, 'companyUsers'], $meetings);
    $router->post('/api/v1/meetings', [MeetingsApiController::class, 'store'], $meetings);
    $router->get('/api/v1/meetings/{id}', [MeetingsApiController::class, 'show'], $meetings);
    $router->post('/api/v1/meetings/{id}', [MeetingsApiController::class, 'update'], $meetings);
    $router->post('/api/v1/meetings/{id}/delete', [MeetingsApiController::class, 'destroy'], $meetings);
    $router->post('/api/v1/meetings/{id}/respond', [MeetingsApiController::class, 'respond'], $meetings);
    $router->post('/api/v1/meetings/{id}/notes', [MeetingsApiController::class, 'addNote'], $meetings);
    $router->post('/api/v1/meetings/{id}/notes/{noteId}/task', [MeetingsApiController::class, 'noteToTask'], $meetings);
    $router->post('/api/v1/meetings/{id}/outcomes', [MeetingsApiController::class, 'updateOutcomes'], $meetings);
    $router->post('/api/v1/meetings/{id}/status', [MeetingsApiController::class, 'changeStatus'], $meetings);

    // ---------- المستندات ----------
    $documents = [$auth, $requireModule('documents')];
    $router->get('/api/v1/documents', [DocumentsApiController::class, 'index'], $documents);
    $router->get('/api/v1/documents/users', [DocumentsApiController::class, 'companyUsers'], $documents);
    $router->get('/api/v1/documents/templates', [DocumentsApiController::class, 'templates'], $documents);
    $router->get('/api/v1/documents/stamps', [DocumentsApiController::class, 'stamps'], $documents);
    $router->get('/api/v1/documents/signatures', [DocumentsApiController::class, 'signatures'], $documents);
    $router->post('/api/v1/documents', [DocumentsApiController::class, 'store'], $documents);
    $router->get('/api/v1/documents/{id}', [DocumentsApiController::class, 'show'], $documents);
    $router->get('/api/v1/documents/{id}/paper', [DocumentsApiController::class, 'paper'], $documents);
    $router->post('/api/v1/documents/{id}', [DocumentsApiController::class, 'update'], $documents);
    $router->post('/api/v1/documents/{id}/delete', [DocumentsApiController::class, 'destroy'], $documents);
    $router->post('/api/v1/documents/{id}/duplicate', [DocumentsApiController::class, 'duplicate'], $documents);
    $router->post('/api/v1/documents/{id}/status', [DocumentsApiController::class, 'updateStatus'], $documents);
    $router->post('/api/v1/documents/{id}/comments', [DocumentsApiController::class, 'comment'], $documents);
    $router->post('/api/v1/documents/{id}/share', [DocumentsApiController::class, 'share'], $documents);
    $router->post('/api/v1/documents/{id}/share/{userId}/unshare', [DocumentsApiController::class, 'unshare'], $documents);
    $router->get('/api/v1/documents/{id}/versions/{versionId}', [DocumentsApiController::class, 'viewVersion'], $documents);
    $router->get('/api/v1/documents/{id}/versions/{versionId}/diff', [DocumentsApiController::class, 'versionDiff'], $documents);
    $router->post('/api/v1/documents/{id}/versions/{versionId}/restore', [DocumentsApiController::class, 'restoreVersion'], $documents);

    // ---------- التحضير ----------
    $checkins = [$auth, $requireModule('checkins')];
    $router->get('/api/v1/checkins', [CheckinsApiController::class, 'index'], $checkins);
    $router->post('/api/v1/checkins', [CheckinsApiController::class, 'store'], $checkins);
    $router->get('/api/v1/checkins/attendance', [CheckinsApiController::class, 'attendance'], $checkins);
    $router->post('/api/v1/checkins/attendance/in', [CheckinsApiController::class, 'attendanceIn'], $checkins);
    $router->post('/api/v1/checkins/attendance/out', [CheckinsApiController::class, 'attendanceOut'], $checkins);
    $router->get('/api/v1/checkins/team', [CheckinsApiController::class, 'team'], $checkins);
    $router->post('/api/v1/checkins/blocker-to-task', [CheckinsApiController::class, 'blockerToTask'], $checkins);
};

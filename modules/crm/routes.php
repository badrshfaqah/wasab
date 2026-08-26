<?php

use App\Core\Middleware;
use App\Core\Router;
use Modules\Crm\Controllers\OrganizationController;
use Modules\Crm\Controllers\WorkspaceController;

/**
 * مسارات CRM. الترتيب مقصود: الثابتة قبل ذوات {id}، ومسار المساحة يبدأ بـ /crm/w/
 * حتى لا يلتبس برقم مساحة. (لا يوجد مجلد باسم crm في جذر الموقع فلا تعارض).
 */
return function (Router $router): void {
    $auth = [Middleware::class, 'auth'];

    // المساحات
    $router->get('/crm', [WorkspaceController::class, 'index'], [$auth]);
    $router->get('/crm/directory', [OrganizationController::class, 'directory'], [$auth]);
    $router->get('/crm/workspaces/create', [WorkspaceController::class, 'create'], [$auth]);
    $router->post('/crm/workspaces', [WorkspaceController::class, 'store'], [$auth]);

    $router->get('/crm/w/{id}/edit', [WorkspaceController::class, 'edit'], [$auth]);
    $router->post('/crm/w/{id}/edit', [WorkspaceController::class, 'update'], [$auth]);
    $router->post('/crm/w/{id}/archive', [WorkspaceController::class, 'toggleArchive'], [$auth]);
    $router->get('/crm/w/{id}/members', [WorkspaceController::class, 'members'], [$auth]);
    $router->post('/crm/w/{id}/members', [WorkspaceController::class, 'saveMember'], [$auth]);
    $router->post('/crm/w/{id}/members/{userId}/remove', [WorkspaceController::class, 'removeMember'], [$auth]);

    // الجهات داخل المساحة
    $router->get('/crm/w/{id}/orgs/create', [OrganizationController::class, 'create'], [$auth]);
    $router->post('/crm/w/{id}/orgs', [OrganizationController::class, 'store'], [$auth]);
    $router->post('/crm/w/{id}/orgs/link', [OrganizationController::class, 'link'], [$auth]);
    $router->get('/crm/w/{id}/orgs/{orgId}', [OrganizationController::class, 'show'], [$auth]);
    $router->post('/crm/w/{id}/orgs/{orgId}', [OrganizationController::class, 'update'], [$auth]);
    $router->post('/crm/w/{id}/orgs/{orgId}/unlink', [OrganizationController::class, 'unlink'], [$auth]);

    // لوحة المساحة (أخيراً حتى لا تلتقط المسارات الثابتة أعلاه)
    $router->get('/crm/w/{id}', [OrganizationController::class, 'index'], [$auth]);
};

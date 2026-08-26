<?php

use App\Core\Middleware;
use App\Core\Router;
use Modules\Crm\Controllers\ActivityController;
use Modules\Crm\Controllers\OpportunityController;
use Modules\Crm\Controllers\OrganizationController;
use Modules\Crm\Controllers\ListController;
use Modules\Crm\Controllers\PipelineController;
use Modules\Crm\Controllers\SettingsController;
use Modules\Crm\Controllers\TodayController;
use Modules\Crm\Controllers\WorkspaceController;

/**
 * مسارات CRM. الترتيب مقصود: الثابتة قبل ذوات {id}، ومسار المساحة يبدأ بـ /crm/w/
 * حتى لا يلتبس برقم مساحة. (لا يوجد مجلد باسم crm في جذر الموقع فلا تعارض).
 */
return function (Router $router): void {
    $auth = [Middleware::class, 'auth'];

    // المساحات
    $router->get('/crm', [WorkspaceController::class, 'index'], [$auth]);
    $router->get('/crm/today', [TodayController::class, 'index'], [$auth]);
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

    // الأنشطة وسجل العلاقة وجهات الاتصال
    $router->post('/crm/w/{id}/orgs/{orgId}/activities', [ActivityController::class, 'store'], [$auth]);
    $router->post('/crm/w/{id}/activities/{activityId}/done', [ActivityController::class, 'completeFollowUp'], [$auth]);
    $router->post('/crm/w/{id}/activities/{activityId}/delete', [ActivityController::class, 'deleteActivity'], [$auth]);
    $router->post('/crm/w/{id}/orgs/{orgId}/contacts', [ActivityController::class, 'storeContact'], [$auth]);
    $router->post('/crm/w/{id}/orgs/{orgId}/contacts/{contactId}/delete', [ActivityController::class, 'deleteContact'], [$auth]);

    // الفرص ومراحل العمل
    $router->get('/crm/w/{id}/opportunities/create', [OpportunityController::class, 'create'], [$auth]);
    $router->post('/crm/w/{id}/opportunities', [OpportunityController::class, 'store'], [$auth]);
    $router->get('/crm/w/{id}/opportunities/{oppId}', [OpportunityController::class, 'show'], [$auth]);
    $router->post('/crm/w/{id}/opportunities/{oppId}', [OpportunityController::class, 'update'], [$auth]);
    $router->post('/crm/w/{id}/opportunities/{oppId}/move', [OpportunityController::class, 'move'], [$auth]);
    $router->post('/crm/w/{id}/opportunities/{oppId}/delete', [OpportunityController::class, 'destroy'], [$auth]);
    $router->get('/crm/w/{id}/opportunities', [OpportunityController::class, 'index'], [$auth]);

    $router->get('/crm/w/{id}/pipelines', [PipelineController::class, 'index'], [$auth]);
    $router->post('/crm/w/{id}/pipelines', [PipelineController::class, 'storePipeline'], [$auth]);
    $router->post('/crm/w/{id}/stages', [PipelineController::class, 'storeStage'], [$auth]);
    $router->post('/crm/w/{id}/stages/{stageId}', [PipelineController::class, 'updateStage'], [$auth]);

    // التصنيفات والوسوم والقوائم
    $router->get('/crm/w/{id}/settings', [SettingsController::class, 'index'], [$auth]);
    $router->post('/crm/w/{id}/categories', [SettingsController::class, 'storeCategory'], [$auth]);
    $router->post('/crm/w/{id}/categories/{categoryId}', [SettingsController::class, 'updateCategory'], [$auth]);
    $router->post('/crm/w/{id}/tags/{tagId}/delete', [SettingsController::class, 'deleteTag'], [$auth]);
    $router->post('/crm/w/{id}/orgs/{orgId}/tags', [SettingsController::class, 'tagOrganization'], [$auth]);

    $router->get('/crm/w/{id}/lists', [ListController::class, 'index'], [$auth]);
    $router->post('/crm/w/{id}/lists', [ListController::class, 'store'], [$auth]);
    $router->get('/crm/w/{id}/lists/{listId}', [ListController::class, 'show'], [$auth]);
    $router->post('/crm/w/{id}/lists/{listId}/items', [ListController::class, 'items'], [$auth]);
    $router->post('/crm/w/{id}/lists/{listId}/delete', [ListController::class, 'destroy'], [$auth]);

    // فتح جهة بمعرّفها فقط (من مهمة مرتبطة مثلاً) - يحوّل لأول مساحة يصلها المستخدم
    $router->get('/crm/orgs/{orgId}', [OrganizationController::class, 'resolve'], [$auth]);

    // لوحة المساحة (أخيراً حتى لا تلتقط المسارات الثابتة أعلاه)
    $router->get('/crm/w/{id}', [OrganizationController::class, 'index'], [$auth]);
};

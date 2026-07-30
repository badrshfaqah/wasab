<?php

use App\Core\Middleware;
use App\Core\Router;
use Modules\Inbox\Controllers\InboxApiController;
use Modules\Inbox\Controllers\InboxController;
use Modules\Inbox\Controllers\InboxSiteController;

/**
 * يُحمَّل فقط عندما تكون إضافة مركز المراسلات مفعّلة. لا علاقة له بملفات النواة.
 * مسارات /inbox/sites مسجّلة قبل /inbox/{id} حتى لا يلتقطها الموجّه كمعرّف رسالة.
 *
 * POST /api/inbox بلا middleware مصادقة عمداً - نقطة استقبال الرسائل من المواقع
 * الخارجية، والتحقق فيها بمفتاح API خاص بكل موقع (بنفس أسلوب رابط RSVP العام
 * بالاجتماعات وروابط مشاركة الأرشيف المؤقتة).
 */
return function (Router $router): void {
    $auth = [Middleware::class, 'auth'];

    $router->post('/api/inbox', [InboxApiController::class, 'receive'], []);

    $router->get('/inbox', [InboxController::class, 'index'], [$auth]);
    $router->get('/inbox/export/{format}', [InboxController::class, 'export'], [$auth]);

    $router->get('/inbox/sites', [InboxSiteController::class, 'index'], [$auth]);
    $router->get('/inbox/sites/create', [InboxSiteController::class, 'create'], [$auth]);
    $router->post('/inbox/sites', [InboxSiteController::class, 'store'], [$auth]);
    $router->get('/inbox/sites/{id}/edit', [InboxSiteController::class, 'edit'], [$auth]);
    $router->post('/inbox/sites/{id}', [InboxSiteController::class, 'update'], [$auth]);
    $router->post('/inbox/sites/{id}/regenerate', [InboxSiteController::class, 'regenerate'], [$auth]);
    $router->post('/inbox/sites/{id}/toggle', [InboxSiteController::class, 'toggle'], [$auth]);
    $router->post('/inbox/sites/{id}/delete', [InboxSiteController::class, 'destroy'], [$auth]);

    $router->get('/inbox/{id}', [InboxController::class, 'show'], [$auth]);
    $router->post('/inbox/{id}/toggle-read', [InboxController::class, 'toggleRead'], [$auth]);
    $router->post('/inbox/{id}/delete', [InboxController::class, 'destroy'], [$auth]);
};

<?php

use App\Core\Middleware;
use App\Core\Router;
use Modules\Meetings\Controllers\MeetingController;
use Modules\Meetings\Controllers\MeetingRsvpController;

/**
 * يُحمَّل فقط عندما تكون إضافة الاجتماعات مفعّلة. لا علاقة له بملفات النواة.
 * /meetings/create مسجّل قبل /meetings/{id} حتى لا يلتقطها الموجّه كمعرّف اجتماع.
 *
 * /meetings/rsvp/{token} بلا middleware مصادقة عمداً - رابط تأكيد حضور للمدعوين
 * الخارجيين الذين لا يملكون حساباً بالنظام، بنفس أسلوب رابط مشاركة الأرشيف المؤقت
 * (توكن عشوائي طويل غير قابل للتخمين).
 */
return function (Router $router): void {
    $auth = [Middleware::class, 'auth'];

    $router->get('/meetings', [MeetingController::class, 'index'], [$auth]);
    $router->get('/meetings/create', [MeetingController::class, 'create'], [$auth]);
    $router->post('/meetings', [MeetingController::class, 'store'], [$auth]);

    $router->get('/meetings/rsvp/{token}', [MeetingRsvpController::class, 'show'], []);
    $router->post('/meetings/rsvp/{token}', [MeetingRsvpController::class, 'respond'], []);

    $router->get('/meetings/{id}', [MeetingController::class, 'show'], [$auth]);
    $router->get('/meetings/{id}/edit', [MeetingController::class, 'edit'], [$auth]);
    $router->post('/meetings/{id}', [MeetingController::class, 'update'], [$auth]);
    $router->post('/meetings/{id}/delete', [MeetingController::class, 'destroy'], [$auth]);
    $router->post('/meetings/{id}/respond', [MeetingController::class, 'respond'], [$auth]);
    $router->post('/meetings/{id}/attendees/{attendeeId}/respond', [MeetingController::class, 'respondFor'], [$auth]);
    $router->post('/meetings/{id}/notes', [MeetingController::class, 'addNote'], [$auth]);
    $router->post('/meetings/{id}/outcomes', [MeetingController::class, 'updateOutcomes'], [$auth]);
    $router->post('/meetings/{id}/status', [MeetingController::class, 'statusAction'], [$auth]);
};

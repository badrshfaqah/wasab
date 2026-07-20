<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\WebPush;

/**
 * اشتراك/إلغاء اشتراك إشعارات الدفع (Web Push) لجهاز المستخدم الحالي.
 * تُستدعى بـ fetch من assets/js/push.js بجسم JSON، والرد JSON دائماً.
 */
class PushController
{
    public function subscribe(): void
    {
        $data = $this->jsonInput();
        if (!Csrf::verify((string) ($data['_csrf'] ?? ''))) {
            $this->respond(419, ['success' => false, 'error' => 'انتهت صلاحية الجلسة، أعد تحميل الصفحة.']);
            return;
        }

        $endpoint = (string) ($data['endpoint'] ?? '');
        $p256dh = (string) ($data['keys']['p256dh'] ?? '');
        $auth = (string) ($data['keys']['auth'] ?? '');

        if (!WebPush::saveSubscription(Auth::id(), $endpoint, $p256dh, $auth)) {
            $this->respond(422, ['success' => false, 'error' => 'بيانات اشتراك غير صالحة.']);
            return;
        }

        $this->respond(200, ['success' => true]);
    }

    /**
     * إرسال تنبيه تجريبي للمستخدم الحالي على كل أجهزته المفعّلة - أداة تحقق ذاتي:
     * إن وصل الإشعار للجوال فالسلسلة كاملة تعمل (اشتراك، تشفير، خدمة الدفع).
     */
    public function test(): void
    {
        if (!\App\Core\Csrf::verify((string) \App\Core\Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/profile');
        }

        if (!WebPush::userHasSubscriptions(Auth::id())) {
            flash_set('error', 'لا يوجد أي جهاز مفعّل للتنبيهات على حسابك بعد - فعّل التنبيهات أولاً من الزر بالأسفل.');
            redirect('/profile');
        }

        \App\Core\Notification::send(
            Auth::id(),
            '🔔 تنبيه تجريبي من وصاب',
            'ممتاز! التنبيهات تصل لجهازك بنجاح.',
            route('/profile')
        );

        flash_set('success', 'أُرسل التنبيه التجريبي - يجب أن يظهر على جهازك خلال ثوانٍ (تأكد أن التطبيق مغلق أو بالخلفية لرؤيته كإشعار).');
        redirect('/profile');
    }

    public function unsubscribe(): void
    {
        $data = $this->jsonInput();
        if (!Csrf::verify((string) ($data['_csrf'] ?? ''))) {
            $this->respond(419, ['success' => false, 'error' => 'انتهت صلاحية الجلسة، أعد تحميل الصفحة.']);
            return;
        }

        WebPush::deleteSubscription(Auth::id(), (string) ($data['endpoint'] ?? ''));
        $this->respond(200, ['success' => true]);
    }

    private function jsonInput(): array
    {
        $decoded = json_decode(file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function respond(int $code, array $data): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}

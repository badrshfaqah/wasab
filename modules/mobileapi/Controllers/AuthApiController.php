<?php

namespace Modules\Mobileapi\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Database;
use Modules\Mobileapi\Support\Api;

class AuthApiController
{
    /**
     * POST /api/v1/auth/login  {email, password, device_name?}
     * يعيد التوكن الخام (يُعرض مرة واحدة) + بيانات المستخدم وصلاحياته.
     * نعيد استخدام Auth::attempt نفسها فنرث قفل المحاولات الفاشلة كما بالويب.
     */
    public function login(): void
    {
        Api::mergeJsonBody();

        $email = trim((string) Api::input('email', ''));
        $password = (string) Api::input('password', '');
        if ($email === '' || $password === '') {
            Api::error('أدخل البريد الإلكتروني وكلمة المرور.', 422, 'validation');
        }

        $result = Auth::attempt($email, $password);
        if ($result === 'locked') {
            Api::error('الحساب مقفل مؤقتاً بسبب محاولات فاشلة متكررة، حاول بعد 15 دقيقة.', 423, 'locked');
        }
        if ($result !== 'success') {
            Api::error('بيانات الدخول غير صحيحة.', 401, 'invalid_credentials');
        }

        $user = Auth::user();
        $token = Api::issueToken((int) $user['id'], (string) Api::input('device_name', 'iPhone'), 'ios');

        ActivityLog::log('mobileapi.login', 'user', (string) $user['id'], 'تسجيل دخول من تطبيق الجوال');

        Api::ok([
            'token' => $token,
            'user' => Api::userPayload($user),
        ]);
    }

    /** POST /api/v1/auth/logout - يبطل توكن الجهاز الحالي فقط. */
    public function logout(): void
    {
        $row = Api::tokenRow();
        if ($row) {
            Database::update('mobile_api_tokens', ['revoked_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $row['id']]);
        }
        Api::ok(['message' => 'تم تسجيل الخروج.']);
    }

    /** GET /api/v1/auth/me - بيانات المستخدم الحالي (تُستدعى عند فتح التطبيق). */
    public function me(): void
    {
        Api::ok(['user' => Api::userPayload(Api::user())]);
    }

    /** POST /api/v1/auth/push-token  {push_token} - يربط توكن إشعارات الجهاز بتوكن الجلسة. */
    public function pushToken(): void
    {
        $row = Api::tokenRow();
        $pushToken = trim((string) Api::input('push_token', ''));
        if ($row && $pushToken !== '') {
            Database::update('mobile_api_tokens', ['push_token' => mb_substr($pushToken, 0, 255)], 'id = :id', ['id' => $row['id']]);
        }
        Api::ok(['message' => 'تم الحفظ.']);
    }
}

<?php

namespace Modules\Mobileapi\Controllers;

use App\Core\ActivityLog;
use App\Core\Database;
use App\Core\Validator;
use Modules\Mobileapi\Support\Api;

class ProfileApiController
{
    /** POST /api/v1/profile  {name, timezone?, password?} - نفس قواعد ProfileController الويب. */
    public function update(): void
    {
        $user = Api::user();

        $name = trim((string) Api::input('name', ''));
        $validator = Validator::make(['name' => $name], ['name' => 'required|max:150']);
        if ($validator->fails()) {
            Api::error('الاسم مطلوب (بحد أقصى 150 حرفاً).', 422, 'validation');
        }

        $timezone = trim((string) Api::input('timezone', ''));
        if ($timezone !== '' && !in_array($timezone, timezone_identifiers_list(), true)) {
            Api::error('منطقة زمنية غير صحيحة.', 422, 'validation');
        }

        $update = [
            'name' => $name,
            'timezone' => $timezone !== '' ? $timezone : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $password = (string) Api::input('password', '');
        if ($password !== '') {
            if (strlen($password) < 8) {
                Api::error('كلمة المرور الجديدة يجب ألا تقل عن 8 أحرف.', 422, 'validation');
            }
            $update['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        Database::update('users', $update, 'id = :id', ['id' => $user['id']]);
        ActivityLog::log('profile.update', 'user', (string) $user['id'], 'تحديث الملف الشخصي من الجوال');

        Api::ok(['message' => 'تم حفظ التغييرات.']);
    }

    /** GET /api/v1/profile/devices - أجهزة الجوال المرتبطة بالحساب. */
    public function devices(): void
    {
        $user = Api::user();
        $current = Api::tokenRow();

        $rows = Database::select(
            'SELECT id, device_name, platform, last_used_at, created_at
               FROM mobile_api_tokens
              WHERE user_id = :u AND revoked_at IS NULL
              ORDER BY last_used_at DESC, id DESC',
            ['u' => $user['id']]
        );

        Api::ok(['devices' => array_map(fn ($r) => [
            'id' => (int) $r['id'],
            'device_name' => $r['device_name'],
            'platform' => $r['platform'],
            'last_used_at' => $r['last_used_at'],
            'created_at' => $r['created_at'],
            'is_current' => $current && (int) $r['id'] === (int) $current['id'],
        ], $rows)]);
    }

    /** POST /api/v1/profile/devices/{id}/revoke - تسجيل خروج جهاز آخر. */
    public function revokeDevice(array $params): void
    {
        $user = Api::user();
        Database::update(
            'mobile_api_tokens',
            ['revoked_at' => date('Y-m-d H:i:s')],
            'id = :id AND user_id = :u',
            ['id' => (int) $params['id'], 'u' => $user['id']]
        );
        Api::ok(['message' => 'تم تسجيل خروج الجهاز.']);
    }
}

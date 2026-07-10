<?php

namespace Modules\Phone\Models;

use App\Core\Database;

class PhoneUser
{
    public static function forUser(int $userId): ?array
    {
        return Database::first('SELECT * FROM phone_users WHERE user_id = :u', ['u' => $userId]);
    }

    public static function save(int $userId, string $extension, ?string $secret, bool $enabled): void
    {
        $existing = self::forUser($userId);

        if ($existing) {
            $update = ['extension' => $extension, 'enabled' => $enabled ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s')];
            if ($secret !== null && $secret !== '') {
                $update['secret'] = $secret;
            }
            Database::update('phone_users', $update, 'id = :id', ['id' => $existing['id']]);
            return;
        }

        Database::insert('phone_users', [
            'user_id' => $userId,
            'extension' => $extension,
            'secret' => (string) $secret,
            'enabled' => $enabled ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

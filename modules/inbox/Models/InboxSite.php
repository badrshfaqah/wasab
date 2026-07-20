<?php

namespace Modules\Inbox\Models;

use App\Core\Database;

class InboxSite
{
    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM inbox_sites WHERE id = :id', ['id' => $id]);
    }

    /**
     * التحقق من مفتاح API الوارد من موقع خارجي: يرجع الموقع فقط إن كان المفتاح
     * صحيحاً والموقع مفعّلاً. المفتاح يحدد الموقع والشركة معاً، فلا حاجة لأي
     * معرّف إضافي بالطلب.
     */
    public static function findByApiKey(string $apiKey): ?array
    {
        if (strlen($apiKey) !== 64 || !ctype_xdigit($apiKey)) {
            return null;
        }
        return Database::first(
            'SELECT * FROM inbox_sites WHERE api_key = :k AND status = "active"',
            ['k' => $apiKey]
        );
    }

    public static function forCompany(int $companyId): array
    {
        return Database::select(
            'SELECT s.*,
                    (SELECT COUNT(*) FROM inbox_messages m WHERE m.site_id = s.id) AS messages_count,
                    (SELECT COUNT(*) FROM inbox_messages m WHERE m.site_id = s.id AND m.is_read = 0) AS unread_count
               FROM inbox_sites s
              WHERE s.company_id = :c
              ORDER BY s.name',
            ['c' => $companyId]
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('inbox_sites', $data);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('inbox_sites', $data, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('inbox_sites', 'id = :id', ['id' => $id]);
    }

    /** مفتاح عشوائي 64 خانة hex غير قابل للتخمين، بنفس أسلوب توكنات مشاركة الأرشيف. */
    public static function generateApiKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function touchLastMessage(int $id): void
    {
        Database::update('inbox_sites', ['last_message_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
    }
}

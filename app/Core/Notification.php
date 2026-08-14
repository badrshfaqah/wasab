<?php

namespace App\Core;

class Notification
{
    public static function send(int $userId, string $title, string $message = '', string $url = ''): void
    {
        Database::insert('notifications', [
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // نسخة دفع لجوال/متصفح المستخدم إن كان مفعّلاً التنبيهات - أفضل جهد،
        // أي فشل يُسجَّل بصمت داخل WebPush ولا يؤثر على العملية الأصلية.
        // تفضيلات المستخدم قد تُسكِت فئة معيّنة عن الجوال (الإشعار الداخلي يصل دائماً).
        if (self::pushAllowed($userId, $url)) {
            WebPush::sendToUser($userId, $title, $message, $url);
        }
    }

    /** فئات إشعارات الجوال المتاحة للتفضيل (المفتاح => التسمية). */
    public static function pushCategories(): array
    {
        return [
            'tasks' => 'المهام',
            'meetings' => 'الاجتماعات',
            'inbox' => 'مركز المراسلات',
            'assets' => 'العهد والأصول',
            'documents' => 'المستندات والخطابات',
            'employees' => 'الملف الوظيفي والإجازات',
            'other' => 'أخرى',
        ];
    }

    /** يستنتج فئة الإشعار من رابطه (لمطابقة تفضيلات المستخدم). */
    public static function pushCategory(string $url): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        $map = [
            '/tasks' => 'tasks',
            '/meetings' => 'meetings',
            '/inbox' => 'inbox',
            '/custody' => 'assets',
            '/documents' => 'documents',
            '/forms' => 'documents',
            '/employees' => 'employees',
            '/checkins' => 'employees',
        ];
        foreach ($map as $prefix => $category) {
            if (str_contains($path, $prefix)) {
                return $category;
            }
        }
        return 'other';
    }

    /** هل يسمح المستخدم بإشعار جوال لهذه الفئة؟ (لا تفضيلات = الكل مسموح). */
    private static function pushAllowed(int $userId, string $url): bool
    {
        try {
            $row = Database::first('SELECT push_prefs FROM users WHERE id = :id', ['id' => $userId]);
        } catch (\Throwable $e) {
            return true; // عمود التفضيلات غير موجود بعد (قبل الترقية) - لا نمنع شيئاً
        }
        if (!$row || empty($row['push_prefs'])) {
            return true;
        }
        $prefs = json_decode((string) $row['push_prefs'], true);
        if (!is_array($prefs)) {
            return true;
        }
        $category = self::pushCategory($url);
        return !array_key_exists($category, $prefs) || $prefs[$category] !== false;
    }

    public static function unreadCount(int $userId): int
    {
        return Database::count('notifications', 'user_id = :u AND is_read = 0', ['u' => $userId]);
    }

    public static function recent(int $userId, int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));
        return Database::select(
            "SELECT * FROM notifications WHERE user_id = :u ORDER BY created_at DESC LIMIT {$limit}",
            ['u' => $userId]
        );
    }

    public static function markRead(int $userId, int $id): void
    {
        Database::update('notifications', ['is_read' => 1], 'id = :id AND user_id = :u', ['id' => $id, 'u' => $userId]);
    }

    public static function markAllRead(int $userId): void
    {
        Database::update('notifications', ['is_read' => 1], 'user_id = :u AND is_read = 0', ['u' => $userId]);
    }
}

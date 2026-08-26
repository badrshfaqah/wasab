<?php

namespace App\Core;

/**
 * مجموعة أيقونات موحّدة (خطية، بلون النص، 20×20).
 *
 * الإيموجي تُرسم بعوائل مختلفة على كل جهاز فتبدو القائمة مجمّعة لا مصمَّمة:
 * أوزان وألوان وأحجام متضاربة. هذه المجموعة أحادية اللون بسماكة واحدة، ترث لون
 * النص فتتناسق مع أي ثيمة، وتبقى الإيموجي بديلاً لأي عنصر بلا أيقونة معروفة.
 */
class Icons
{
    /** مسارات الأيقونات - stroke بلا fill ليتناسق الوزن. */
    private const PATHS = [
        'home' => '<path d="M3 10.5 12 4l9 6.5V19a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'check-circle' => '<circle cx="12" cy="12" r="8.5"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
        'user' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.3 3.1-5.5 7-5.5s7 2.2 7 5.5"/>',
        'pin' => '<path d="M9 3h6M12 3v7M8 10h8l1.5 5H6.5zM12 15v6"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M8 3v4M16 3v4M3.5 10h17"/>',
        'inbox' => '<path d="M3.5 12.5 6 5h12l2.5 7.5V19a1 1 0 0 1-1 1H4.5a1 1 0 0 1-1-1z"/><path d="M3.5 12.5H9l1 2.5h4l1-2.5h5.5"/>',
        'meeting' => '<circle cx="8.5" cy="9" r="2.75"/><circle cx="16" cy="9.5" r="2.25"/><path d="M3.5 19c0-2.8 2.2-4.5 5-4.5s5 1.7 5 4.5M14.5 19c0-2.3 1.4-3.8 3-3.8s3 1.1 3 3.3"/>',
        'tasks' => '<rect x="5" y="4.5" width="14" height="16" rx="2"/><path d="M9 3.5h6v2.5H9zM8.5 11l1.75 1.75L14 9M8.5 16h6"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'handshake' => '<path d="M3.5 11 7 7.5h4l2 2 2-2h4L21 11M6 13l3.5 4 2-2 2 2 3.5-4"/>',
        'contacts' => '<rect x="4" y="3.5" width="14" height="17" rx="2"/><path d="M20 7.5v9M11 11.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM8 16.5c0-1.7 1.3-2.75 3-2.75s3 1.05 3 2.75"/>',
        'document' => '<path d="M14 3.5H7a1.5 1.5 0 0 0-1.5 1.5v14A1.5 1.5 0 0 0 7 20.5h10a1.5 1.5 0 0 0 1.5-1.5V8z"/><path d="M14 3.5V8h4.5M9 12.5h6M9 16h4"/>',
        'form' => '<rect x="4.5" y="3.5" width="15" height="17" rx="2"/><path d="M8.5 8h7M8.5 12h7M8.5 16h4"/>',
        'archive' => '<rect x="3.5" y="5" width="17" height="4" rx="1"/><path d="M5 9v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9M10 13h4"/>',
        'box' => '<path d="M12 3.5 4 7v10l8 3.5 8-3.5V7z"/><path d="M4 7l8 3.5L20 7M12 10.5v10"/>',
        'badge' => '<rect x="3.5" y="5" width="17" height="14" rx="2"/><path d="M9 3.5v3M15 3.5v3"/><circle cx="9.5" cy="12" r="1.75"/><path d="M13.5 10.5h4M13.5 14h3M6.5 16c0-1.2 1.3-2 3-2s3 .8 3 2"/>',
        'wallet' => '<path d="M4 7.5A2 2 0 0 1 6 5.5h11a1.5 1.5 0 0 1 1.5 1.5v1.5"/><rect x="4" y="7.5" width="16.5" height="12" rx="2"/><circle cx="16" cy="13.5" r="1.25"/>',
        'phone' => '<path d="M7.5 3.5h9a1.5 1.5 0 0 1 1.5 1.5v14a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 6 19V5a1.5 1.5 0 0 1 1.5-1.5z"/><path d="M10.5 17.5h3"/>',
        'chart' => '<path d="M4 20V4M4 20h16M8 17v-5M12.5 17V8M17 17v-7"/>',
        'shield' => '<path d="M12 3.5 5 6v5.5c0 4 2.9 7.4 7 8.5 4.1-1.1 7-4.5 7-8.5V6z"/><path d="m9 12 2 2 4-4"/>',
        'users' => '<circle cx="9.5" cy="8.5" r="3"/><path d="M3.5 19c0-3 2.7-5 6-5s6 2 6 5"/><path d="M16 6.2a3 3 0 0 1 0 5.6M17.5 14.5c1.9.6 3 1.9 3 4.5"/>',
        'stamp' => '<path d="M8 10.5c0-3 .5-4 .5-5.5A3.5 3.5 0 0 1 12 2a3.5 3.5 0 0 1 3.5 3c0 1.5.5 2.5.5 5.5"/><path d="M5 13.5h14v3H5zM6.5 16.5v3.5h11v-3.5"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 3.5v2M12 18.5v2M4.9 7.5l1.7 1M17.4 15.5l1.7 1M4.9 16.5l1.7-1M17.4 8.5l1.7-1"/>',
        'scroll' => '<path d="M6 4.5h10.5A1.5 1.5 0 0 1 18 6v13.5H7.5A1.5 1.5 0 0 1 6 18z"/><path d="M18 8h2.5M9 9h6M9 12.5h6M9 16h3"/>',
        'puzzle' => '<path d="M9.5 4.5h5v2.2a1.8 1.8 0 1 0 3.5 0V9h2v5.5h-2.2a1.8 1.8 0 1 0 0 3.5H19v2H5.5v-5.2a1.8 1.8 0 1 1 0-3.5V4.5z"/>',
        'tools' => '<path d="M14.5 6.5a3.5 3.5 0 0 1 4.8 4.4l-9 9-4.2-4.2 9-9z"/><path d="M4 20l2.5-.5"/>',
        'building' => '<rect x="5" y="3.5" width="14" height="17" rx="1.5"/><path d="M9 7.5h2M13 7.5h2M9 11.5h2M13 11.5h2M10.5 20.5v-4h3v4"/>',
        'mobile' => '<rect x="7" y="3" width="10" height="18" rx="2"/><path d="M10.5 6.5h3M11 18h2"/>',
        'palm' => '<path d="M12 20V11M12 11c-2-2.5-5-3-6.5-1.5M12 11c2-2.5 5-3 6.5-1.5M12 11c0-3 1.5-5 4-5.5M12 11c0-3-1.5-5-4-5.5"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/>',
        'command' => '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M9 10h6M9 14h4"/>',
        'chevron' => '<path d="m9 5 7 7-7 7"/>',
    ];

    /** خريطة المسار → اسم الأيقونة، فتُستنتج تلقائياً بلا تعديل كل إضافة. */
    private const BY_PATH = [
        '/' => 'home', '/approvals' => 'check-circle', '/me' => 'user', '/calendar' => 'calendar',
        '/inbox' => 'inbox', '/meetings' => 'meeting', '/tasks' => 'tasks', '/checkins' => 'clock',
        '/crm' => 'handshake', '/crm/today' => 'pin', '/contacts' => 'contacts',
        '/documents' => 'document', '/forms' => 'form', '/archive' => 'archive',
        '/custody' => 'box', '/employees' => 'badge', '/employees/leaves' => 'palm', '/expenses' => 'wallet',
        '/phone/settings' => 'phone', '/phone/contacts' => 'contacts', '/phone/admin' => 'settings',
        '/reports' => 'chart', '/users' => 'users', '/roles' => 'shield', '/stamps' => 'stamp',
        '/settings' => 'settings', '/activity-log' => 'scroll', '/extensions' => 'puzzle',
        '/admin' => 'tools', '/companies' => 'building', '/get-app' => 'mobile',
    ];

    public static function has(string $name): bool
    {
        return isset(self::PATHS[$name]);
    }

    /** اسم الأيقونة المناسبة لمسار، أو null إن لم تُعرف. */
    public static function forPath(string $path): ?string
    {
        $path = '/' . trim(parse_url($path, PHP_URL_PATH) ?: '', '/');
        return self::BY_PATH[$path] ?? null;
    }

    public static function svg(string $name, int $size = 20, string $class = 'ic-svg'): string
    {
        if (!isset(self::PATHS[$name])) {
            return '';
        }
        return '<svg class="' . e($class) . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24"'
            . ' fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"'
            . ' aria-hidden="true" focusable="false">' . self::PATHS[$name] . '</svg>';
    }
}

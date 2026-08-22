<?php

namespace Modules\Mobileapi\Support;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ModuleManager;
use App\Core\Permission;
use App\Core\Session;

/**
 * أدوات مشتركة لواجهة تطبيق الجوال: ردود JSON موحّدة، قراءة جسم الطلب JSON،
 * ومصادقة التوكن (Bearer). التوكن يُخزَّن كـ SHA-256 فقط، وعند نجاح التحقق
 * نضبط user_id في الجلسة الحالية فتعمل Auth::user() وPermission::check()
 * وكل كود النواة والإضافات كما هو دون أي تعديل عليها.
 */
class Api
{
    private static bool $jsonBodyMerged = false;

    // ---------- الردود ----------

    public static function ok(array $data = [], int $status = 200): void
    {
        self::respond(['ok' => true, 'data' => $data], $status);
    }

    public static function error(string $message, int $status = 400, string $code = 'error'): void
    {
        self::respond(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], $status);
    }

    private static function respond(array $payload, int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // ---------- جسم الطلب ----------

    /** يقرأ مدخلاً من JSON body أو POST أو GET (بهذا الترتيب). */
    public static function input(string $key, $default = null)
    {
        self::mergeJsonBody();
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    /** يدمج جسم الطلب JSON داخل $_POST مرة واحدة، فيستفيد منه حتى كود النواة. */
    public static function mergeJsonBody(): void
    {
        if (self::$jsonBodyMerged) {
            return;
        }
        self::$jsonBodyMerged = true;

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') === false) {
            return;
        }
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) {
            return;
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $_POST = array_merge($_POST, $decoded);
        }
    }

    // ---------- التوكنات ----------

    public static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($header === '' && function_exists('apache_request_headers')) {
            $headers = array_change_key_case(apache_request_headers(), CASE_LOWER);
            $header = $headers['authorization'] ?? '';
        }
        if (preg_match('/^Bearer\s+(\S+)$/i', trim((string) $header), $m)) {
            return $m[1];
        }
        return null;
    }

    /** ينشئ توكناً جديداً للمستخدم ويعيد قيمته الخام (تُعرض مرة واحدة فقط). */
    public static function issueToken(int $userId, string $deviceName, string $platform = 'ios'): string
    {
        $token = bin2hex(random_bytes(32));
        Database::insert('mobile_api_tokens', [
            'user_id' => $userId,
            'token_hash' => hash('sha256', $token),
            'device_name' => mb_substr($deviceName, 0, 190),
            'platform' => mb_substr($platform, 0, 30),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return $token;
    }

    /** صف التوكن الصالح الحالي (بعد المصادقة) - يُملأ في auth(). */
    private static ?array $tokenRow = null;

    public static function tokenRow(): ?array
    {
        return self::$tokenRow;
    }

    // ---------- ميدلوير المصادقة ----------

    /**
     * ميدلوير: يتحقق من توكن Bearer ويحمّل المستخدم في الجلسة الحالية.
     * يعيد false (بعد إرسال رد JSON 401) عند الفشل فيوقف الموجّه التنفيذ.
     */
    public static function auth(): bool
    {
        self::mergeJsonBody();

        $token = self::bearerToken();
        if (!$token) {
            self::error('يتطلب تسجيل الدخول.', 401, 'unauthenticated');
            return false;
        }

        $row = Database::first(
            'SELECT * FROM mobile_api_tokens WHERE token_hash = :h AND revoked_at IS NULL LIMIT 1',
            ['h' => hash('sha256', $token)]
        );
        if (!$row) {
            self::error('انتهت الجلسة، سجّل الدخول من جديد.', 401, 'unauthenticated');
            return false;
        }

        $user = Database::first(
            'SELECT * FROM users WHERE id = :id AND status = "active" LIMIT 1',
            ['id' => $row['user_id']]
        );
        if (!$user) {
            self::error('الحساب غير نشط.', 401, 'unauthenticated');
            return false;
        }

        // تحديث "آخر استخدام" بتهدئة دقيقة حتى لا نكتب مع كل طلب.
        if (empty($row['last_used_at']) || strtotime($row['last_used_at']) < time() - 60) {
            Database::update('mobile_api_tokens', ['last_used_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $row['id']]);
        }

        Session::set('user_id', (int) $user['id']);
        self::$tokenRow = $row;
        return true;
    }

    // ---------- مساعدات مشتركة ----------

    /** تمثيل المستخدم الحالي كما يراه التطبيق (بلا حقول حساسة). */
    public static function userPayload(array $user): array
    {
        $company = null;
        if (!empty($user['company_id'])) {
            $row = Database::first('SELECT id, name FROM companies WHERE id = :id', ['id' => $user['company_id']]);
            if ($row) {
                $company = ['id' => (int) $row['id'], 'name' => $row['name']];
            }
        }

        return [
            'id' => (int) $user['id'],
            'name' => $user['name'] ?? '',
            'email' => $user['email'] ?? '',
            'phone' => $user['phone'] ?? null,
            'job_title' => $user['job_title'] ?? null,
            'membership_type' => $user['membership_type'] ?? 'member',
            'company' => $company,
            'permissions' => Permission::userPermissionKeys((int) $user['id']),
            'active_modules' => array_values(array_filter(
                array_keys(ModuleManager::installedRows()),
                fn (string $key) => ModuleManager::isActive($key)
            )),
        ];
    }

    /** فحص صلاحية مع رد 403 موحّد. */
    public static function requirePermission(string $key): void
    {
        if (!Permission::check($key)) {
            self::error('لا تملك صلاحية تنفيذ هذا الإجراء.', 403, 'forbidden');
        }
    }

    /** المستخدم الحالي أو 401 (تُستدعى بعد ميدلوير auth فلا تفشل عملياً). */
    public static function user(): array
    {
        $user = Auth::user();
        if (!$user) {
            self::error('يتطلب تسجيل الدخول.', 401, 'unauthenticated');
        }
        return $user;
    }
}

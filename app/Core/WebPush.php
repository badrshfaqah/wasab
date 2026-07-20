<?php

namespace App\Core;

/**
 * إشعارات دفع للمتصفح/الجوال (Web Push) بـ PHP خام بدون Composer أو مكتبات خارجية،
 * التزاماً بفلسفة النظام. تنفيذ كامل لمعيارين:
 *
 * - VAPID (RFC 8292): توقيع JWT بخوارزمية ES256 عبر openssl، بمفتاح EC P-256 يولَّد
 *   مرة واحدة ويُخزَّن في settings (المفتاح الخاص PEM + العام كنقطة 65 بايت base64url).
 * - تشفير الحمولة aes128gcm (RFC 8291/8188): ECDH عبر openssl_pkey_derive ثم
 *   HKDF عبر hash_hkdf ثم AES-128-GCM.
 *
 * المتطلبات متوفرة في PHP 8 قياسياً: openssl (بدوال EC) + hash_hkdf + curl.
 * كل الإرسال "أفضل جهد": أي فشل يُسجَّل بصمت ولا يكسر الطلب الأصلي أبداً،
 * والاشتراكات الميتة (410/404 من خدمة الدفع) تُحذف تلقائياً.
 */
class WebPush
{
    private const VAPID_SUBJECT = 'mailto:info@almgrat.com';
    private const CURL_TIMEOUT_MS = 3000;
    private const MAX_SUBSCRIPTIONS_PER_USER = 8;

    // ---------------------------------------------------------------
    // مفاتيح VAPID
    // ---------------------------------------------------------------

    /** المفتاح العام (base64url لنقطة EC غير مضغوطة 65 بايت) - يولّد الزوج عند أول طلب. */
    public static function publicKey(): string
    {
        $public = Setting::get('webpush_vapid_public', null, '');
        if ($public !== '') {
            return $public;
        }
        return self::generateKeys()['public'];
    }

    private static function privateKeyPem(): string
    {
        $pem = Setting::get('webpush_vapid_private', null, '');
        if ($pem !== '') {
            return $pem;
        }
        return self::generateKeys()['private'];
    }

    private static function generateKeys(): array
    {
        $key = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if ($key === false) {
            throw new \RuntimeException('تعذر توليد مفاتيح VAPID (openssl EC غير متاح).');
        }
        openssl_pkey_export($key, $privatePem);
        $details = openssl_pkey_get_details($key);
        $publicRaw = "\x04" . self::pad32($details['ec']['x']) . self::pad32($details['ec']['y']);
        $public = self::b64url($publicRaw);

        Setting::set('webpush_vapid_private', $privatePem);
        Setting::set('webpush_vapid_public', $public);

        return ['private' => $privatePem, 'public' => $public];
    }

    // ---------------------------------------------------------------
    // إدارة الاشتراكات
    // ---------------------------------------------------------------

    /** حفظ/تحديث اشتراك جهاز. الاشتراك مرتبط بالمستخدم الحالي، وفريد بالـ endpoint. */
    public static function saveSubscription(int $userId, string $endpoint, string $p256dh, string $auth): bool
    {
        if (!str_starts_with($endpoint, 'https://') || strlen($endpoint) > 500 || $p256dh === '' || $auth === '') {
            return false;
        }

        $hash = sha1($endpoint);
        $existing = Database::first('SELECT id FROM push_subscriptions WHERE endpoint_hash = :h', ['h' => $hash]);
        if ($existing) {
            Database::update('push_subscriptions', [
                'user_id' => $userId,
                'p256dh' => $p256dh,
                'auth' => $auth,
            ], 'id = :id', ['id' => $existing['id']]);
            return true;
        }

        // حد أقصى للأجهزة لكل مستخدم: عند التجاوز نحذف الأقدم.
        $count = Database::count('push_subscriptions', 'user_id = :u', ['u' => $userId]);
        if ($count >= self::MAX_SUBSCRIPTIONS_PER_USER) {
            Database::pdo()->prepare('DELETE FROM push_subscriptions WHERE user_id = ? ORDER BY id ASC LIMIT 1')->execute([$userId]);
        }

        Database::insert('push_subscriptions', [
            'user_id' => $userId,
            'endpoint' => $endpoint,
            'endpoint_hash' => $hash,
            'p256dh' => $p256dh,
            'auth' => $auth,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return true;
    }

    public static function deleteSubscription(int $userId, string $endpoint): void
    {
        Database::delete('push_subscriptions', 'user_id = :u AND endpoint_hash = :h', ['u' => $userId, 'h' => sha1($endpoint)]);
    }

    public static function userHasSubscriptions(int $userId): bool
    {
        return Database::count('push_subscriptions', 'user_id = :u', ['u' => $userId]) > 0;
    }

    // ---------------------------------------------------------------
    // الإرسال
    // ---------------------------------------------------------------

    /**
     * إرسال إشعار دفع لكل أجهزة مستخدم. أفضل جهد: لا يرمي استثناءات أبداً،
     * ويُنظّف الاشتراكات الميتة. يُستدعى من Notification::send().
     */
    public static function sendToUser(int $userId, string $title, string $message = '', string $url = ''): void
    {
        try {
            $subs = Database::select(
                'SELECT * FROM push_subscriptions WHERE user_id = :u ORDER BY id DESC',
                ['u' => $userId]
            );
            if (!$subs) {
                return;
            }

            $payload = json_encode([
                'title' => $title,
                'body' => $message,
                'url' => $url,
            ], JSON_UNESCAPED_UNICODE);

            foreach ($subs as $sub) {
                self::sendToSubscription($sub, $payload);
            }
        } catch (\Throwable $e) {
            log_exception($e);
        }
    }

    private static function sendToSubscription(array $sub, string $payload): void
    {
        try {
            $endpoint = $sub['endpoint'];
            $audience = self::origin($endpoint);
            $jwt = self::vapidJwt($audience);
            $body = self::encryptPayload($payload, $sub['p256dh'], $sub['auth']);

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => [
                    'Authorization: vapid t=' . $jwt . ', k=' . self::publicKey(),
                    'Content-Encoding: aes128gcm',
                    'Content-Type: application/octet-stream',
                    'Content-Length: ' . strlen($body),
                    'TTL: 86400',
                    'Urgency: normal',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT_MS => self::CURL_TIMEOUT_MS,
                CURLOPT_TIMEOUT_MS => self::CURL_TIMEOUT_MS,
            ]);
            curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            // اشتراك منتهٍ أو محذوف من المتصفح: نظّفه حتى لا نعيد المحاولة للأبد.
            if ($status === 404 || $status === 410) {
                Database::delete('push_subscriptions', 'id = :id', ['id' => $sub['id']]);
            }
        } catch (\Throwable $e) {
            log_exception($e);
        }
    }

    // ---------------------------------------------------------------
    // VAPID JWT (ES256)
    // ---------------------------------------------------------------

    private static function vapidJwt(string $audience): string
    {
        $header = self::b64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::b64url(json_encode([
            'aud' => $audience,
            'exp' => time() + 43200,
            'sub' => self::VAPID_SUBJECT,
        ]));
        $signingInput = $header . '.' . $claims;

        $privateKey = openssl_pkey_get_private(self::privateKeyPem());
        if (!openssl_sign($signingInput, $derSignature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('فشل توقيع VAPID JWT.');
        }

        return $signingInput . '.' . self::b64url(self::derSignatureToRaw($derSignature));
    }

    /** تحويل توقيع ECDSA من صيغة DER إلى الصيغة الخام r||s (64 بايت) المطلوبة بـ JWT. */
    private static function derSignatureToRaw(string $der): string
    {
        $offset = 2;
        if ((ord($der[1]) & 0x80) !== 0) {
            $offset += ord($der[1]) & 0x7F;
        }

        $readInt = function () use ($der, &$offset): string {
            if (ord($der[$offset]) !== 0x02) {
                throw new \RuntimeException('توقيع DER غير صالح.');
            }
            $len = ord($der[$offset + 1]);
            $value = substr($der, $offset + 2, $len);
            $offset += 2 + $len;
            $value = ltrim($value, "\x00");
            return str_pad($value, 32, "\x00", STR_PAD_LEFT);
        };

        $r = $readInt();
        $s = $readInt();
        return $r . $s;
    }

    // ---------------------------------------------------------------
    // تشفير الحمولة aes128gcm (RFC 8291 + RFC 8188)
    // ---------------------------------------------------------------

    private static function encryptPayload(string $payload, string $p256dhB64, string $authB64): string
    {
        $uaPublic = self::b64urlDecode($p256dhB64);   // مفتاح المتصفح العام (65 بايت)
        $authSecret = self::b64urlDecode($authB64);   // سر المصادقة (16 بايت)
        if (strlen($uaPublic) !== 65 || strlen($authSecret) !== 16) {
            throw new \RuntimeException('بيانات اشتراك الدفع غير صالحة.');
        }

        // زوج مفاتيح مؤقت لخادم التطبيق لكل رسالة
        $asKey = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        $asDetails = openssl_pkey_get_details($asKey);
        $asPublic = "\x04" . self::pad32($asDetails['ec']['x']) . self::pad32($asDetails['ec']['y']);

        // ECDH: السر المشترك مع مفتاح المتصفح
        $peerKey = openssl_pkey_get_public(self::spkiPemFromPoint($uaPublic));
        $sharedSecret = openssl_pkey_derive($peerKey, $asKey, 32);
        if ($sharedSecret === false) {
            throw new \RuntimeException('فشل اشتقاق سر ECDH.');
        }

        // سلسلة اشتقاق المفاتيح حسب RFC 8291
        $prk = hash_hkdf('sha256', $sharedSecret, 32, "WebPush: info\x00" . $uaPublic . $asPublic, $authSecret);
        $salt = random_bytes(16);
        $cek = hash_hkdf('sha256', $prk, 16, "Content-Encoding: aes128gcm\x00", $salt);
        $nonce = hash_hkdf('sha256', $prk, 12, "Content-Encoding: nonce\x00", $salt);

        // سجل واحد أخير: الحمولة + فاصل 0x02 (RFC 8188)
        $tag = '';
        $ciphertext = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ciphertext === false) {
            throw new \RuntimeException('فشل تشفير حمولة الدفع.');
        }

        // ترويسة aes128gcm الثنائية: salt(16) + rs(4) + idlen(1) + as_public(65) + السجل
        return $salt . pack('N', 4096) . chr(65) . $asPublic . $ciphertext . $tag;
    }

    /** بناء PEM لمفتاح EC عام من نقطة P-256 غير مضغوطة (ترويسة SPKI ثابتة للمنحنى). */
    private static function spkiPemFromPoint(string $point): string
    {
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $point;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    // ---------------------------------------------------------------
    // أدوات
    // ---------------------------------------------------------------

    private static function origin(string $url): string
    {
        $parts = parse_url($url);
        return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
    }

    private static function pad32(string $bin): string
    {
        return str_pad($bin, 32, "\x00", STR_PAD_LEFT);
    }

    private static function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), false);
        return $decoded === false ? '' : $decoded;
    }
}

<?php

namespace Modules\Mobileapi\Support;

use App\Core\Setting;

/**
 * بناء بطاقة Apple Wallet (.pkpass) وتوقيعها.
 *
 * ملف pkpass هو أرشيف ZIP يحوي: pass.json (وصف البطاقة)، والصور، و
 * manifest.json (بصمة SHA-1 لكل ملف)، و signature (توقيع PKCS#7 منفصل على
 * manifest بشهادة Pass Type ID من حساب أبل للمطورين).
 *
 * لا تعمل بلا شهادة: تُقرأ من `config.php` تحت المفتاح `wallet`. حين لا
 * تكون مهيّأة يخفي التطبيق الزر أصلاً بدل أن يُظهره ويفشل.
 */
final class ApplePass
{
    /** لون الهوية حين لا يكون للشركة لون. */
    private const FALLBACK_COLOR = '#1A756B';

    /** هل الخادم مهيّأ لإصدار بطاقات المحفظة؟ */
    public static function isConfigured(): bool
    {
        $config = self::config();

        return $config['pass_type_identifier'] !== ''
            && $config['team_identifier'] !== ''
            && is_file($config['certificate'])
            && is_file($config['wwdr']);
    }

    /**
     * يبني بطاقة موقّعة ويعيد بايتاتها.
     *
     * @throws \RuntimeException حين تفشل قراءة الشهادة أو التوقيع.
     */
    public static function build(array $employee, array $company, int $companyId, string $vcard, ?string $email): string
    {
        $config = self::config();
        $workDir = self::workDirectory();

        try {
            $files = ['pass.json' => self::passJson($employee, $company, $companyId, $config, $vcard, $email)];
            $files += self::images($employee, $company, $companyId);

            foreach ($files as $name => $bytes) {
                file_put_contents($workDir . '/' . $name, $bytes);
            }

            $manifest = [];
            foreach ($files as $name => $bytes) {
                $manifest[$name] = sha1($bytes);
            }
            $manifestJson = json_encode($manifest, JSON_UNESCAPED_SLASHES);
            file_put_contents($workDir . '/manifest.json', $manifestJson);

            self::sign($workDir . '/manifest.json', $workDir . '/signature', $config);

            return self::zip($workDir, array_keys($files));
        } finally {
            self::removeDirectory($workDir);
        }
    }

    // ------------------------------------------------------------- pass.json

    private static function passJson(
        array $employee,
        array $company,
        int $companyId,
        array $config,
        string $vcard,
        ?string $email
    ): string {
        $companyName = (string) ($company['name'] ?? '');
        $background = self::rgb($company['primary_color'] ?? null);

        $secondary = [];
        if (!empty($employee['job_title'])) {
            $secondary[] = ['key' => 'title', 'label' => 'المسمى الوظيفي', 'value' => $employee['job_title']];
        }
        if (!empty($employee['department'])) {
            $secondary[] = ['key' => 'department', 'label' => 'الإدارة', 'value' => $employee['department']];
        }

        $auxiliary = [];
        if (!empty($employee['phone'])) {
            $auxiliary[] = ['key' => 'phone', 'label' => 'الجوال', 'value' => $employee['phone']];
        }
        if ($email) {
            $auxiliary[] = ['key' => 'email', 'label' => 'البريد', 'value' => $email];
        }

        $back = [];
        if ($companyName !== '') {
            $back[] = ['key' => 'org', 'label' => 'الشركة', 'value' => $companyName];
        }
        $website = self::website($companyId);
        if ($website !== '') {
            $back[] = ['key' => 'website', 'label' => 'الموقع', 'value' => $website];
        }
        if (!empty($employee['hire_date'])) {
            $back[] = ['key' => 'hire', 'label' => 'تاريخ المباشرة', 'value' => $employee['hire_date']];
        }
        $back[] = [
            'key' => 'hint',
            'label' => 'الرمز',
            'value' => 'امسح الرمز خلف البطاقة لحفظ جهة الاتصال كاملة في هاتفك.',
        ];

        $pass = [
            'formatVersion' => 1,
            'passTypeIdentifier' => $config['pass_type_identifier'],
            'teamIdentifier' => $config['team_identifier'],
            // رقم ثابت للموظف: إعادة الإضافة تُحدّث البطاقة نفسها لا تُنشئ ثانية.
            'serialNumber' => 'wasab-' . $companyId . '-' . (int) ($employee['id'] ?? 0),
            'organizationName' => $companyName !== '' ? $companyName : 'وصاب',
            'description' => 'بطاقة موظف' . ($companyName !== '' ? ' — ' . $companyName : ''),
            'logoText' => $companyName,
            'backgroundColor' => $background,
            'foregroundColor' => 'rgb(255, 255, 255)',
            'labelColor' => 'rgb(210, 232, 228)',
            'sharingProhibited' => true,
            'barcodes' => [[
                'format' => 'PKBarcodeFormatQR',
                'message' => $vcard,
                // البطاقة عربية، فالترميز utf-8 لا iso-8859-1.
                'messageEncoding' => 'utf-8',
                'altText' => $employee['full_name'] ?? '',
            ]],
            'generic' => [
                'primaryFields' => [[
                    'key' => 'name',
                    'label' => 'الموظف',
                    'value' => $employee['full_name'] ?? '',
                ]],
                'secondaryFields' => $secondary,
                'auxiliaryFields' => $auxiliary,
                'backFields' => $back,
            ],
        ];

        return json_encode($pass, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** موقع الشركة كما ضبطه مديرها، وإلا عنوان النظام نفسه. */
    public static function website(int $companyId): string
    {
        $website = trim((string) Setting::get('company_website', $companyId, ''));

        return $website !== '' ? $website : rtrim((string) app_url(), '/');
    }

    // ---------------------------------------------------------------- الصور

    /**
     * أبل تشترط icon.png. ونضيف الشعار وصورة الموظف حين توجدان.
     *
     * @return array<string, string>
     */
    private static function images(array $employee, array $company, int $companyId): array
    {
        $images = [];
        $color = $company['primary_color'] ?? self::FALLBACK_COLOR;

        $logoPath = !empty($company['logo'])
            ? BASE_PATH . '/storage/uploads/companies/' . $company['logo']
            : null;

        // الأيقونة: شعار الشركة على مربّع بلون هويتها، وإلا مربّع اللون وحده.
        foreach ([['icon.png', 29], ['icon@2x.png', 58], ['icon@3x.png', 87]] as [$name, $size]) {
            $images[$name] = self::square($logoPath, $size, $color);
        }

        if ($logoPath && is_file($logoPath)) {
            $images['logo.png'] = self::fit($logoPath, 160, 50);
            $images['logo@2x.png'] = self::fit($logoPath, 320, 100);
        }

        $photoPath = !empty($employee['photo'])
            ? BASE_PATH . '/storage/uploads/employees/' . $companyId . '/' . $employee['photo']
            : null;
        if ($photoPath && is_file($photoPath)) {
            $images['thumbnail.png'] = self::square($photoPath, 90, $color, true);
            $images['thumbnail@2x.png'] = self::square($photoPath, 180, $color, true);
        }

        return $images;
    }

    /** مربّع بلون الهوية، وفوقه الصورة إن وُجدت. */
    private static function square(?string $source, int $size, string $hex, bool $cover = false): string
    {
        $canvas = imagecreatetruecolor($size, $size);
        [$r, $g, $b] = self::channels($hex);
        imagefilledrectangle($canvas, 0, 0, $size, $size, imagecolorallocate($canvas, $r, $g, $b));

        if ($source && is_file($source)) {
            $image = @imagecreatefromstring((string) file_get_contents($source));
            if ($image !== false) {
                $width = imagesx($image);
                $height = imagesy($image);

                if ($cover) {
                    // صورة الموظف تملأ المربّع وتُقصّ من الأطول.
                    $side = min($width, $height);
                    imagecopyresampled(
                        $canvas, $image,
                        0, 0,
                        (int) (($width - $side) / 2), (int) (($height - $side) / 2),
                        $size, $size, $side, $side
                    );
                } else {
                    // الشعار يجلس بهامش مريح داخل المربّع.
                    $box = (int) ($size * 0.72);
                    $scale = min($box / $width, $box / $height);
                    $w = max(1, (int) ($width * $scale));
                    $h = max(1, (int) ($height * $scale));
                    imagecopyresampled(
                        $canvas, $image,
                        (int) (($size - $w) / 2), (int) (($size - $h) / 2),
                        0, 0, $w, $h, $width, $height
                    );
                }
                imagedestroy($image);
            }
        }

        return self::png($canvas);
    }

    /** يضع الصورة داخل مستطيل شفاف بلا تشويه نسبتها. */
    private static function fit(string $source, int $maxWidth, int $maxHeight): string
    {
        $image = @imagecreatefromstring((string) file_get_contents($source));
        if ($image === false) {
            return self::png(imagecreatetruecolor($maxWidth, $maxHeight));
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min($maxWidth / $width, $maxHeight / $height);
        $w = max(1, (int) ($width * $scale));
        $h = max(1, (int) ($height * $scale));

        $canvas = imagecreatetruecolor($w, $h);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $w, $h, $width, $height);
        imagedestroy($image);

        return self::png($canvas);
    }

    private static function png(\GdImage $image): string
    {
        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    // -------------------------------------------------------------- التوقيع

    private static function sign(string $manifestPath, string $signaturePath, array $config): void
    {
        [$certificate, $privateKey] = self::credentials($config);

        $smimePath = $signaturePath . '.smime';
        $signed = openssl_pkcs7_sign(
            $manifestPath,
            $smimePath,
            $certificate,
            [$privateKey, $config['certificate_password']],
            [],
            PKCS7_BINARY | PKCS7_DETACHED,
            $config['wwdr']
        );

        if (!$signed) {
            throw new \RuntimeException('فشل توقيع البطاقة: ' . openssl_error_string());
        }

        // openssl يُخرج رسالة S/MIME كاملة، وأبل تريد توقيع DER خاماً.
        $smime = (string) file_get_contents($smimePath);
        @unlink($smimePath);

        file_put_contents($signaturePath, self::derFromSmime($smime));
    }

    /**
     * الشهادة والمفتاح، من ملف p12 أو من PEM.
     *
     * الصيغتان معاً ضرورة لا ترفٌ: تصدير Keychain على ماك يُخرج p12 مشفّراً
     * بـ RC2، وبناء OpenSSL 3 في أغلب الاستضافات لا يحمّل موفّر legacy
     * فيرفضه. عندها يحوّل المستخدم الملف لـ PEM بأمر واحد ويمضي.
     *
     * @return array{0: string, 1: string}
     */
    private static function credentials(array $config): array
    {
        $path = $config['certificate'];
        $bundle = @file_get_contents($path);
        if ($bundle === false) {
            throw new \RuntimeException('تعذّرت قراءة شهادة البطاقة من: ' . $path);
        }

        // PEM: الشهادة والمفتاح في ملف واحد، أو المفتاح في ملف مستقل.
        if (str_contains($bundle, '-----BEGIN')) {
            $key = $bundle;
            if (!empty($config['private_key'])) {
                $keyBytes = @file_get_contents($config['private_key']);
                if ($keyBytes === false) {
                    throw new \RuntimeException('تعذّرت قراءة المفتاح الخاص من: ' . $config['private_key']);
                }
                $key = $keyBytes;
            }

            return [$bundle, $key];
        }

        $certificates = [];
        if (openssl_pkcs12_read($bundle, $certificates, $config['certificate_password'])) {
            return [$certificates['cert'], $certificates['pkey']];
        }

        $reason = '';
        while ($error = openssl_error_string()) {
            $reason = $error;
        }

        if (str_contains($reason, 'unsupported')) {
            throw new \RuntimeException(
                'ملف p12 مشفّر بخوارزمية قديمة (RC2) لا يقرؤها OpenSSL على هذا الخادم. '
                . 'حوّله لـ PEM بأمر: openssl pkcs12 -in pass.p12 -out pass.pem -nodes -legacy '
                . 'ثم اجعل certificate يشير إلى pass.pem.'
            );
        }

        throw new \RuntimeException('كلمة مرور شهادة البطاقة غير صحيحة أو الملف ليس p12 ولا PEM. ' . $reason);
    }

    /**
     * يستخرج توقيع DER من رسالة S/MIME.
     *
     * لا يكفي تصفية أحرف base64 من بقية النص: سطر الحدّ الفاصل
     * (`--boundary`) يحوي أحرفاً وأرقاماً تنجو من التصفية فتُفسد الترميز.
     * فنقصّ الجسم عند أول سطر يبدأ بشرطتين.
     */
    private static function derFromSmime(string $smime): string
    {
        $smime = str_replace("\r\n", "\n", $smime);

        // آخر ورود لا أوّله: الترويسة العليا تذكر البروتوكول نفسه
        // (`protocol="application/x-pkcs7-signature"`)، والمقصود ترويسة الجزء
        // الموقّع في آخر الرسالة.
        $position = strripos($smime, 'pkcs7-signature');
        if ($position === false) {
            throw new \RuntimeException('لم يُعثر على جزء التوقيع في مخرجات openssl.');
        }

        $bodyStart = strpos($smime, "\n\n", $position);
        if ($bodyStart === false) {
            throw new \RuntimeException('تعذّر تحديد بداية جسم التوقيع.');
        }

        $base64 = '';
        foreach (explode("\n", substr($smime, $bodyStart + 2)) as $line) {
            $line = trim($line);
            if (str_starts_with($line, '--')) {
                break;
            }
            $base64 .= $line;
        }

        $der = base64_decode($base64, true);
        if ($der === false || $der === '') {
            throw new \RuntimeException('تعذّر فكّ ترميز توقيع البطاقة.');
        }

        return $der;
    }

    // ---------------------------------------------------------------- الأرشيف

    /** @param string[] $files */
    private static function zip(string $directory, array $files): string
    {
        $path = $directory . '/pass.pkpass';
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('تعذّر إنشاء ملف البطاقة.');
        }

        foreach ([...$files, 'manifest.json', 'signature'] as $name) {
            $zip->addFile($directory . '/' . $name, $name);
        }
        $zip->close();

        $bytes = (string) file_get_contents($path);
        if ($bytes === '') {
            throw new \RuntimeException('ملف البطاقة خرج فارغاً.');
        }

        return $bytes;
    }

    // --------------------------------------------------------------- مساعدات

    /** @return array{pass_type_identifier: string, team_identifier: string, certificate: string, certificate_password: string, wwdr: string} */
    private static function config(): array
    {
        $config = require BASE_PATH . '/config.php';
        $wallet = is_array($config['wallet'] ?? null) ? $config['wallet'] : [];

        return [
            'pass_type_identifier' => trim((string) ($wallet['pass_type_identifier'] ?? '')),
            'team_identifier' => trim((string) ($wallet['team_identifier'] ?? '')),
            'certificate' => (string) ($wallet['certificate'] ?? BASE_PATH . '/storage/wallet/pass.p12'),
            'certificate_password' => (string) ($wallet['certificate_password'] ?? ''),
            'private_key' => (string) ($wallet['private_key'] ?? ''),
            'wwdr' => (string) ($wallet['wwdr'] ?? BASE_PATH . '/storage/wallet/wwdr.pem'),
        ];
    }

    private static function workDirectory(): string
    {
        $path = sys_get_temp_dir() . '/wasab-pass-' . bin2hex(random_bytes(8));
        if (!mkdir($path, 0700, true) && !is_dir($path)) {
            throw new \RuntimeException('تعذّر إنشاء مجلد مؤقّت للبطاقة.');
        }

        return $path;
    }

    private static function removeDirectory(string $path): void
    {
        foreach ((array) glob($path . '/*') as $file) {
            if (is_string($file)) {
                @unlink($file);
            }
        }
        @rmdir($path);
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function channels(?string $hex): array
    {
        $hex = is_string($hex) && preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? $hex : self::FALLBACK_COLOR;

        return [
            (int) hexdec(substr($hex, 1, 2)),
            (int) hexdec(substr($hex, 3, 2)),
            (int) hexdec(substr($hex, 5, 2)),
        ];
    }

    private static function rgb(?string $hex): string
    {
        [$r, $g, $b] = self::channels($hex);

        return sprintf('rgb(%d, %d, %d)', $r, $g, $b);
    }
}

<?php

namespace App\Core;

/**
 * مولّد رمز QR بسيط ومكتفٍ ذاتياً (بلا مكتبات خارجية) - وضع البايت، مستوى تصحيح
 * الأخطاء M، الإصدارات 1..6 (تتّسع حتى ~105 حرفاً، تكفي روابط التحقق). يُنتج SVG
 * بلون قابل للتحديد (currentColor) فيُطبع بدقة عالية دون اتصال بالإنترنت.
 *
 * تم التحقق من مطابقة مصفوفاته لمكتبة segno المرجعية (نفس الإصدار/القناع) لضمان
 * قابلية المسح.
 */
class QrCode
{
    /** بنية الكتل لمستوى M: version => [dataCodewordsPerBlock, ecCodewordsPerBlock, numBlocks]. */
    private const BLOCKS_M = [
        1 => [16, 10, 1],
        2 => [28, 16, 1],
        3 => [44, 26, 1],
        4 => [32, 18, 2],
        5 => [43, 24, 2],
        6 => [27, 16, 4],
    ];

    /** مركز نمط المحاذاة الوحيد للإصدارات 2..6 (لا محاذاة للإصدار 1). */
    private const ALIGN_CENTER = [2 => 18, 3 => 22, 4 => 26, 5 => 30, 6 => 34];

    private static array $exp = [];
    private static array $log = [];

    private static function initGf(): void
    {
        if (self::$exp) {
            return;
        }
        $x = 1;
        for ($i = 0; $i < 256; $i++) {
            self::$exp[$i] = $x;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11d;
            }
        }
        for ($i = 0; $i < 255; $i++) {
            self::$log[self::$exp[$i]] = $i;
        }
    }

    private static function gmul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$exp[(self::$log[$a] + self::$log[$b]) % 255];
    }

    /** كثير حدود التوليد لعدد codewords من تصحيح الأخطاء. */
    private static function genPoly(int $deg): array
    {
        $g = [1];
        for ($i = 0; $i < $deg; $i++) {
            $mul = [1, self::$exp[$i]];
            $res = array_fill(0, count($g) + 1, 0);
            foreach ($g as $ai => $av) {
                foreach ($mul as $bi => $bv) {
                    $res[$ai + $bi] ^= self::gmul($av, $bv);
                }
            }
            $g = $res;
        }
        return $g;
    }

    /** رموز تصحيح الأخطاء لكتلة بيانات. */
    private static function ecCodewords(array $data, int $ecLen): array
    {
        $gen = self::genPoly($ecLen);
        $res = array_merge($data, array_fill(0, $ecLen, 0));
        $dataLen = count($data);
        for ($i = 0; $i < $dataLen; $i++) {
            $coef = $res[$i];
            if ($coef !== 0) {
                foreach ($gen as $j => $gv) {
                    $res[$i + $j] ^= self::gmul($gv, $coef);
                }
            }
        }
        return array_slice($res, $dataLen);
    }

    /** أصغر إصدار (1..6) يتّسع للنص بوضع البايت مستوى M، أو null إن تجاوز السعة. */
    private static function pickVersion(int $byteLen): ?int
    {
        foreach (self::BLOCKS_M as $v => [$dpb, , $nb]) {
            $totalData = $dpb * $nb;
            // 4 بت للوضع + 8 بت لعدّاد الطول (إصدارات 1..9) = 12 بت = 1.5 بايت تُقرّب لبايتين
            if ($byteLen + 2 <= $totalData) {
                return $v;
            }
        }
        return null;
    }

    /** المصفوفة النهائية (0/1) للنص بإصدار وقناع محددين - للاختبار والبناء الداخلي. */
    public static function matrix(string $text, ?int $version = null, ?int $forceMask = null): ?array
    {
        self::initGf();
        $bytes = array_values(unpack('C*', $text));
        $len = count($bytes);

        $version = $version ?? self::pickVersion($len);
        if ($version === null || !isset(self::BLOCKS_M[$version])) {
            return null;
        }
        [$dpb, $ecLen, $nb] = self::BLOCKS_M[$version];
        $totalData = $dpb * $nb;
        if ($len + 2 > $totalData) {
            return null;
        }

        // --- ترميز البيانات إلى بتات ---
        $bits = '';
        $bits .= '0100'; // وضع البايت
        $bits .= str_pad(decbin($len), 8, '0', STR_PAD_LEFT); // عدّاد الطول (8 بت لـ v1..9)
        foreach ($bytes as $b) {
            $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        }
        // منهٍ (حتى 4 بت) ثم بتات إكمال لحدود البايت، ثم حشو بالبايتين المتناوبين -
        // مطابقة تماماً للمرجع (segno): بتات الإكمال دائماً 8-(الطول%8).
        $cap = $totalData * 8;
        $bits .= str_repeat('0', min($cap - strlen($bits), 4));
        $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
        $pad = [0xEC, 0x11];
        $padCount = intdiv($cap, 8) - intdiv(strlen($bits), 8);
        for ($k = 0; $k < $padCount; $k++) {
            $bits .= str_pad(decbin($pad[$k % 2]), 8, '0', STR_PAD_LEFT);
        }

        // codewords البيانات
        $dataCw = [];
        for ($i = 0; $i < $cap; $i += 8) {
            $dataCw[] = bindec(substr($bits, $i, 8));
        }

        // تقسيم لكتل + تصحيح الأخطاء لكل كتلة، ثم التشابك
        $dataBlocks = [];
        $ecBlocks = [];
        for ($b = 0; $b < $nb; $b++) {
            $block = array_slice($dataCw, $b * $dpb, $dpb);
            $dataBlocks[] = $block;
            $ecBlocks[] = self::ecCodewords($block, $ecLen);
        }
        $finalCw = [];
        for ($i = 0; $i < $dpb; $i++) {
            foreach ($dataBlocks as $blk) {
                $finalCw[] = $blk[$i];
            }
        }
        for ($i = 0; $i < $ecLen; $i++) {
            foreach ($ecBlocks as $blk) {
                $finalCw[] = $blk[$i];
            }
        }

        // تدفق البتات النهائي
        $bitstream = '';
        foreach ($finalCw as $cw) {
            $bitstream .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
        }

        return self::buildMatrix($version, $bitstream, $forceMask);
    }

    private static function buildMatrix(int $version, string $bitstream, ?int $forceMask): array
    {
        $size = 17 + 4 * $version;
        // m: قيمة الوحدة (0/1)، fn: هل هي وحدة وظيفية (لا تُقنّع)
        $m = array_fill(0, $size, array_fill(0, $size, 0));
        $fn = array_fill(0, $size, array_fill(0, $size, false));

        $set = function (int $r, int $c, int $v, bool $isFn) use (&$m, &$fn): void {
            $m[$r][$c] = $v;
            $fn[$r][$c] = $isFn;
        };

        // أنماط الكشف الثلاثة + الفواصل
        $placeFinder = function (int $r, int $c) use ($set, $size): void {
            for ($i = -1; $i <= 7; $i++) {
                for ($j = -1; $j <= 7; $j++) {
                    $rr = $r + $i;
                    $cc = $c + $j;
                    if ($rr < 0 || $rr >= $size || $cc < 0 || $cc >= $size) {
                        continue;
                    }
                    $inRing = ($i >= 0 && $i <= 6 && ($j === 0 || $j === 6))
                        || ($j >= 0 && $j <= 6 && ($i === 0 || $i === 6));
                    $inCore = ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4);
                    $set($rr, $cc, ($inRing || $inCore) ? 1 : 0, true);
                }
            }
        };
        $placeFinder(0, 0);
        $placeFinder(0, $size - 7);
        $placeFinder($size - 7, 0);

        // نمط المحاذاة (إصدار >= 2)
        if (isset(self::ALIGN_CENTER[$version])) {
            $ac = self::ALIGN_CENTER[$version];
            for ($i = -2; $i <= 2; $i++) {
                for ($j = -2; $j <= 2; $j++) {
                    $ring = max(abs($i), abs($j));
                    $set($ac + $i, $ac + $j, ($ring === 1) ? 0 : 1, true);
                }
            }
        }

        // أنماط التوقيت
        for ($i = 8; $i < $size - 8; $i++) {
            $v = ($i % 2 === 0) ? 1 : 0;
            $set(6, $i, $v, true);
            $set($i, 6, $v, true);
        }

        // الوحدة الداكنة الثابتة
        $set(4 * $version + 9, 8, 1, true);

        // حجز مناطق معلومات التنسيق (تُملأ لاحقاً)
        for ($i = 0; $i <= 8; $i++) {
            if ($i !== 6) {
                $set(8, $i, 0, true);
                $set($i, 8, 0, true);
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $set(8, $size - 1 - $i, 0, true);
            $set($size - 1 - $i, 8, 0, true);
        }

        // وضع بتات البيانات بنمط التعرّج (من أسفل اليمين، أعمدة مزدوجة، تخطّي العمود 6)
        $bitLen = strlen($bitstream);
        $bi = 0;
        $col = $size - 1;
        $upward = true;
        while ($col > 0) {
            if ($col === 6) {
                $col--; // تخطّي عمود التوقيت العمودي
            }
            for ($k = 0; $k < $size; $k++) {
                $row = $upward ? ($size - 1 - $k) : $k;
                for ($t = 0; $t < 2; $t++) {
                    $c = $col - $t;
                    if (!$fn[$row][$c]) {
                        $bit = ($bi < $bitLen) ? (int) $bitstream[$bi] : 0;
                        $bi++;
                        $m[$row][$c] = $bit;
                    }
                }
            }
            $col -= 2;
            $upward = !$upward;
        }

        // اختيار القناع الأمثل (أو المفروض) ثم كتابة معلومات التنسيق
        $bestMask = $forceMask;
        if ($bestMask === null) {
            $bestPenalty = PHP_INT_MAX;
            for ($mask = 0; $mask < 8; $mask++) {
                $trial = self::applyMask($m, $fn, $mask, $size);
                self::writeFormat($trial, $fn, $version, $mask, $size);
                $p = self::penalty($trial, $size);
                if ($p < $bestPenalty) {
                    $bestPenalty = $p;
                    $bestMask = $mask;
                }
            }
        }
        $final = self::applyMask($m, $fn, $bestMask, $size);
        self::writeFormat($final, $fn, $version, $bestMask, $size);
        return $final;
    }

    private static function maskBit(int $mask, int $r, int $c): bool
    {
        switch ($mask) {
            case 0: return ($r + $c) % 2 === 0;
            case 1: return $r % 2 === 0;
            case 2: return $c % 3 === 0;
            case 3: return ($r + $c) % 3 === 0;
            case 4: return (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0;
            case 5: return (($r * $c) % 2) + (($r * $c) % 3) === 0;
            case 6: return ((($r * $c) % 2) + (($r * $c) % 3)) % 2 === 0;
            default: return ((($r + $c) % 2) + (($r * $c) % 3)) % 2 === 0;
        }
    }

    private static function applyMask(array $m, array $fn, int $mask, int $size): array
    {
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if (!$fn[$r][$c] && self::maskBit($mask, $r, $c)) {
                    $m[$r][$c] ^= 1;
                }
            }
        }
        return $m;
    }

    /** معلومات التنسيق (15 بت): مستوى M = 00، القناع 3 بت، BCH(15,5) وXOR الثابت. */
    private static function writeFormat(array &$m, array $fn, int $version, int $mask, int $size): void
    {
        $data = (0b00 << 3) | $mask; // مستوى M
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1);
            if ($rem & (1 << 10)) {
                $rem ^= 0b10100110111;
            }
        }
        $bits = (($data << 10) | ($rem & 0x3FF)) ^ 0b101010000010010;

        // النسخة الأولى حول أنماط الكشف العلوية اليسرى
        for ($i = 0; $i < 15; $i++) {
            $bit = ($bits >> $i) & 1;
            // الوضع الرأسي (العمود 8) ثم الأفقي (الصف 8)
            if ($i < 6) {
                $m[$i][8] = $bit;
            } elseif ($i === 6) {
                $m[7][8] = $bit;
            } elseif ($i === 7) {
                $m[8][8] = $bit;
            } elseif ($i === 8) {
                $m[8][7] = $bit;
            } else {
                $m[8][14 - $i] = $bit;
            }

            if ($i < 8) {
                $m[8][$size - 1 - $i] = $bit;
            } else {
                $m[$size - 15 + $i][8] = $bit;
            }
        }
        // الوحدة الداكنة تبقى داكنة
        $m[4 * $version + 9][8] = 1;
    }

    private static function penalty(array $m, int $size): int
    {
        $penalty = 0;
        // القاعدة 1: خمس وحدات متتالية أو أكثر بنفس اللون (صفوف وأعمدة)
        for ($r = 0; $r < $size; $r++) {
            for ($orient = 0; $orient < 2; $orient++) {
                $run = 1;
                $prev = -1;
                for ($c = 0; $c < $size; $c++) {
                    $val = $orient === 0 ? $m[$r][$c] : $m[$c][$r];
                    if ($val === $prev) {
                        $run++;
                    } else {
                        if ($run >= 5) {
                            $penalty += 3 + ($run - 5);
                        }
                        $run = 1;
                        $prev = $val;
                    }
                }
                if ($run >= 5) {
                    $penalty += 3 + ($run - 5);
                }
            }
        }
        // القاعدة 2: كتل 2×2 بنفس اللون
        for ($r = 0; $r < $size - 1; $r++) {
            for ($c = 0; $c < $size - 1; $c++) {
                $v = $m[$r][$c];
                if ($v === $m[$r][$c + 1] && $v === $m[$r + 1][$c] && $v === $m[$r + 1][$c + 1]) {
                    $penalty += 3;
                }
            }
        }
        // القاعدة 3: نمط 1:1:3:1:1 (10111010000 أو معكوسه)
        $patA = [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0];
        $patB = [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1];
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size - 10; $c++) {
                $rowSeg = [];
                $colSeg = [];
                for ($k = 0; $k < 11; $k++) {
                    $rowSeg[] = $m[$r][$c + $k];
                    $colSeg[] = $m[$c + $k][$r];
                }
                if ($rowSeg === $patA || $rowSeg === $patB) {
                    $penalty += 40;
                }
                if ($colSeg === $patA || $colSeg === $patB) {
                    $penalty += 40;
                }
            }
        }
        // القاعدة 4: انحراف نسبة الوحدات الداكنة عن 50%
        $dark = 0;
        for ($r = 0; $r < $size; $r++) {
            $dark += array_sum($m[$r]);
        }
        $ratio = ($dark * 100) / ($size * $size);
        $penalty += (int) ((abs($ratio - 50) / 5)) * 10;

        return $penalty;
    }

    /** يُنتج <svg> لرمز QR بلون currentColor - أو null إن تعذّر (نص طويل جداً). */
    public static function svg(string $text, int $sizePx = 100, int $quiet = 4): ?string
    {
        $matrix = self::matrix($text);
        if ($matrix === null) {
            return null;
        }
        $n = count($matrix);
        $total = $n + 2 * $quiet;
        $rects = '';
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($matrix[$r][$c]) {
                    $x = $c + $quiet;
                    $y = $r + $quiet;
                    $rects .= "M{$x} {$y}h1v1h-1z";
                }
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $sizePx . '" height="' . $sizePx . '" '
            . 'viewBox="0 0 ' . $total . ' ' . $total . '" shape-rendering="crispEdges" role="img">'
            . '<rect width="' . $total . '" height="' . $total . '" fill="#ffffff"/>'
            . '<path d="' . $rects . '" fill="currentColor"/></svg>';
    }
}

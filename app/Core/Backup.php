<?php

namespace App\Core;

use PDO;

/**
 * نسخ احتياطي منطقي لقاعدة البيانات بلا أدوات خارجية (لا يعتمد على mysqldump/exec
 * التي تُعطَّل غالباً على الاستضافة المشتركة). يمرّ على كل الجداول عبر PDO ويكتب
 * ملف SQL مضغوطاً (gzip) في storage/backups المحميّ. يحتفظ بآخر N نسخ فقط.
 */
class Backup
{
    private const KEEP = 7;          // عدد النسخ المحفوظة
    private const INTERVAL = 86400;  // الحد الأدنى بين النسخ التلقائية (يوم)
    private const CHUNK = 500;       // عدد الصفوف لكل دفعة قراءة

    public static function dir(): string
    {
        return BASE_PATH . '/storage/backups';
    }

    /** يشغّل النسخ فقط إن مرّ يوم على أحدث نسخة (يُستدعى من cron). */
    public static function runIfDue(): ?string
    {
        $latest = self::latestTime();
        if ($latest !== null && (time() - $latest) < self::INTERVAL) {
            return null;
        }
        return self::run();
    }

    /** ينشئ نسخة احتياطية الآن ويُعيد مسار الملف (أو null عند الفشل). */
    public static function run(): ?string
    {
        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        // حارس وصول: منع الوصول المباشر عبر الويب (طبقة إضافية فوق حجب storage)
        if (!is_file($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
        }

        $pdo = Database::pdo();
        $file = $dir . '/backup-' . self::stamp() . '.sql.gz';
        $gz = gzopen($file, 'wb9');
        if ($gz === false) {
            return null;
        }

        gzwrite($gz, "-- Wasab logical backup\n-- " . self::stamp() . "\nSET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");

        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            self::dumpTable($pdo, $gz, (string) $table);
        }

        gzwrite($gz, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);

        self::pruneOld();
        return $file;
    }

    private static function dumpTable(PDO $pdo, $gz, string $table): void
    {
        $quoted = '`' . str_replace('`', '``', $table) . '`';

        $create = $pdo->query('SHOW CREATE TABLE ' . $quoted)->fetch(PDO::FETCH_ASSOC);
        $createSql = $create['Create Table'] ?? ($create['Create View'] ?? null);
        if ($createSql === null) {
            return;
        }
        gzwrite($gz, "DROP TABLE IF EXISTS {$quoted};\n{$createSql};\n\n");

        // قراءة الصفوف على دفعات لتفادي استهلاك الذاكرة
        $offset = 0;
        while (true) {
            $rows = $pdo->query("SELECT * FROM {$quoted} LIMIT " . self::CHUNK . " OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) {
                break;
            }
            foreach ($rows as $row) {
                $cols = array_map(fn ($c) => '`' . str_replace('`', '``', $c) . '`', array_keys($row));
                $vals = array_map(function ($v) use ($pdo) {
                    return $v === null ? 'NULL' : $pdo->quote((string) $v);
                }, array_values($row));
                gzwrite($gz, "INSERT INTO {$quoted} (" . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ");\n");
            }
            $offset += self::CHUNK;
            if (count($rows) < self::CHUNK) {
                break;
            }
        }
        gzwrite($gz, "\n");
    }

    /** قائمة النسخ الحالية (الأحدث أولاً): [name, size, mtime]. */
    public static function list(): array
    {
        $files = glob(self::dir() . '/backup-*.sql.gz') ?: [];
        $out = [];
        foreach ($files as $f) {
            $out[] = ['name' => basename($f), 'size' => (int) @filesize($f), 'mtime' => (int) @filemtime($f)];
        }
        usort($out, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);
        return $out;
    }

    /** مسار ملف نسخة بالاسم (بعد التحقق من صحة الاسم لمنع اجتياز المسارات). */
    public static function pathFor(string $name): ?string
    {
        if (!preg_match('/^backup-\d{8}-\d{6}\.sql\.gz$/', $name)) {
            return null;
        }
        $path = self::dir() . '/' . $name;
        return is_file($path) ? $path : null;
    }

    private static function latestTime(): ?int
    {
        $list = self::list();
        return $list ? $list[0]['mtime'] : null;
    }

    private static function pruneOld(): void
    {
        $list = self::list();
        foreach (array_slice($list, self::KEEP) as $old) {
            @unlink(self::dir() . '/' . $old['name']);
        }
    }

    private static function stamp(): string
    {
        return date('Ymd-His');
    }
}

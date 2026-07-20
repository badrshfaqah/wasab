<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * غلاف خفيف حول PDO مع أدوات استعلام بسيطة، بدون ORM ثقيل.
 */
class Database
{
    private static ?PDO $connection = null;

    public static function connect(array $config): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'] ?? '3306',
            $config['database'],
            $config['charset'] ?? 'utf8mb4'
        );

        self::$connection = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // مزامنة منطقة MySQL الزمنية للجلسة مع منطقة PHP (المضبوطة من config.php):
        // بدونها تكون NOW()/CURDATE() بتوقيت خادم قاعدة البيانات (غالباً UTC على
        // الاستضافات) بينما التواريخ المخزنة تُكتب بتوقيت PHP، فتنطلق تذكيرات
        // الاجتماعات وأحداث التقويم متأخرة أو لا تنطلق إطلاقاً.
        try {
            self::$connection->exec("SET time_zone = '" . date('P') . "'");
        } catch (\Throwable $e) {
            // بعض الاستضافات تمنع تغيير time_zone - نكمل بلا كسر (السلوك السابق نفسه).
        }

        return self::$connection;
    }

    public static function connectRaw(string $host, string $port, string $username, string $password, ?string $database = null, string $charset = 'utf8mb4'): PDO
    {
        $dsn = "mysql:host={$host};port={$port}" . ($database ? ";dbname={$database}" : '') . ";charset={$charset}";
        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public static function pdo(): PDO
    {
        if (!self::$connection instanceof PDO) {
            throw new PDOException('لم يتم فتح اتصال بقاعدة البيانات بعد.');
        }
        return self::$connection;
    }

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function select(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function first(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row ?: null;
    }

    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn ($c) => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            '`' . implode('`, `', $columns) . '`',
            implode(', ', $placeholders)
        );

        self::query($sql, array_combine($placeholders, array_values($data)));

        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = [];
        $params = [];
        foreach ($data as $col => $val) {
            $set[] = "`{$col}` = :set_{$col}";
            $params["set_{$col}"] = $val;
        }
        foreach ($whereParams as $k => $v) {
            $params[$k] = $v;
        }

        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $set), $where);
        return self::query($sql, $params)->rowCount();
    }

    public static function delete(string $table, string $where, array $whereParams = []): int
    {
        $sql = sprintf('DELETE FROM `%s` WHERE %s', $table, $where);
        return self::query($sql, $whereParams)->rowCount();
    }

    public static function count(string $table, string $where = '1=1', array $params = []): int
    {
        $row = self::first("SELECT COUNT(*) AS c FROM `{$table}` WHERE {$where}", $params);
        return (int) ($row['c'] ?? 0);
    }

    public static function beginTransaction(): bool
    {
        return self::pdo()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::pdo()->commit();
    }

    public static function rollBack(): bool
    {
        return self::pdo()->inTransaction() ? self::pdo()->rollBack() : false;
    }
}

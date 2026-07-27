<?php

namespace Modules\Archive\Models;

use App\Core\Database;

class ArchiveFileShare
{
    public static function create(int $fileId, int $userId, string $expiresAt, ?int $maxDownloads): array
    {
        $token = bin2hex(random_bytes(24));
        $id = Database::insert('archive_file_shares', [
            'file_id' => $fileId,
            'token' => $token,
            'expires_at' => $expiresAt,
            'max_downloads' => $maxDownloads,
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return Database::first('SELECT * FROM archive_file_shares WHERE id = :id', ['id' => $id]);
    }

    public static function findByToken(string $token): ?array
    {
        return Database::first('SELECT * FROM archive_file_shares WHERE token = :t', ['t' => $token]);
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM archive_file_shares WHERE id = :id', ['id' => $id]);
    }

    public static function forFile(int $fileId): array
    {
        return Database::select(
            'SELECT s.*, u.name AS creator_name
               FROM archive_file_shares s
               LEFT JOIN users u ON u.id = s.created_by
              WHERE s.file_id = :id
              ORDER BY s.id DESC',
            ['id' => $fileId]
        );
    }

    public static function revoke(int $id): void
    {
        Database::update('archive_file_shares', ['revoked_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
    }

    public static function incrementDownload(int $id): void
    {
        Database::pdo()->prepare('UPDATE archive_file_shares SET download_count = download_count + 1 WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * استهلاك تحميل واحد ذرّياً: يزيد العدّاد فقط إن كان الرابط صالحاً (غير ملغى،
     * غير منتهٍ، ولم يبلغ حدّه) - كل ذلك في جملة UPDATE واحدة يحكمها قفل الصف، فلا
     * يمكن لطلبين متزامنين تجاوز حدّ التحميلات معاً. يعيد true إن تم الحجز فعلاً.
     */
    public static function tryConsumeDownload(int $id): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE archive_file_shares
                SET download_count = download_count + 1
              WHERE id = :id
                AND revoked_at IS NULL
                AND expires_at >= NOW()
                AND (max_downloads IS NULL OR download_count < max_downloads)'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() === 1;
    }

    public static function isUsable(array $share): bool
    {
        if ($share['revoked_at'] !== null) {
            return false;
        }
        if (strtotime($share['expires_at']) < time()) {
            return false;
        }
        if ($share['max_downloads'] !== null && (int) $share['download_count'] >= (int) $share['max_downloads']) {
            return false;
        }
        return true;
    }
}

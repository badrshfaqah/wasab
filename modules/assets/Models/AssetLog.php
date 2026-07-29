<?php

namespace Modules\Assets\Models;

use App\Core\Database;

class AssetLog
{
    public static function add(int $assetId, ?int $userId, string $action, string $description = ''): void
    {
        Database::insert('assets_logs', [
            'asset_id' => $assetId,
            'user_id' => $userId,
            'action' => $action,
            'description' => mb_substr($description, 0, 400),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function forAsset(int $assetId): array
    {
        return Database::select(
            'SELECT l.*, u.name AS user_name FROM assets_logs l
               LEFT JOIN users u ON u.id = l.user_id
              WHERE l.asset_id = :a ORDER BY l.id DESC',
            ['a' => $assetId]
        );
    }
}

<?php

namespace Modules\Assets\Models;

use App\Core\Database;

/** محضر تسليم عهدة (قد يضم عدة أصول لحامل واحد) وبنوده. */
class AssetHandover
{
    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM assets_handovers WHERE id = :id', ['id' => $id]);
    }

    public static function create(array $data): int
    {
        return Database::insert('assets_handovers', $data);
    }

    public static function addItem(int $handoverId, int $assetId): int
    {
        return Database::insert('assets_handover_items', [
            'handover_id' => $handoverId,
            'asset_id' => $assetId,
        ]);
    }

    /** بنود المحضر مع بيانات الأصل. */
    public static function items(int $handoverId): array
    {
        return Database::select(
            'SELECT i.*, a.name AS asset_name, a.asset_code, a.serial_number
               FROM assets_handover_items i
               JOIN assets_assets a ON a.id = i.asset_id
              WHERE i.handover_id = :h ORDER BY i.id',
            ['h' => $handoverId]
        );
    }

    /** بند محضر واحد (لعملية الإرجاع). */
    public static function findItem(int $itemId): ?array
    {
        return Database::first(
            'SELECT i.*, h.company_id, a.name AS asset_name
               FROM assets_handover_items i
               JOIN assets_handovers h ON h.id = i.handover_id
               JOIN assets_assets a ON a.id = i.asset_id
              WHERE i.id = :id',
            ['id' => $itemId]
        );
    }

    public static function markReturned(int $itemId, ?string $condition, ?string $note): void
    {
        Database::update('assets_handover_items', [
            'returned_at' => date('Y-m-d H:i:s'),
            'return_condition' => $condition,
            'return_note' => $note,
        ], 'id = :id', ['id' => $itemId]);
    }

    /** محاضر الشركة مع عدد بنودها، أحدثها أولاً. */
    public static function paginate(int $companyId, int $page, int $perPage): array
    {
        $total = Database::count('assets_handovers', 'company_id = :c', ['c' => $companyId]);
        $offset = ($page - 1) * $perPage;
        $rows = Database::select(
            "SELECT h.*,
                    (SELECT COUNT(*) FROM assets_handover_items i WHERE i.handover_id = h.id) AS items_count,
                    (SELECT COUNT(*) FROM assets_handover_items i WHERE i.handover_id = h.id AND i.returned_at IS NULL) AS open_count
               FROM assets_handovers h
              WHERE h.company_id = :c
              ORDER BY h.id DESC LIMIT {$perPage} OFFSET {$offset}",
            ['c' => $companyId]
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** المحضر الذي سُلّم عبره أصل معيّن وما زال مفتوحاً (لإرجاعه من صفحة الأصل). */
    public static function openItemForAsset(int $assetId): ?array
    {
        return Database::first(
            'SELECT i.*, h.holder_name FROM assets_handover_items i
               JOIN assets_handovers h ON h.id = i.handover_id
              WHERE i.asset_id = :a AND i.returned_at IS NULL
              ORDER BY i.id DESC LIMIT 1',
            ['a' => $assetId]
        );
    }
}

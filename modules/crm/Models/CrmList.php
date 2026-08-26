<?php

namespace Modules\Crm\Models;

use App\Core\Database;

/** قوائم المساحة: تجميعات مرنة للجهات (جهات مستهدفة، شركاء محتملون...). */
class CrmList
{
    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM crm_lists WHERE id = :id', ['id' => $id]);
    }

    public static function forWorkspace(int $workspaceId): array
    {
        return Database::select(
            'SELECT l.*, (SELECT COUNT(*) FROM crm_list_items i WHERE i.list_id = l.id) AS items_count
               FROM crm_lists l WHERE l.workspace_id = :w ORDER BY l.name',
            ['w' => $workspaceId]
        );
    }

    public static function create(int $workspaceId, string $name, ?string $description, int $userId): int
    {
        return Database::insert('crm_lists', [
            'workspace_id' => $workspaceId,
            'name' => mb_substr($name, 0, 150),
            'description' => $description ? mb_substr($description, 0, 500) : null,
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function delete(int $id): void
    {
        Database::delete('crm_lists', 'id = :id', ['id' => $id]);
    }

    /** جهات القائمة (عبر علاقة الجهة بالمساحة فلا تتسرب جهة من مساحة أخرى). */
    public static function organizations(int $listId): array
    {
        return Database::select(
            "SELECT r.*, o.name, o.sector, o.city, o.email, o.phone, u.name AS owner_name
               FROM crm_list_items i
               JOIN crm_workspace_orgs r ON r.id = i.workspace_org_id
               JOIN contacts_organizations o ON o.id = r.organization_id
               LEFT JOIN users u ON u.id = r.owner_id
              WHERE i.list_id = :l ORDER BY o.name",
            ['l' => $listId]
        );
    }

    /** القوائم التي تضم علاقة جهة معيّنة. */
    public static function forRelation(int $relationId): array
    {
        return Database::select(
            'SELECT l.* FROM crm_list_items i JOIN crm_lists l ON l.id = i.list_id
              WHERE i.workspace_org_id = :r ORDER BY l.name',
            ['r' => $relationId]
        );
    }

    public static function addItem(int $listId, int $relationId): void
    {
        $exists = Database::first(
            'SELECT list_id FROM crm_list_items WHERE list_id = :l AND workspace_org_id = :r',
            ['l' => $listId, 'r' => $relationId]
        );
        if (!$exists) {
            Database::insert('crm_list_items', [
                'list_id' => $listId,
                'workspace_org_id' => $relationId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public static function removeItem(int $listId, int $relationId): void
    {
        Database::delete('crm_list_items', 'list_id = :l AND workspace_org_id = :r', ['l' => $listId, 'r' => $relationId]);
    }
}

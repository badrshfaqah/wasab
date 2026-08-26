<?php

namespace Modules\Crm\Models;

use App\Core\Auth;
use App\Core\Database;

/** سجل تغييرات CRM: من فعل ماذا ومتى وعلى أي سجل. */
class CrmLog
{
    public static function add(?int $workspaceId, string $action, string $entityType, ?int $entityId, string $description): void
    {
        Database::insert('crm_logs', [
            'company_id' => (int) (Auth::companyId() ?? 0),
            'workspace_id' => $workspaceId,
            'user_id' => Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => mb_substr($description, 0, 255),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function forEntity(string $entityType, int $entityId, int $limit = 30): array
    {
        return Database::select(
            "SELECT l.*, u.name AS user_name FROM crm_logs l LEFT JOIN users u ON u.id = l.user_id
              WHERE l.entity_type = :t AND l.entity_id = :i ORDER BY l.id DESC LIMIT {$limit}",
            ['t' => $entityType, 'i' => $entityId]
        );
    }

    public static function forWorkspace(int $workspaceId, int $limit = 100): array
    {
        return Database::select(
            "SELECT l.*, u.name AS user_name FROM crm_logs l LEFT JOIN users u ON u.id = l.user_id
              WHERE l.workspace_id = :w ORDER BY l.id DESC LIMIT {$limit}",
            ['w' => $workspaceId]
        );
    }
}

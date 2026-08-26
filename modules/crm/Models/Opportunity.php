<?php

namespace Modules\Crm\Models;

use App\Core\Database;

/**
 * الفرصة: الجهة قد يكون معها عدة فرص في وقت واحد (شراكة إعلامية، تنفيذ جناح،
 * تغطية مدفوعة...) فتُفصل عن الجهة وتُدار عبر مراحل المسار.
 */
class Opportunity
{
    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM crm_opportunities WHERE id = :id', ['id' => $id]);
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('crm_opportunities', $data);
    }

    public static function update(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        Database::update('crm_opportunities', $data, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('crm_opportunities', 'id = :id', ['id' => $id]);
    }

    /** فرص مسار مرتبة حسب المرحلة - لعرض Kanban. */
    public static function byStage(int $workspaceId, int $pipelineId, array $filters = []): array
    {
        $where = 'o.workspace_id = :w AND o.pipeline_id = :p';
        $params = ['w' => $workspaceId, 'p' => $pipelineId];
        if (!empty($filters['owner'])) {
            $where .= ' AND o.owner_id = :owner';
            $params['owner'] = (int) $filters['owner'];
        }
        if (!empty($filters['q'])) {
            $where .= ' AND (o.name LIKE :q OR org.name LIKE :q2)';
            $params['q'] = '%' . $filters['q'] . '%';
            $params['q2'] = '%' . $filters['q'] . '%';
        }
        if (empty($filters['include_closed'])) {
            $where .= " AND o.status = 'open'";
        }

        $rows = Database::select(
            "SELECT o.*, org.name AS organization_name, u.name AS owner_name, c.full_name AS contact_name
               FROM crm_opportunities o
               JOIN contacts_organizations org ON org.id = o.organization_id
               LEFT JOIN users u ON u.id = o.owner_id
               LEFT JOIN contacts_persons c ON c.id = o.contact_id
              WHERE {$where}
              ORDER BY o.updated_at DESC, o.id DESC",
            $params
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['stage_id']][] = $row;
        }
        return $grouped;
    }

    public static function forOrganization(int $workspaceId, int $organizationId): array
    {
        return Database::select(
            "SELECT o.*, s.name AS stage_name, s.color AS stage_color, u.name AS owner_name
               FROM crm_opportunities o
               LEFT JOIN crm_stages s ON s.id = o.stage_id
               LEFT JOIN users u ON u.id = o.owner_id
              WHERE o.workspace_id = :w AND o.organization_id = :o
              ORDER BY o.status = 'open' DESC, o.created_at DESC",
            ['w' => $workspaceId, 'o' => $organizationId]
        );
    }

    /** أرقام المسار: عدد وقيمة الفرص المفتوحة والمكسوبة. */
    public static function stats(int $workspaceId): array
    {
        $row = Database::first(
            "SELECT
                SUM(status = 'open') AS open_count,
                SUM(status = 'won') AS won_count,
                SUM(status = 'lost') AS lost_count,
                COALESCE(SUM(CASE WHEN status = 'open' THEN value END), 0) AS open_value,
                COALESCE(SUM(CASE WHEN status = 'won' THEN value END), 0) AS won_value
             FROM crm_opportunities WHERE workspace_id = :w",
            ['w' => $workspaceId]
        );
        return [
            'open' => (int) ($row['open_count'] ?? 0),
            'won' => (int) ($row['won_count'] ?? 0),
            'lost' => (int) ($row['lost_count'] ?? 0),
            'open_value' => (float) ($row['open_value'] ?? 0),
            'won_value' => (float) ($row['won_value'] ?? 0),
        ];
    }
}

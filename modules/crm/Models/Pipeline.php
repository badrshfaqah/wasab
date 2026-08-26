<?php

namespace Modules\Crm\Models;

use App\Core\Database;

/** مسارات العمل ومراحلها - كل مساحة تصمّم مسارها كما يناسب نشاطها. */
class Pipeline
{
    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM crm_pipelines WHERE id = :id', ['id' => $id]);
    }

    public static function forWorkspace(int $workspaceId): array
    {
        return Database::select(
            'SELECT * FROM crm_pipelines WHERE workspace_id = :w ORDER BY is_default DESC, name',
            ['w' => $workspaceId]
        );
    }

    /** المسار الافتراضي للمساحة (أو أول مسار). */
    public static function defaultFor(int $workspaceId): ?array
    {
        return Database::first(
            'SELECT * FROM crm_pipelines WHERE workspace_id = :w ORDER BY is_default DESC, id LIMIT 1',
            ['w' => $workspaceId]
        );
    }

    public static function create(int $workspaceId, string $name, bool $isDefault = false): int
    {
        if ($isDefault) {
            Database::update('crm_pipelines', ['is_default' => 0], 'workspace_id = :w', ['w' => $workspaceId]);
        }
        return Database::insert('crm_pipelines', [
            'workspace_id' => $workspaceId,
            'name' => mb_substr($name, 0, 120),
            'is_default' => $isDefault ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** مراحل مسار - النشطة فقط افتراضياً (المعطّلة تبقى لأجل الفرص القديمة). */
    public static function stages(int $pipelineId, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM crm_stages WHERE pipeline_id = :p';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        return Database::select($sql . ' ORDER BY sort_order, id', ['p' => $pipelineId]);
    }

    public static function findStage(int $stageId): ?array
    {
        return Database::first('SELECT * FROM crm_stages WHERE id = :id', ['id' => $stageId]);
    }

    public static function addStage(int $pipelineId, string $name, string $color, string $outcome): int
    {
        $max = Database::first('SELECT COALESCE(MAX(sort_order), 0) AS m FROM crm_stages WHERE pipeline_id = :p', ['p' => $pipelineId]);
        return Database::insert('crm_stages', [
            'pipeline_id' => $pipelineId,
            'name' => mb_substr($name, 0, 120),
            'color' => $color,
            'outcome' => in_array($outcome, ['open', 'won', 'lost'], true) ? $outcome : 'open',
            'sort_order' => ((int) ($max['m'] ?? 0)) + 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function updateStage(int $stageId, array $data): void
    {
        Database::update('crm_stages', $data, 'id = :id', ['id' => $stageId]);
    }

    /** تحريك مرحلة لأعلى/أسفل بتبديل ترتيبها مع جارتها. */
    public static function moveStage(int $stageId, string $direction): void
    {
        $stage = self::findStage($stageId);
        if (!$stage) {
            return;
        }
        $op = $direction === 'up' ? '<' : '>';
        $order = $direction === 'up' ? 'DESC' : 'ASC';
        $neighbour = Database::first(
            "SELECT * FROM crm_stages WHERE pipeline_id = :p AND sort_order {$op} :s ORDER BY sort_order {$order} LIMIT 1",
            ['p' => $stage['pipeline_id'], 's' => $stage['sort_order']]
        );
        if (!$neighbour) {
            return;
        }
        Database::update('crm_stages', ['sort_order' => $neighbour['sort_order']], 'id = :id', ['id' => $stage['id']]);
        Database::update('crm_stages', ['sort_order' => $stage['sort_order']], 'id = :id', ['id' => $neighbour['id']]);
    }
}

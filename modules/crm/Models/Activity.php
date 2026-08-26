<?php

namespace Modules\Crm\Models;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ModuleManager;
use App\Core\Notification;

/**
 * نشاط CRM: كل ما جرى مع الجهة (بريد، اتصال، اجتماع، ملاحظة...) بترتيب زمني.
 *
 * المتابعة القادمة ليست حقلاً معلّقاً: حين يحدد المستخدم موعداً تُنشأ مهمة حقيقية
 * في إضافة المهام مرتبطة بالجهة، فتظهر في مهامه وتقويمه وتصله تنبيهاتها من
 * منظومة وصاب نفسها بدل بناء نظام تذكير مكرر داخل CRM.
 */
class Activity
{
    public static function types(): array
    {
        return [
            'email' => ['📧', 'بريد إلكتروني'],
            'call' => ['📞', 'اتصال هاتفي'],
            'whatsapp' => ['💬', 'واتساب'],
            'meeting' => ['🤝', 'اجتماع'],
            'visit' => ['🚗', 'زيارة'],
            'proposal' => ['📄', 'عرض/ملف تعريفي'],
            'note' => ['📝', 'ملاحظة'],
            'followup' => ['🔔', 'متابعة'],
            'file' => ['📎', 'مرفق'],
            'stage_change' => ['🔀', 'تغيير مرحلة'],
        ];
    }

    public static function typeLabel(string $type): string
    {
        return self::types()[$type][1] ?? $type;
    }

    public static function typeIcon(string $type): string
    {
        return self::types()[$type][0] ?? '•';
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM crm_activities WHERE id = :id', ['id' => $id]);
    }

    /**
     * سجل العلاقة: أنشطة الجهة داخل مساحة معيّنة، الأحدث أولاً.
     * $onlyUserId يقصر العرض على أنشطة مستخدم بعينه - لمن لا يملك صلاحية
     * «مشاهدة أنشطة الآخرين» فيرى ما سجّله هو فقط.
     */
    public static function timeline(int $workspaceId, int $organizationId, int $limit = 100, ?int $onlyUserId = null): array
    {
        $params = ['w' => $workspaceId, 'o' => $organizationId];
        $mine = '';
        if ($onlyUserId !== null) {
            $mine = ' AND a.user_id = :me';
            $params['me'] = $onlyUserId;
        }
        return Database::select(
            "SELECT a.*, u.name AS user_name, c.name AS contact_name
               FROM crm_activities a
               LEFT JOIN users u ON u.id = a.user_id
               LEFT JOIN crm_contacts c ON c.id = a.contact_id
              WHERE a.workspace_id = :w AND a.organization_id = :o{$mine}
              ORDER BY a.occurred_at DESC, a.id DESC LIMIT {$limit}",
            $params
        );
    }

    /**
     * تسجيل نشاط: يحدّث آخر تواصل على العلاقة، وإن كان له موعد متابعة أنشأ مهمة
     * مرتبطة بالجهة وأشعر مسؤول العلاقة.
     */
    public static function log(array $data, array $workspace, array $organization, ?int $assignFollowUpTo = null): int
    {
        $data['occurred_at'] = $data['occurred_at'] ?? date('Y-m-d H:i:s');
        $data['user_id'] = $data['user_id'] ?? Auth::id();
        if (!empty($data['next_action_at'])) {
            $data['next_action_owner_id'] = $assignFollowUpTo ?: $data['user_id'];
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['next_action_status'] = !empty($data['next_action_at']) ? 'pending' : 'none';

        $activityId = Database::insert('crm_activities', $data);

        // تحديث العلاقة: آخر تواصل + المتابعة القادمة
        $update = ['last_activity_at' => $data['occurred_at'], 'updated_at' => date('Y-m-d H:i:s')];
        if (!empty($data['next_action_at'])) {
            $update['next_action_at'] = $data['next_action_at'];
        }
        Database::update(
            'crm_workspace_orgs',
            $update,
            'workspace_id = :w AND organization_id = :o',
            ['w' => (int) $workspace['id'], 'o' => (int) $organization['id']]
        );

        if (!empty($data['next_action_at'])) {
            self::createFollowUpTask($activityId, $data, $workspace, $organization, $assignFollowUpTo);
        }

        return $activityId;
    }

    /** إنشاء مهمة المتابعة في إضافة المهام (إن كانت مفعّلة) وربطها بالجهة. */
    private static function createFollowUpTask(int $activityId, array $data, array $workspace, array $organization, ?int $assignTo): void
    {
        if (!ModuleManager::isActive('tasks')) {
            return;
        }
        $assignee = $assignTo ?: Auth::id();
        $note = trim((string) ($data['next_action_note'] ?? ''));
        $title = 'متابعة: ' . mb_substr($organization['name'], 0, 120);

        $taskId = Database::insert('tasks_tasks', [
            'company_id' => (int) $organization['company_id'],
            'title' => $title,
            'description' => ($note !== '' ? $note . "\n\n" : '')
                . 'متابعة ناتجة عن نشاط CRM في مساحة «' . $workspace['name'] . '».',
            'assignee_id' => $assignee,
            'creator_id' => Auth::id(),
            'due_date' => date('Y-m-d', strtotime((string) $data['next_action_at'])),
            'priority' => 'medium',
            'status' => 'todo',
            'requires_approval' => 0,
            'linked_type' => 'crm_org',
            'linked_id' => (int) $organization['id'],
            'linked_label' => mb_substr($organization['name'], 0, 200),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        Database::update('crm_activities', ['task_id' => $taskId], 'id = :id', ['id' => $activityId]);

        if ($assignee !== Auth::id()) {
            Notification::send(
                $assignee,
                '🔔 متابعة CRM جديدة',
                $organization['name'] . ($note !== '' ? ' — ' . $note : ''),
                route('/crm/w/' . $workspace['id'] . '/orgs/' . $organization['id'])
            );
        }
    }

    /** إنهاء متابعة: تُغلق مهمتها أيضاً حتى لا تبقى معلّقة في مهام الموظف. */
    public static function completeFollowUp(int $activityId): void
    {
        $activity = self::find($activityId);
        if (!$activity) {
            return;
        }
        Database::update('crm_activities', ['next_action_status' => 'done'], 'id = :id', ['id' => $activityId]);

        if (!empty($activity['task_id']) && ModuleManager::isActive('tasks')) {
            Database::update(
                'tasks_tasks',
                ['status' => 'done', 'completed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
                'id = :id AND status != :done',
                ['id' => (int) $activity['task_id'], 'done' => 'done']
            );
        }

        // إن لم تبقَ متابعات معلّقة للجهة نُفرغ موعد المتابعة من العلاقة
        $stillPending = Database::first(
            "SELECT MIN(next_action_at) AS next FROM crm_activities
              WHERE workspace_id = :w AND organization_id = :o AND next_action_status = 'pending'",
            ['w' => $activity['workspace_id'], 'o' => $activity['organization_id']]
        );
        Database::update(
            'crm_workspace_orgs',
            ['next_action_at' => $stillPending['next'] ?? null],
            'workspace_id = :w AND organization_id = :o',
            ['w' => $activity['workspace_id'], 'o' => $activity['organization_id']]
        );
    }

    /** متابعات المستخدم المستحقة (لشاشة «عملي اليوم» ومزود التقويم). */
    public static function dueFollowUps(int $companyId, int $userId, array $workspaceIds, ?string $untilDate = null): array
    {
        if (!$workspaceIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($workspaceIds), '?'));
        $params = array_merge($workspaceIds, [$userId]);
        $dateSql = '';
        if ($untilDate) {
            $dateSql = ' AND a.next_action_at <= ?';
            $params[] = $untilDate . ' 23:59:59';
        }
        return Database::select(
            "SELECT a.*, o.name AS organization_name, w.name AS workspace_name, w.icon
               FROM crm_activities a
               JOIN crm_organizations o ON o.id = a.organization_id
               JOIN crm_workspaces w ON w.id = a.workspace_id
              WHERE a.workspace_id IN ({$placeholders})
                AND a.next_action_status = 'pending'
                AND COALESCE(a.next_action_owner_id, a.user_id) = ?{$dateSql}
              ORDER BY a.next_action_at",
            $params
        );
    }
}

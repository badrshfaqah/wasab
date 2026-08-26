<?php
$block = function (string $title, array $rows, string $badgeClass) {
    if (!$rows) return;
    ?>
    <div class="card">
        <div class="card-title"><span><?= $title ?> <span class="badge badge-<?= $badgeClass ?>"><?= count($rows) ?></span></span></div>
        <?php foreach ($rows as $r): ?>
            <div class="doc-log" style="display:flex;justify-content:space-between;gap:10px;align-items:center;">
                <div>
                    <a href="<?= route('/crm/w/' . $r['workspace_id'] . '/orgs/' . $r['organization_id']) ?>"><strong><?= e($r['organization_name']) ?></strong></a>
                    <?php if (!empty($r['next_action_note'])): ?><div class="hint"><?= e($r['next_action_note']) ?></div><?php endif; ?>
                    <div class="doc-log-meta"><?= e($r['icon'] ?? '') ?> <?= e($r['workspace_name']) ?></div>
                </div>
                <?php if (!empty($r['next_action_at'])): ?>
                    <span class="badge badge-<?= $badgeClass ?>"><?= format_date($r['next_action_at'], 'Y-m-d') ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
};
?>
<div class="page-head">
    <div><h1>عملي اليوم</h1><p>كل ما ينتظر إجراءً منك عبر مساحاتك — لتعرف فور دخولك ماذا تفعل.</p></div>
    <a class="btn btn-outline" href="<?= route('/crm') ?>">المساحات</a>
</div>

<?php if (!$overdue && !$today && !$upcoming && !$untouched && !$tasks): ?>
    <div class="card"><div class="empty-state"><div class="ic">✅</div>
        لا يوجد ما ينتظرك اليوم — سجّل نشاطاً وحدد متابعته ليظهر هنا في موعده.
    </div></div>
<?php endif; ?>

<?php $block('⚠️ متابعات متأخرة', $overdue, 'danger'); ?>
<?php $block('🔔 متابعات اليوم', $today, 'info'); ?>
<?php $block('📅 خلال الأسبوع', $upcoming, 'muted'); ?>
<?php $block('🆕 جهات لم يبدأ التواصل معها', $untouched, 'warning'); ?>

<?php if ($tasks): ?>
<div class="card">
    <div class="card-title"><span>📋 مهام CRM المفتوحة <span class="badge badge-info"><?= count($tasks) ?></span></span></div>
    <p class="hint" style="margin-top:0;">هذه مهام حقيقية في إضافة «المهام» أُنشئت من متابعات CRM.</p>
    <?php foreach ($tasks as $t): ?>
        <div class="doc-log" style="display:flex;justify-content:space-between;gap:10px;align-items:center;">
            <div>
                <a href="<?= route('/tasks/' . $t['id']) ?>"><?= e($t['title']) ?></a>
                <?php if (!empty($t['linked_label'])): ?><div class="doc-log-meta">🏢 <?= e($t['linked_label']) ?></div><?php endif; ?>
            </div>
            <?php if (!empty($t['due_date'])): ?>
                <span class="badge badge-<?= $t['due_date'] < date('Y-m-d') ? 'danger' : 'muted' ?>"><?= format_date($t['due_date'], 'Y-m-d') ?></span>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

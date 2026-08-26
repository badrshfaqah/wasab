<div class="page-head">
    <div>
        <h1>مساحات CRM</h1>
        <p>كل مساحة بيئة مستقلة بأعضائها وتصنيفاتها ومراحلها، فوق دليل جهات واحد لا تتكرر فيه الجهة.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn btn-outline" href="<?= route('/crm/today') ?>">📌 عملي اليوم</a>
        <?php if ($isAdmin): ?>
            <a class="btn btn-outline" href="<?= route('/crm/directory') ?>">🏛️ دليل الجهات</a>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?= route('/crm?archived=' . ($showArchived ? '0' : '1')) ?>">
            <?= $showArchived ? '↩ النشطة' : '🗄️ المؤرشفة' ?>
        </a>
        <?php if ($canCreate): ?>
            <a class="btn" href="<?= route('/crm/workspaces/create') ?>">+ مساحة جديدة</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$workspaces): ?>
    <div class="card"><div class="empty-state"><div class="ic">🤝</div>
        <?= $showArchived ? 'لا توجد مساحات مؤرشفة.' : 'لا توجد مساحات متاحة لك بعد.' ?>
        <?php if ($canCreate && !$showArchived): ?>
            <div style="margin-top:12px;"><a class="btn" href="<?= route('/crm/workspaces/create') ?>">أنشئ أول مساحة</a></div>
        <?php endif; ?>
    </div></div>
<?php else: ?>
<div class="cards-row" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
    <?php foreach ($workspaces as $w): ?>
        <div class="card" style="margin-bottom:0;border-top:3px solid <?= e($w['color']) ?>;">
            <div class="card-title">
                <span style="display:flex;align-items:center;gap:8px;">
                    <span style="font-size:1.3em;"><?= e($w['icon']) ?></span>
                    <a href="<?= route('/crm/w/' . $w['id']) ?>"><?= e($w['name']) ?></a>
                </span>
            </div>
            <?php if (!empty($w['description'])): ?>
                <p class="hint" style="margin-top:0;"><?= e($w['description']) ?></p>
            <?php endif; ?>
            <p class="hint" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <span class="badge badge-muted">🏢 <?= (int) $w['orgs_count'] ?> جهة</span>
                <span class="badge badge-muted">👥 <?= (int) $w['members_count'] ?> عضو</span>
                <?php if ($w['my_role'] === 'manager'): ?><span class="badge badge-info">مدير المساحة</span><?php endif; ?>
                <?php if ($w['status'] === 'archived'): ?><span class="badge badge-warning">مؤرشفة</span><?php endif; ?>
            </p>
            <div style="display:flex;gap:8px;">
                <a class="btn btn-sm" href="<?= route('/crm/w/' . $w['id']) ?>">دخول</a>
                <?php if ($w['my_role'] === 'manager'): ?>
                    <a class="btn btn-outline btn-sm" href="<?= route('/crm/w/' . $w['id'] . '/members') ?>">الأعضاء</a>
                    <a class="btn btn-outline btn-sm" href="<?= route('/crm/w/' . $w['id'] . '/edit') ?>">إعدادات</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

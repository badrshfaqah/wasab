<div class="page-head">
    <div><h1>الإشعارات</h1></div>
    <form method="post" action="<?= route('/notifications/read-all') ?>">
        <?= csrf_field() ?>
        <button class="btn btn-outline btn-sm" type="submit">تعليم الكل كمقروء</button>
    </form>
</div>

<div class="card">
<?php if (!$notifications): ?>
    <div class="empty-state"><div class="ic">🔔</div>لا توجد إشعارات</div>
<?php else: ?>
    <?php foreach ($notifications as $n): ?>
        <a class="notif-item<?= $n['is_read'] ? '' : ' unread' ?>" style="display:block;" href="<?= e($n['url'] ?: '#') ?>">
            <div style="display:flex;justify-content:space-between;">
                <strong><?= e($n['title']) ?></strong>
                <span style="color:var(--muted);font-size:12px;"><?= format_date($n['created_at'], 'Y-m-d H:i') ?></span>
            </div>
            <?php if ($n['message']): ?><div style="color:var(--muted);margin-top:4px;"><?= e($n['message']) ?></div><?php endif; ?>
        </a>
    <?php endforeach; ?>
<?php endif; ?>
<?= render_pagination($total, $perPage, $page, route('/notifications')) ?>
</div>

<?php
$tabs = [
    'unread' => 'غير المقروءة',
    'read' => 'المقروءة',
    'all' => 'الكل',
];

$inboxQuery = function (string $scope, array $filters, array $overrides = []): string {
    $params = array_merge(['scope' => $scope], $filters, $overrides);
    $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
    return route('/inbox?' . http_build_query($params));
};
?>
<div class="page-head">
    <div><h1>مركز المراسلات</h1><p>كل رسائل نماذج التواصل من مواقعك في شاشة واحدة<?= $unreadCount > 0 ? ' - <strong>' . (int) $unreadCount . '</strong> غير مقروءة' : '' ?>.</p></div>
    <?php if ($canManageSites): ?>
        <div style="display:flex;gap:8px;">
            <a class="btn btn-outline" href="<?= route('/inbox/sites') ?>">🌐 إدارة المواقع</a>
        </div>
    <?php endif; ?>
</div>

<div class="tabs">
    <?php foreach ($tabs as $key => $label): ?>
        <a class="<?= $scope === $key ? 'active' : '' ?>" href="<?= $inboxQuery($key, $filters) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <form method="get" action="<?= route('/inbox') ?>" class="filters-toolbar" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:16px;">
        <input type="hidden" name="scope" value="<?= e($scope) ?>">
        <div class="field" style="margin:0;flex:1;min-width:180px;">
            <label>بحث</label>
            <input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="اسم المرسل، الإيميل، الجوال، أو نص الرسالة...">
        </div>
        <div class="field" style="margin:0;min-width:180px;">
            <label>الموقع المصدر</label>
            <select name="site_id">
                <option value="">كل المواقع</option>
                <?php foreach ($sites as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= (int) ($filters['site_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-sm" type="submit">بحث</button>
        <?php if ($filters): ?>
            <a class="btn btn-outline btn-sm" href="<?= $inboxQuery($scope, []) ?>">مسح الفلاتر</a>
        <?php endif; ?>
    </form>

    <div class="table-wrap">
    <table>
        <thead><tr><th>المرسل</th><th>الرسالة</th><th>الموقع المصدر</th><th>وقت الاستلام</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if (!$messages): ?>
            <tr><td colspan="5"><div class="empty-state"><div class="ic">📨</div>لا توجد رسائل مطابقة</div></td></tr>
        <?php endif; ?>
        <?php foreach ($messages as $m): ?>
            <?php $bold = $m['is_read'] ? '' : 'font-weight:700;'; ?>
            <tr>
                <td style="<?= $bold ?>">
                    <?= e($m['sender_name'] ?: 'مجهول') ?>
                    <?php if ($m['sender_email'] || $m['sender_phone']): ?>
                        <div class="hint" style="font-weight:400;"><?= e($m['sender_email'] ?: $m['sender_phone']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="<?= $bold ?>max-width:340px;">
                    <a href="<?= route('/inbox/' . $m['id']) ?>">
                        <?= e($m['subject'] ?: mb_substr($m['body'], 0, 60) . (mb_strlen($m['body']) > 60 ? '...' : '')) ?>
                    </a>
                    <?php if ($m['subject']): ?>
                        <div class="hint" style="font-weight:400;"><?= e(mb_substr($m['body'], 0, 60)) ?><?= mb_strlen($m['body']) > 60 ? '...' : '' ?></div>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-info"><?= e($m['site_name'] ?? 'موقع محذوف') ?></span></td>
                <td style="<?= $bold ?>"><?= format_date($m['received_at'], 'Y-m-d H:i') ?></td>
                <td><?= $m['is_read'] ? '<span class="badge badge-muted">مقروءة</span>' : '<span class="badge badge-warning">غير مقروءة</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?= render_pagination($total, $perPage, $page, $inboxQuery($scope, $filters)) ?>
</div>

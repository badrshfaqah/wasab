<div class="page-head">
    <div><h1>سجل التغييرات — <?= e($workspace['name']) ?></h1><p>من فعل ماذا ومتى داخل هذه المساحة.</p></div>
    <a class="btn btn-outline" href="<?= route('/crm/w/' . $workspace['id']) ?>">↩ المساحة</a>
</div>
<div class="card">
    <?php if (!$logs): ?><div class="empty-state"><div class="ic">🕓</div>لا سجل بعد.</div><?php endif; ?>
    <div class="table-wrap">
    <table class="table-cards">
        <thead><tr><th>العملية</th><th>المستخدم</th><th>التاريخ</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
            <tr>
                <td><?= e($l['description']) ?><div class="hint"><?= e($l['action']) ?></div></td>
                <td><?= e($l['user_name'] ?? 'النظام') ?></td>
                <td><?= format_date($l['created_at'], 'Y-m-d H:i') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

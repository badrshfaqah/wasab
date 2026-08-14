<?php $typeLabels = ['company' => 'شركة', 'person' => 'فرد']; ?>
<div class="page-head">
    <div><h1>العملاء</h1><p>سجل عملاء الشركة — اربطهم بالمهام والأرشيف لتتبّع كل ما يخصهم.</p></div>
    <?php if ($canCreate): ?><a class="btn" href="<?= route('/clients/create') ?>">+ عميل جديد</a><?php endif; ?>
</div>

<div class="card">
    <form method="get" action="<?= route('/clients') ?>" style="display:flex;gap:8px;margin-bottom:14px;max-width:420px;">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="بحث بالاسم أو الهاتف...">
        <button class="btn btn-sm" type="submit">بحث</button>
    </form>
    <div class="table-wrap">
    <table>
        <thead><tr><th>العميل</th><th>النوع</th><th>المسؤول</th><th>الهاتف</th><th>البريد</th></tr></thead>
        <tbody>
        <?php if (!$clients): ?>
            <tr><td colspan="5"><div class="empty-state"><div class="ic">👔</div><h3>لا عملاء بعد</h3><p>أضف أول عميل من زر «عميل جديد».</p></div></td></tr>
        <?php endif; ?>
        <?php foreach ($clients as $c): ?>
            <tr>
                <td><a href="<?= route('/clients/' . $c['id']) ?>"><strong><?= e($c['name']) ?></strong></a></td>
                <td><span class="badge badge-muted"><?= e($typeLabels[$c['type']] ?? $c['type']) ?></span></td>
                <td><?= e($c['contact_name'] ?: '—') ?></td>
                <td dir="ltr" style="text-align:end;"><?= e($c['phone'] ?: '—') ?></td>
                <td dir="ltr" style="text-align:end;"><?= e($c['email'] ?: '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

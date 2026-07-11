<?php
$typeLabels = ['internal' => 'داخلي (تحويلة)', 'external' => 'خارجي'];
?>
<div class="page-head">
    <div><h1>دليل جهات الاتصال</h1><p>جهات داخلية (تحويلات الموظفين) وخارجية، عامة للشركة أو خاصة بك.</p></div>
    <a class="btn" href="<?= route('/phone/contacts/create') ?>">+ جهة اتصال جديدة</a>
</div>

<div class="card">
    <form method="get" action="<?= route('/phone/contacts') ?>" style="display:flex;gap:10px;align-items:flex-end;margin-bottom:16px;">
        <div class="field" style="margin:0;flex:1;min-width:200px;">
            <label>بحث بالاسم أو الرقم</label>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="اكتب اسماً أو رقماً...">
        </div>
        <button class="btn btn-sm" type="submit">بحث</button>
    </form>

    <div class="table-wrap">
    <table>
        <thead><tr><th>النوع</th><th>الاسم</th><th>الرقم</th><th>ملاحظات</th><th>الظهور</th><th></th></tr></thead>
        <tbody>
        <?php if (!$contacts): ?>
            <tr><td colspan="6"><div class="empty-state"><div class="ic">📇</div>لا توجد جهات اتصال مطابقة</div></td></tr>
        <?php endif; ?>
        <?php foreach ($contacts as $c): ?>
            <?php
            $isInternal = $c['type'] === 'internal';
            $number = $isInternal ? $c['linked_extension'] : $c['phone_number'];
            $displayName = $isInternal ? ($c['linked_user_name'] ?? $c['name']) : $c['name'];
            $canDial = $number && (!$isInternal || (int) $c['linked_enabled'] === 1);
            $canEditThis = $c['visibility'] === 'private'
                ? ($canManagePublic || (int) $c['created_by'] === (int) current_user()['id'])
                : $canManagePublic;
            ?>
            <tr>
                <td><?= $isInternal ? '🧑‍💼' : '☎️' ?> <?= e($typeLabels[$c['type']]) ?></td>
                <td><?= e($displayName) ?><?php if ($isInternal && (int) $c['linked_enabled'] !== 1): ?> <span class="hint">(الهاتف موقوف)</span><?php endif; ?></td>
                <td dir="ltr" style="text-align:left;"><?= e($number ?: '—') ?></td>
                <td><?= e($c['notes'] ?: '—') ?></td>
                <td><span class="badge <?= $c['visibility'] === 'public' ? 'badge-info' : 'badge-muted' ?>"><?= $c['visibility'] === 'public' ? 'عام للشركة' : 'خاص بك' ?></span></td>
                <td style="display:flex;gap:6px;justify-content:flex-end;">
                    <?php if ($canDial): ?>
                        <button type="button" class="btn btn-sm" onclick="window.__phoneDial && window.__phoneDial('<?= e($number) ?>')">📞 اتصال</button>
                    <?php endif; ?>
                    <?php if ($canEditThis): ?>
                        <a class="btn btn-outline btn-sm" href="<?= route('/phone/contacts/' . $c['id'] . '/edit') ?>">تعديل</a>
                        <form method="post" action="<?= route('/phone/contacts/' . $c['id'] . '/delete') ?>" data-confirm="حذف جهة الاتصال هذه؟">
                            <?= csrf_field() ?>
                            <button class="btn btn-danger btn-sm" type="submit">حذف</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

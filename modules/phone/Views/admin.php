<div class="page-head">
    <div><h1>إعدادات الهاتف للشركات</h1><p>حدّد مفتاح Innocalls API لكل شركة. هذه الخطوة مطلوبة قبل أن يستطيع موظفو الشركة استخدام الهاتف.</p></div>
</div>

<div class="card">
    <div class="table-wrap">
    <table>
        <thead><tr><th>الشركة</th><th>مفتاح API</th><th></th></tr></thead>
        <tbody>
        <?php if (!$companies): ?>
            <tr><td colspan="3"><div class="empty-state"><div class="ic">🏢</div>لا توجد شركات بعد</div></td></tr>
        <?php endif; ?>
        <?php foreach ($companies as $c): ?>
            <tr>
                <td><?= e($c['name']) ?></td>
                <td>
                    <form method="post" action="<?= route('/phone/admin/' . $c['id']) ?>" style="display:flex;gap:8px;">
                        <?= csrf_field() ?>
                        <input type="text" name="api_key" value="<?= e($settings[$c['id']] ?? '') ?>" placeholder="لم يُحدَّد بعد" style="min-width:280px;">
                        <button class="btn btn-sm" type="submit">حفظ</button>
                    </form>
                </td>
                <td><?= !empty($settings[$c['id']]) ? status_badge('active') : status_badge('pending') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

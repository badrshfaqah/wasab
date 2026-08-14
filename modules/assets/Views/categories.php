<div class="page-head">
    <div><h1>تصنيفات الأصول</h1><p>نظّم أصول الشركة في تصنيفات (لابتوبات، أثاث، سيارات...).</p></div>
    <a class="btn btn-outline" href="<?= route('/custody') ?>">← الأصول</a>
</div>

<div class="card" style="max-width:640px;">
    <form method="post" action="<?= route('/custody/categories') ?>">
        <?= csrf_field() ?>
        <div class="grid-2">
            <div class="field"><label>تصنيف جديد</label><input type="text" name="name" required maxlength="120" placeholder="مثال: سيارات"></div>
            <div class="field"><label>حقول مخصصة (اختياري)</label><input type="text" name="fields" placeholder="رقم اللوحة، رقم الاستمارة"></div>
        </div>
        <p class="hint" style="margin:-6px 0 10px;">افصل الحقول بفاصلة — تظهر تلقائياً في نموذج أي أصل بهذا التصنيف (حتى 10 حقول).</p>
        <button class="btn" type="submit">إضافة</button>
    </form>
</div>

<div class="card" style="max-width:640px;">
    <div class="table-wrap">
    <table>
        <thead><tr><th>التصنيف</th><th>الحقول المخصصة</th><th>الأصول</th><th></th></tr></thead>
        <tbody>
        <?php if (!$categories): ?>
            <tr><td colspan="4"><div class="empty-state"><div class="ic">🏷️</div>لا تصنيفات بعد</div></td></tr>
        <?php endif; ?>
        <?php foreach ($categories as $c): ?>
            <?php $catFields = \Modules\Assets\Models\AssetCategory::fields($c); ?>
            <tr>
                <td><?= e($c['name']) ?></td>
                <td>
                    <form method="post" action="<?= route('/custody/categories/' . $c['id'] . '/fields') ?>" style="display:flex;gap:6px;align-items:center;">
                        <?= csrf_field() ?>
                        <input type="text" name="fields" value="<?= e(implode('، ', $catFields)) ?>" placeholder="بلا حقول" style="min-width:150px;padding:5px 8px;font-size:12.5px;">
                        <button class="btn btn-ghost btn-sm" type="submit" title="حفظ الحقول">💾</button>
                    </form>
                </td>
                <td><?= (int) $c['assets_count'] ?></td>
                <td>
                    <form method="post" action="<?= route('/custody/categories/' . $c['id'] . '/delete') ?>" onsubmit="return confirm('حذف التصنيف؟ الأصول ستبقى بلا تصنيف.');">
                        <?= csrf_field() ?><button class="btn btn-outline btn-sm" type="submit">حذف</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

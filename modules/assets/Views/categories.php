<div class="page-head">
    <div><h1>تصنيفات الأصول</h1><p>نظّم أصول الشركة في تصنيفات (لابتوبات، أثاث، سيارات...).</p></div>
    <a class="btn btn-outline" href="<?= route('/assets') ?>">← الأصول</a>
</div>

<div class="card" style="max-width:520px;">
    <form method="post" action="<?= route('/assets/categories') ?>" style="display:flex;gap:8px;align-items:flex-end;">
        <?= csrf_field() ?>
        <div class="field" style="margin:0;flex:1;"><label>تصنيف جديد</label><input type="text" name="name" required maxlength="120" placeholder="مثال: أجهزة حاسب"></div>
        <button class="btn" type="submit">إضافة</button>
    </form>
</div>

<div class="card" style="max-width:520px;">
    <div class="table-wrap">
    <table>
        <thead><tr><th>التصنيف</th><th>عدد الأصول</th><th></th></tr></thead>
        <tbody>
        <?php if (!$categories): ?>
            <tr><td colspan="3"><div class="empty-state"><div class="ic">🏷️</div>لا تصنيفات بعد</div></td></tr>
        <?php endif; ?>
        <?php foreach ($categories as $c): ?>
            <tr>
                <td><?= e($c['name']) ?></td>
                <td><?= (int) $c['assets_count'] ?></td>
                <td>
                    <form method="post" action="<?= route('/assets/categories/' . $c['id'] . '/delete') ?>" onsubmit="return confirm('حذف التصنيف؟ الأصول ستبقى بلا تصنيف.');">
                        <?= csrf_field() ?><button class="btn btn-outline btn-sm" type="submit">حذف</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

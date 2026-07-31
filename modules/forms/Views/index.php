<div class="page-head">
    <div><h1>النماذج</h1><p>مولّد خطابات الموارد البشرية الجاهزة (تعريف راتب، تعريف عمل، تعريف بنك...).</p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php if ($canManage): ?>
            <a class="btn btn-outline" href="<?= route('/forms/settings') ?>">⚙️ الترويسة والتوقيع</a>
            <a class="btn btn-outline" href="<?= route('/forms/templates') ?>">📄 القوالب</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($canGenerate): ?>
<div class="card">
    <div class="card-title"><span>✍️ توليد خطاب جديد</span></div>
    <?php if (!$templates): ?>
        <p class="hint" style="margin:0;">لا توجد قوالب مفعّلة. <?= $canManage ? '<a href="' . route('/forms/templates') . '">أضف قالباً</a>.' : 'اطلب من المدير إضافة قالب.' ?></p>
    <?php else: ?>
        <form method="get" action="<?= route('/forms/generate') ?>" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
            <div class="field" style="margin:0;flex:1;min-width:220px;">
                <label>اختر النموذج</label>
                <select name="template" required>
                    <option value="">— اختر —</option>
                    <?php foreach ($templates as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn" type="submit">التالي ←</button>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-title"><span>الخطابات المولّدة</span></div>
    <form method="get" action="<?= route('/forms') ?>" style="margin-bottom:14px;display:flex;gap:8px;">
        <input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="بحث بالعنوان، الرقم، أو المستفيد..." style="flex:1;">
        <button class="btn btn-sm" type="submit">بحث</button>
    </form>
    <div class="table-wrap">
    <table class="table-cards">
        <thead><tr><th>الرقم</th><th>النموذج</th><th>المستفيد</th><th>أنشأه</th><th>التاريخ</th><th></th></tr></thead>
        <tbody>
        <?php if (!$letters): ?>
            <tr><td colspan="6"><div class="empty-state"><div class="ic">📝</div>لا خطابات مولّدة بعد</div></td></tr>
        <?php endif; ?>
        <?php foreach ($letters as $l): ?>
            <tr>
                <td dir="ltr" style="text-align:end;"><?= e($l['number']) ?></td>
                <td><a href="<?= route('/forms/' . $l['id']) ?>"><strong><?= e($l['title']) ?></strong></a></td>
                <td><?= e($l['recipient_name'] ?: '—') ?></td>
                <td><?= e($l['creator_name'] ?? '—') ?></td>
                <td><?= format_date($l['created_at'], 'Y-m-d') ?></td>
                <td><a class="btn btn-outline btn-sm" href="<?= route('/forms/' . $l['id'] . '/print') ?>" target="_blank" rel="noopener">🖨️ طباعة</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?= render_pagination($total, $perPage, $page, route('/forms')) ?>
</div>

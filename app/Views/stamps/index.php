<?php /** @var array $stamps */ ?>
<div class="page-head">
    <div><h1>أختام الشركة</h1><p>مكتبة الأختام — تُربط بقوالب المستندات والنماذج فتُطبَّق تلقائياً على ما يُولَّد منها.</p></div>
</div>

<div class="card" style="max-width:640px;">
    <div class="card-title"><span>الأختام المضافة</span></div>
    <?php if (!$stamps): ?>
        <div class="empty-state"><div class="ic">🔖</div>لا توجد أختام بعد — أضف أول ختم بالأسفل.</div>
    <?php else: ?>
        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:6px;">
            <?php foreach ($stamps as $st): ?>
                <div style="border:1px solid var(--border);border-radius:10px;padding:12px;text-align:center;width:160px;">
                    <img src="<?= e(\App\Core\CompanyStamp::imageUrl($st)) ?>" alt="" style="max-height:80px;max-width:100%;">
                    <div class="hint" style="margin-top:8px;word-break:break-word;"><?= e($st['name']) ?></div>
                    <form method="post" action="<?= route('/stamps/' . $st['id'] . '/delete') ?>" onsubmit="return confirm('حذف هذا الختم؟ القوالب المرتبطة به ستفقد ختمها.');" style="margin-top:8px;">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline btn-sm" type="submit">حذف</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card" style="max-width:640px;">
    <div class="card-title"><span>➕ إضافة ختم</span></div>
    <form method="post" action="<?= route('/stamps') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="field"><label>اسم الختم</label><input type="text" name="name" maxlength="120" placeholder="مثال: ختم الشركة الرسمي"></div>
        <div class="field"><label>صورة الختم</label><input type="file" name="image" accept="image/png,image/jpeg,image/webp" required><div class="hint">يُفضّل PNG بخلفية شفافة ليظهر فوق المستند بشكل طبيعي.</div></div>
        <button class="btn" type="submit">حفظ الختم</button>
    </form>
</div>

<div class="page-head">
    <div>
        <h1><?= e($letter['title']) ?></h1>
        <p>رقم الخطاب: <strong dir="ltr"><?= e($letter['number']) ?></strong><?= $letter['recipient_name'] ? ' · المستفيد: ' . e($letter['recipient_name']) : '' ?> · <?= format_date($letter['created_at'], 'Y-m-d') ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn btn-outline" href="<?= route('/forms') ?>">← القائمة</a>
        <?php if (!empty($letter['employee_id'])): ?>
        <form method="post" action="<?= route('/forms/' . $letter['id'] . '/email') ?>" onsubmit="return confirm('إرسال الخطاب لبريد الموظف؟');">
            <?= csrf_field() ?><button class="btn btn-outline" type="submit">📧 إرسال بالبريد</button>
        </form>
        <?php endif; ?>
        <a class="btn" href="<?= route('/forms/' . $letter['id'] . '/print') ?>" target="_blank" rel="noopener">🖨️ طباعة / حفظ PDF</a>
        <?php if ($canDelete): ?>
        <form method="post" action="<?= route('/forms/' . $letter['id'] . '/delete') ?>" onsubmit="return confirm('حذف هذا الخطاب؟');">
            <?= csrf_field() ?><button class="btn btn-danger" type="submit">حذف</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- معاينة PDF داخل الموقع: الخطاب النهائي بترويسته وتوقيعه وختمه -->
<div class="card">
    <div class="card-title divided">
        <span>📄 معاينة الخطاب (PDF)</span>
        <a class="btn btn-ghost btn-sm" href="<?= route('/forms/' . $letter['id'] . '/print') ?>" target="_blank" rel="noopener">⛶ ملء الشاشة</a>
    </div>
    <iframe src="<?= route('/forms/' . $letter['id'] . '/print?embed=1') ?>"
            style="width:100%;height:75vh;min-height:420px;border:1px solid var(--border);border-radius:10px;background:#9ca3af;"
            loading="lazy" title="معاينة الخطاب"></iframe>
</div>

<div class="card">
    <div class="card-title divided"><span>النص</span></div>
    <div style="white-space:pre-wrap;line-height:2.1;font-size:15px;"><?= e($letter['body']) ?></div>
</div>

<?php if (!empty($letter['verify_token'])): ?>
<div class="card">
    <div class="card-title"><span>🔎 رابط التحقق العام</span></div>
    <p class="hint" style="margin-top:0;">أي شخص يملك هذا الرابط يمكنه التأكد من صحة الخطاب وبياناته الأساسية (دون رؤية نصّه).</p>
    <?php $vurl = base_url('forms/verify/' . $letter['verify_token']); ?>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <input type="text" readonly value="<?= e($vurl) ?>" onclick="this.select()" style="flex:1;min-width:240px;padding:8px;border:1px solid var(--border);border-radius:8px;direction:ltr;">
        <button type="button" class="btn btn-outline btn-sm" data-copy="<?= e($vurl) ?>">نسخ الرابط</button>
        <a class="btn btn-outline btn-sm" href="<?= $vurl ?>" target="_blank" rel="noopener">فتح</a>
    </div>
    <p class="hint" style="margin-bottom:0;">رمز التحقق المختصر: <strong><?= e(strtoupper(substr((string) $letter['verify_token'], 0, 8))) ?></strong></p>
</div>
<?php endif; ?>

<?php /** @var array $own @var array $templates */ ?>
<div class="page-head">
    <div><h1>طلب خطاب</h1><p>يصل طلبك للإدارة، وعند الاعتماد يصدر الخطاب باسمك تلقائياً ويصلك إشعار.</p></div>
    <a class="btn btn-outline" href="<?= route('/forms/requests') ?>">← الطلبات</a>
</div>

<div class="card" style="max-width:560px;">
    <p class="hint" style="margin-top:0;">الطلب باسم: <strong><?= e($own['full_name']) ?></strong></p>
    <form method="post" action="<?= route('/forms/requests') ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label>نوع الخطاب <span class="req">*</span></label>
            <select name="template_id" required>
                <option value="">— اختر —</option>
                <?php foreach ($templates as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>ملاحظة (اختياري)</label>
            <textarea name="note" rows="2" maxlength="500" placeholder="مثال: مطلوب لجهة حكومية / بالإنجليزية..."></textarea>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit">📨 إرسال الطلب</button>
            <a class="btn btn-outline" href="<?= route('/forms/requests') ?>">إلغاء</a>
        </div>
    </form>
</div>

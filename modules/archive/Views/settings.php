<div class="page-head">
    <div><h1>إعدادات الأرشيف</h1></div>
    <a class="btn btn-outline" href="<?= route('/archive') ?>">↩ رجوع للملفات</a>
</div>

<div class="card" style="max-width:520px;">
    <form method="post" action="<?= route('/archive/settings') ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label>عدد الأيام قبل انتهاء الصلاحية لإظهار التنبيه</label>
            <input type="number" name="expiry_warning_days" min="1" max="365" value="<?= (int) $settings['expiry_warning_days'] ?>">
            <p class="hint">تظهر الملفات ضمن "الملفات التي قاربت على الانتهاء" بالصفحة الرئيسية عندما يتبقى هذا العدد من الأيام أو أقل.</p>
        </div>

        <hr style="border:0;border-top:1px solid var(--border);margin:20px 0;">
        <h3 style="margin:0 0 4px;font-size:16px;">🗂️ سياسة الاحتفاظ (Retention)</h3>
        <p class="hint" style="margin:0 0 14px;">للامتثال: تُطبَّق تلقائياً (يومياً) على الملفات الأقدم من المدة المحددة منذ رفعها.</p>
        <div class="field">
            <label>مدة الاحتفاظ (بالشهور)</label>
            <input type="number" name="retention_months" min="0" max="600" value="<?= (int) ($settings['retention_months'] ?? 0) ?>">
            <p class="hint">0 = تعطيل السياسة. مثال: 60 = خمس سنوات.</p>
        </div>
        <div class="field">
            <label>الإجراء بعد انقضاء المدة</label>
            <select name="retention_action">
                <?php $ra = $settings['retention_action'] ?? 'none'; ?>
                <option value="none" <?= $ra === 'none' ? 'selected' : '' ?>>لا شيء (تعطيل)</option>
                <option value="archive" <?= $ra === 'archive' ? 'selected' : '' ?>>أرشفة الملف (يبقى محفوظاً كـ«مؤرشف»)</option>
                <option value="trash" <?= $ra === 'trash' ? 'selected' : '' ?>>نقل لسلة المحذوفات (قابل للاسترجاع)</option>
            </select>
            <p class="hint">النقل لسلة المحذوفات حذف ناعم قابل للاسترجاع — وليس محواً نهائياً.</p>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit">حفظ الإعدادات</button>
        </div>
    </form>
</div>

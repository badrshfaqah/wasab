<div class="page-head">
    <div><h1>طلب مصروف</h1><p>يصل الطلب للمدير للاعتماد، ويصلك إشعار بالقرار.</p></div>
    <a class="btn btn-outline" href="<?= route('/expenses') ?>">← المصروفات</a>
</div>

<div class="card" style="max-width:560px;">
    <form method="post" action="<?= route('/expenses') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="grid-2">
            <div class="field"><label>المبلغ <span class="req">*</span></label><input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00"></div>
            <div class="field"><label>تاريخ المصروف <span class="req">*</span></label><input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required></div>
        </div>
        <div class="field">
            <label>الوصف <span class="req">*</span></label>
            <textarea name="description" rows="2" maxlength="500" required placeholder="مثال: وقود مشوار العميل، قرطاسية للمكتب..."></textarea>
        </div>
        <div class="field">
            <label>صورة الفاتورة/الإيصال (اختياري)</label>
            <input type="file" name="receipt" accept="image/png,image/jpeg,image/webp">
            <p class="hint">صوّر الإيصال بجوالك مباشرة — يظهر للمدير مع الطلب.</p>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit">💰 تقديم الطلب</button>
            <a class="btn btn-outline" href="<?= route('/expenses') ?>">إلغاء</a>
        </div>
    </form>
</div>

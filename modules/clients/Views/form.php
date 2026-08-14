<?php $isEdit = $client !== null; ?>
<div class="page-head">
    <div><h1><?= $isEdit ? 'تعديل عميل' : 'عميل جديد' ?></h1></div>
    <a class="btn btn-outline" href="<?= $isEdit ? route('/clients/' . $client['id']) : route('/clients') ?>">← رجوع</a>
</div>

<div class="card" style="max-width:620px;">
    <form method="post" action="<?= $isEdit ? route('/clients/' . $client['id']) : route('/clients') ?>">
        <?= csrf_field() ?>
        <div class="grid-2">
            <div class="field"><label>الاسم <span class="req">*</span></label><input type="text" name="name" required maxlength="200" value="<?= e($client['name'] ?? '') ?>"></div>
            <div class="field"><label>النوع</label>
                <select name="type">
                    <option value="company" <?= ($client['type'] ?? 'company') === 'company' ? 'selected' : '' ?>>شركة</option>
                    <option value="person" <?= ($client['type'] ?? '') === 'person' ? 'selected' : '' ?>>فرد</option>
                </select>
            </div>
        </div>
        <div class="grid-2">
            <div class="field"><label>الشخص المسؤول</label><input type="text" name="contact_name" maxlength="150" value="<?= e($client['contact_name'] ?? '') ?>"></div>
            <div class="field"><label>الهاتف</label><input type="tel" name="phone" maxlength="50" value="<?= e($client['phone'] ?? '') ?>"></div>
        </div>
        <div class="grid-2">
            <div class="field"><label>البريد</label><input type="email" name="email" maxlength="150" value="<?= e($client['email'] ?? '') ?>"></div>
            <div class="field"><label>العنوان</label><input type="text" name="address" maxlength="300" value="<?= e($client['address'] ?? '') ?>"></div>
        </div>
        <div class="field"><label>ملاحظات</label><textarea name="notes" rows="3"><?= e($client['notes'] ?? '') ?></textarea></div>
        <div class="form-actions">
            <button class="btn" type="submit"><?= $isEdit ? 'حفظ' : 'إضافة العميل' ?></button>
            <a class="btn btn-outline" href="<?= route('/clients') ?>">إلغاء</a>
        </div>
    </form>
</div>

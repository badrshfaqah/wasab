<?php $isEdit = $company !== null; ?>
<div class="page-head">
    <div><h1><?= $isEdit ? 'تعديل شركة' : 'إضافة شركة' ?></h1></div>
</div>

<div class="card" style="max-width:560px;">
    <form method="post" enctype="multipart/form-data" action="<?= $isEdit ? route('/companies/' . $company['id']) : route('/companies') ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label>اسم الشركة</label>
            <input type="text" name="name" value="<?= e($company['name'] ?? '') ?>" required>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>اللون الأساسي</label>
                <input type="color" name="primary_color" value="<?= e($company['primary_color'] ?? '#2563eb') ?>">
            </div>
            <div class="field">
                <label>الحالة</label>
                <select name="status">
                    <option value="active" <?= ($company['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>نشطة</option>
                    <option value="inactive" <?= ($company['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>غير نشطة</option>
                </select>
            </div>
        </div>
        <div class="field">
            <label>الشعار</label>
            <input type="file" name="logo" accept="image/*">
            <?php if (!empty($company['logo'])): ?><p class="hint">الشعار الحالي محفوظ، ارفع ملفاً جديداً لاستبداله.</p><?php endif; ?>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit"><?= $isEdit ? 'حفظ التعديلات' : 'إنشاء الشركة' ?></button>
            <a class="btn btn-outline" href="<?= route('/companies') ?>">إلغاء</a>
        </div>
    </form>
</div>

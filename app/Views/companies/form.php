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
        <?php $currentTheme = $company['theme'] ?? \App\Core\Theme::DEFAULT; ?>
        <div class="field">
            <label>ثيم التصميم (افتراضي الشركة)</label>
            <input type="hidden" name="theme" id="theme-input" value="<?= e($currentTheme) ?>">
            <div class="theme-grid">
                <?php foreach (\App\Core\Theme::presets() as $key => $t): ?>
                    <button type="button" class="theme-card<?= $key === $currentTheme ? ' selected' : '' ?>"
                            data-theme="<?= e($key) ?>" data-primary="<?= e($t['primary']) ?>" data-sidebar="<?= e($t['sidebar']) ?>"
                            onclick="wasabPickTheme(this)">
                        <span class="theme-swatch" style="background:<?= e($t['sidebar']) ?>;">
                            <span style="background:<?= e($t['primary']) ?>;"></span>
                            <span style="background:<?= e($t['bg']) ?>;"></span>
                        </span>
                        <span class="theme-name"><?= e($t['label']) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <p class="hint">يمكن لمدير الشركة تغييره لاحقاً من إعداداته.</p>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>اللون الأساسي</label>
                <input type="color" name="primary_color" id="primary-color-input" value="<?= e($company['primary_color'] ?? '#2563eb') ?>">
            </div>
            <div class="field">
                <label>خلفية القائمة الجانبية</label>
                <input type="color" name="sidebar_color" id="sidebar-color-input" value="<?= e($company['sidebar_color'] ?? '#111827') ?>">
            </div>
        </div>
        <script>
        function wasabPickTheme(btn) {
            document.querySelectorAll('.theme-card').forEach(function (c) { c.classList.remove('selected'); });
            btn.classList.add('selected');
            document.getElementById('theme-input').value = btn.getAttribute('data-theme');
            document.getElementById('primary-color-input').value = btn.getAttribute('data-primary');
            document.getElementById('sidebar-color-input').value = btn.getAttribute('data-sidebar');
        }
        </script>
        <div class="field">
            <label>الحالة</label>
            <select name="status">
                <option value="active" <?= ($company['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>نشطة</option>
                <option value="inactive" <?= ($company['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>غير نشطة</option>
            </select>
        </div>
        <div class="field">
            <label>الشعار</label>
            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
            <?php if (!empty($company['logo'])): ?>
                <div style="margin-top:8px;display:flex;align-items:center;gap:10px;">
                    <img src="<?= e(route('/media/companies/' . $company['logo'])) ?>" alt="الشعار الحالي" style="height:40px;border-radius:6px;">
                    <p class="hint" style="margin:0;">الشعار الحالي محفوظ، ارفع ملفاً جديداً لاستبداله.</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit"><?= $isEdit ? 'حفظ التعديلات' : 'إنشاء الشركة' ?></button>
            <a class="btn btn-outline" href="<?= route('/companies') ?>">إلغاء</a>
        </div>
    </form>
</div>

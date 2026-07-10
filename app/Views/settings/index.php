<?php use App\Core\Auth; ?>
<div class="page-head">
    <div><h1>الإعدادات</h1><p><?= Auth::isSystemAdmin() ? 'إعدادات النظام العامة.' : 'تخصيص شعار وألوان شركتك.' ?></p></div>
</div>

<div class="card" style="max-width:560px;">
<?php if (Auth::isSystemAdmin()): ?>
    <form method="post" action="<?= route('/settings') ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label>اسم النظام</label>
            <input type="text" name="app_name" value="<?= e($appName) ?>" required>
        </div>
        <div class="form-actions"><button class="btn" type="submit">حفظ</button></div>
    </form>
<?php else: ?>
    <form method="post" action="<?= route('/settings') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="field">
            <label>اسم الشركة</label>
            <input type="text" name="company_name" value="<?= e($company['name'] ?? '') ?>">
        </div>
        <div class="grid-2">
            <div class="field">
                <label>اللون الأساسي</label>
                <input type="color" name="primary_color" value="<?= e($company['primary_color'] ?? '#2563eb') ?>">
            </div>
            <div class="field">
                <label>خلفية القائمة الجانبية</label>
                <input type="color" name="sidebar_color" value="<?= e($company['sidebar_color'] ?? '#111827') ?>">
                <p class="hint">يُفضّل لون داكن ليبقى نص القائمة الجانبية واضحاً.</p>
            </div>
        </div>
        <div class="field">
            <label>الشعار</label>
            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
            <?php if (!empty($company['logo'])): ?>
                <div style="margin-top:8px;">
                    <img src="<?= e(base_url('storage/uploads/' . $company['logo'])) ?>" alt="الشعار الحالي" style="height:40px;border-radius:6px;">
                </div>
            <?php endif; ?>
        </div>
        <div class="form-actions"><button class="btn" type="submit">حفظ</button></div>
    </form>
<?php endif; ?>
</div>

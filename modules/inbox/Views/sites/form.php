<?php
$platformLabels = ['wordpress' => 'ووردبريس', 'custom' => 'مخصص'];
$isEdit = $site !== null;
$action = $isEdit ? route('/inbox/sites/' . $site['id']) : route('/inbox/sites');
?>
<div class="page-head">
    <div><h1><?= $isEdit ? 'تعديل موقع' : 'إضافة موقع' ?></h1></div>
    <a class="btn btn-outline" href="<?= route('/inbox/sites') ?>">← عودة للمواقع</a>
</div>

<div class="card" style="max-width:560px;">
    <form method="post" action="<?= $action ?>">
        <?= csrf_field() ?>

        <div class="field">
            <label>اسم الموقع *</label>
            <input type="text" name="name" required maxlength="120" value="<?= e($site['name'] ?? '') ?>" placeholder="مثال: موقع المجرات الرئيسي">
        </div>

        <div class="field">
            <label>رابط الموقع (اختياري)</label>
            <input type="url" name="url" dir="ltr" maxlength="255" value="<?= e($site['url'] ?? '') ?>" placeholder="https://example.com">
        </div>

        <div class="field">
            <label>المنصة</label>
            <select name="platform">
                <?php foreach ($platforms as $p): ?>
                    <option value="<?= $p ?>" <?= ($site['platform'] ?? 'custom') === $p ? 'selected' : '' ?>><?= e($platformLabels[$p] ?? $p) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (!$isEdit): ?>
            <p class="hint">مفتاح الربط يولَّد تلقائياً من الخادم بعد الحفظ، وستجده مع كود التركيب الجاهز في قائمة المواقع.</p>
        <?php endif; ?>

        <button class="btn" type="submit"><?= $isEdit ? 'حفظ التعديلات' : 'إضافة الموقع' ?></button>
    </form>
</div>

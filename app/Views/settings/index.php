<?php use App\Core\Auth; ?>
<div class="page-head">
    <div><h1>الإعدادات</h1><p><?= Auth::isSystemAdmin() ? 'إعدادات النظام العامة.' : 'تخصيص شعار وألوان شركتك.' ?></p></div>
</div>

<div class="card" style="max-width:560px;">
<?php if (Auth::isSystemAdmin()): ?>
    <form method="post" action="<?= route('/settings') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="field">
            <label>اسم النظام</label>
            <input type="text" name="app_name" value="<?= e($appName) ?>" required>
            <p class="hint">يظهر ببوابة الدخول والقائمة الجانبية واسم تطبيق الجوال. (هوية "وصاب" ورقم الإصدار يبقيان أسفل النظام).</p>
        </div>
        <div class="field">
            <label>شعار النظام</label>
            <input type="file" name="app_logo" accept="image/png,image/jpeg,image/webp">
            <p class="hint">يظهر ببوابة تسجيل الدخول، وبالقائمة الجانبية للشركات التي لم ترفع شعارها الخاص، وتتولد منه أيقونة تطبيق الجوال تلقائياً. يُفضَّل صورة مربعة بخلفية فاتحة.</p>
            <?php if ($appLogoUrl): ?>
                <div style="margin-top:8px;display:flex;align-items:center;gap:10px;">
                    <img src="<?= e($appLogoUrl) ?>" alt="شعار النظام الحالي" style="height:40px;border-radius:6px;border:1px solid var(--border);">
                    <img src="<?= e(app_icon_url(192)) ?>" alt="أيقونة الجوال" style="height:40px;border-radius:10px;border:1px solid var(--border);" title="أيقونة الجوال المولَّدة">
                </div>
            <?php endif; ?>
        </div>
        <div class="field">
            <label>عنوان الموقع (اختياري)</label>
            <input type="text" name="app_url" value="<?= e($appUrl) ?>" placeholder="https://example.com/system">
            <p class="hint">يُستخدم فقط لبناء روابط صحيحة داخل الإشعارات المُرسلة من مهام الجدولة الدورية (cron.php) مثل تذكير الاجتماعات، حيث لا يوجد طلب متصفح حي لاستنتاج العنوان منه. اتركه فارغاً إن كنت لا تستخدم Cron.</p>
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

        <div class="field">
            <label>موقع الشركة</label>
            <input type="url" name="company_website" dir="ltr" placeholder="https://example.com"
                   value="<?= e($companyWebsite ?? '') ?>">
            <small>يظهر في بطاقة التواصل التي يحفظها من يمسح رمز بطاقة الموظف.</small>
        </div>

        <?php $currentTheme = $company['theme'] ?? \App\Core\Theme::DEFAULT; ?>
        <div class="field">
            <label>ثيم التصميم</label>
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
            <p class="hint">اختيار الثيم يضبط الألوان تلقائياً - ويمكنك تخصيصها يدوياً بالأسفل.</p>
        </div>

        <details class="theme-advanced">
            <summary>🎨 تخصيص لوني متقدم (اختياري)</summary>
            <div class="grid-2" style="margin-top:10px;">
                <div class="field">
                    <label>اللون الأساسي</label>
                    <input type="color" name="primary_color" id="primary-color-input" value="<?= e($company['primary_color'] ?? '#2563eb') ?>">
                </div>
                <div class="field">
                    <label>خلفية القائمة الجانبية</label>
                    <input type="color" name="sidebar_color" id="sidebar-color-input" value="<?= e($company['sidebar_color'] ?? '#111827') ?>">
                    <p class="hint">يُفضّل لون داكن ليبقى نص القائمة الجانبية واضحاً.</p>
                </div>
            </div>
        </details>
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
            <label>الشعار</label>
            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
            <?php if (!empty($company['logo'])): ?>
                <div style="margin-top:8px;">
                    <img src="<?= e(route('/media/companies/' . $company['logo'])) ?>" alt="الشعار الحالي" style="height:40px;border-radius:6px;">
                </div>
            <?php endif; ?>
        </div>
        <div class="form-actions"><button class="btn" type="submit">حفظ</button></div>
    </form>
<?php endif; ?>
</div>

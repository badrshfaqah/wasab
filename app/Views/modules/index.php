<div class="page-head">
    <div><h1>إدارة الإضافات</h1><p>ثبّت وفعّل الإضافات لتوسيع قدرات النظام دون المساس بالنواة.</p></div>
</div>

<?php
$fileVersion = \App\Core\Wasab::currentVersion();
$dbVersion = (string) (\App\Core\Setting::get('system_version', null, '') ?? '');
$finalizedAt = (string) (\App\Core\Setting::get('system_version_updated_at', null, '') ?? '');
$versionSynced = $dbVersion === $fileVersion;
?>
<div class="card">
    <div class="card-title">
        <span>⬇️ تحديث النظام</span>
        <span style="display:flex;gap:6px;flex-wrap:wrap;">
            <span class="badge badge-info">الإصدار: <?= e($fileVersion) ?></span>
            <?php if ($versionSynced): ?>
                <span class="badge badge-success">✓ قاعدة البيانات مواكبة</span>
            <?php else: ?>
                <span class="badge badge-warning">⏳ بانتظار إكمال الترقيات</span>
            <?php endif; ?>
        </span>
    </div>
    <?php if ($finalizedAt): ?>
        <p class="hint" style="margin:0 0 12px;">آخر اكتمال تحديث: <strong><?= e($dbVersion ?: '—') ?></strong> بتاريخ <?= format_date($finalizedAt, 'Y-m-d H:i') ?> — بعد كل سحب من الاستضافة تُطبَّق الترقيات تلقائياً (بأول زيارة أو خلال دورة الكرون) ويصلك إشعار بالاكتمال.</p>
    <?php else: ?>
        <p class="hint" style="margin:0 0 12px;">بعد كل سحب من الاستضافة تُطبَّق الترقيات تلقائياً (بأول زيارة أو خلال دورة الكرون) ويصلك إشعار بالاكتمال.</p>
    <?php endif; ?>

    <div class="form-section" style="margin-top:0;padding-top:0;border-top:0;">الطريقة الأولى — السحب عبر Git الاستضافة (الموصى بها)</div>
    <p class="hint" style="margin:0 0 10px;">
        اسحب من لوحة الاستضافة — الترقيات تكتمل تلقائياً. هذا الزر لمن يريد التطبيق الفوري مع تقرير مفصّل بما طُبِّق
        (ترقيات النواة وكل الإضافات ومزامنة الصلاحيات).
    </p>
    <form method="post" action="<?= route('/extensions/finish-update') ?>">
        <?= csrf_field() ?>
        <button class="btn btn-sm" type="submit">✅ أكمل التحديث الآن (بعد سحب Git)</button>
    </form>

    <div class="form-section">الطريقة الثانية — السحب المدمج (احتياطية)</div>
    <p class="hint" style="margin:0 0 10px;">
        إن لم يكن Git مفعّلاً بالاستضافة: هذا الزر ينزّل آخر نسخة من GitHub ويطبّقها بنفسه (الملفات + الترقيات معاً).
        لا يمس config.php ولا الملفات المرفوعة.
    </p>
    <form method="post" action="<?= route('/extensions/self-update') ?>" data-confirm="سيتم استبدال ملفات النظام بآخر نسخة من GitHub. متابعة؟">
        <?= csrf_field() ?>
        <button class="btn btn-outline btn-sm" type="submit">⬇️ سحب آخر تحديث وتطبيقه الآن</button>
    </form>
</div>

<div class="card">
    <div class="card-title">
        <span>🗄️ ترقيات النواة (قاعدة البيانات والملفات)</span>
        <?= $coreSchemaUpToDate ? status_badge('active') : status_badge('pending') ?>
    </div>
    <p class="hint" style="margin:0 0 12px;">
        <?php if ($coreSchemaUpToDate): ?>
            النواة محدّثة بالكامل (جداول قاعدة البيانات وتنظيم الملفات). عادة يتم هذا تلقائياً بمجرد رفع ملفات جديدة،
            لكن يمكنك الضغط على الزر يدوياً في أي وقت (مثلاً بعد رفع تحديث، أو لإعادة المحاولة إن واجهت رسالة خطأ سابقاً).
        <?php else: ?>
            يتوفر تحديث لبنية النواة لم يُطبَّق تلقائياً بعد (على الأغلب بسبب صلاحيات قاعدة البيانات أو الملفات على
            الاستضافة). اضغط الزر لإعادة المحاولة الآن.
        <?php endif; ?>
    </p>
    <form method="post" action="<?= route('/extensions/update-core-database') ?>">
        <?= csrf_field() ?>
        <button class="btn <?= $coreSchemaUpToDate ? 'btn-outline' : '' ?> btn-sm" type="submit">تحديث النواة</button>
    </form>
</div>

<div class="card">
    <div class="card-title"><span>⏰ مهام الجدولة الدورية (Cron)</span></div>
    <p class="hint" style="margin:0 0 12px;">
        تذكيرات الاجتماعات القريبة وأحداث التقويم تعتمد على مهمة جدولة تعمل كل ٥ دقائق.
        فعّلها من لوحة تحكم الاستضافة بإحدى الطريقتين (أيهما أسهل لك):
    </p>
    <div class="field" style="margin-bottom:10px;">
        <label>الطريقة الأولى - أمر PHP (خيار "Cron Jobs" بلوحة الاستضافة):</label>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <code dir="ltr" style="padding:6px 10px;background:var(--bg);border:1px solid var(--border);border-radius:8px;overflow-x:auto;max-width:100%;"><?= e($cronCliCommand) ?></code>
            <button type="button" class="btn btn-outline btn-sm" data-copy="<?= e($cronCliCommand) ?>">نسخ</button>
        </div>
    </div>
    <div class="field" style="margin:0;">
        <label>الطريقة الثانية - زيارة رابط (لجدولة URL أو خدمة مراقبة خارجية):</label>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <code dir="ltr" style="padding:6px 10px;background:var(--bg);border:1px solid var(--border);border-radius:8px;overflow-x:auto;max-width:100%;"><?= e($cronWebUrl) ?></code>
            <button type="button" class="btn btn-outline btn-sm" data-copy="<?= e($cronWebUrl) ?>">نسخ</button>
        </div>
        <p class="hint" style="margin-top:6px;">الرابط محمي بتوكن سري - لا تنشره خارج إعدادات الجدولة.</p>
    </div>
</div>

<?php $installedKeys = array_column(array_filter($modules, fn ($m) => $m['installed']), 'key'); ?>
<div class="card">
    <p class="hint" style="margin:0;">رتّب ظهور الإضافات بالقائمة الجانبية بأسهم التحريك ⬆️⬇️ (تظهر فقط على الإضافات المثبَّتة).</p>
</div>

<div class="cards-row" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr));">
<?php if (!$modules): ?>
    <div class="empty-state"><div class="ic">🧩</div>لا توجد إضافات داخل مجلد modules/ حالياً.</div>
<?php endif; ?>
<?php foreach ($modules as $m): ?>
    <?php $order = $m['installed'] ? array_search($m['key'], $installedKeys, true) : null; ?>
    <div class="card" style="margin-bottom:0;">
        <div class="card-title">
            <span>🧩 <?= e($m['name']) ?></span>
            <?php if ($m['status'] === 'active'): ?>
                <span class="badge badge-success">مفعّلة</span>
            <?php elseif ($m['installed']): ?>
                <span class="badge badge-muted">معطّلة</span>
            <?php else: ?>
                <span class="badge badge-warning">غير مثبتة</span>
            <?php endif; ?>
        </div>
        <p style="color:var(--muted);font-size:13px;min-height:36px;"><?= e($m['description']) ?></p>
        <p class="hint">الإصدار: <?= e($m['version']) ?><?= $m['needs_update'] ? ' (يتوفر تحديث)' : '' ?></p>

        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;">
            <?php if ($m['installed']): ?>
                <form method="post" action="<?= route('/extensions/' . $m['key'] . '/move-up') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline btn-sm" type="submit" <?= $order === 0 ? 'disabled' : '' ?> title="تحريك للأعلى">⬆️</button>
                </form>
                <form method="post" action="<?= route('/extensions/' . $m['key'] . '/move-down') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline btn-sm" type="submit" <?= $order === count($installedKeys) - 1 ? 'disabled' : '' ?> title="تحريك للأسفل">⬇️</button>
                </form>
            <?php endif; ?>
            <?php if (!$m['installed']): ?>
                <form method="post" action="<?= route('/extensions/' . $m['key'] . '/install') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm" type="submit">تثبيت</button>
                </form>
            <?php else: ?>
                <?php if ($m['status'] === 'active'): ?>
                    <form method="post" action="<?= route('/extensions/' . $m['key'] . '/deactivate') ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline btn-sm" type="submit">تعطيل</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= route('/extensions/' . $m['key'] . '/activate') ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm" type="submit">تفعيل</button>
                    </form>
                    <form method="post" action="<?= route('/extensions/' . $m['key'] . '/remove') ?>" data-confirm="سيتم حذف بيانات الإضافة بشكل نهائي. متابعة؟">
                        <?= csrf_field() ?>
                        <button class="btn btn-danger btn-sm" type="submit">إزالة</button>
                    </form>
                <?php endif; ?>

                <?php if ($m['needs_update']): ?>
                    <form method="post" action="<?= route('/extensions/' . $m['key'] . '/update') ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline btn-sm" type="submit">تحديث</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>

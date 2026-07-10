<div class="page-head">
    <div><h1>إدارة الإضافات</h1><p>ثبّت وفعّل الإضافات لتوسيع قدرات النظام دون المساس بالنواة.</p></div>
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

<div class="cards-row" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr));">
<?php if (!$modules): ?>
    <div class="empty-state"><div class="ic">🧩</div>لا توجد إضافات داخل مجلد modules/ حالياً.</div>
<?php endif; ?>
<?php foreach ($modules as $m): ?>
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

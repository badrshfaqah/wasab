<div class="page-head">
    <div><h1>قوائم التحقق</h1><p>قوالب جاهزة من العناصر تُطبَّق على أي مهمة كمهام فرعية بنقرة واحدة.</p></div>
    <a class="btn btn-outline" href="<?= route('/tasks') ?>">↩ المهام</a>
</div>

<div class="card">
    <div class="card-title"><span>إضافة قائمة تحقق</span></div>
    <form method="post" action="<?= route('/tasks/checklists') ?>">
        <?= csrf_field() ?>
        <div class="field"><label>الاسم</label><input type="text" name="name" required placeholder="مثال: خطوات إغلاق مشروع"></div>
        <div class="field">
            <label>العناصر (عنصر لكل سطر)</label>
            <textarea name="items" required style="min-height:120px;" placeholder="مراجعة المتطلبات&#10;اعتماد المدير&#10;أرشفة الملفات&#10;إبلاغ العميل"></textarea>
        </div>
        <button class="btn btn-sm" type="submit">إضافة</button>
    </form>
</div>

<div class="card">
    <div class="card-title"><span>القوالب الحالية</span></div>
    <?php if (!$items): ?>
        <p class="hint">لا توجد قوائم تحقق بعد.</p>
    <?php endif; ?>
    <?php foreach ($items as $it): ?>
        <?php $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $it['items'])))); ?>
        <div class="doc-log" style="align-items:flex-start;">
            <div>
                <strong><?= e($it['name']) ?></strong> <span class="hint">(<?= count($lines) ?> عنصر)</span>
                <div class="hint" style="margin-top:4px;"><?= e(implode(' · ', array_slice($lines, 0, 6))) ?><?= count($lines) > 6 ? ' …' : '' ?></div>
            </div>
            <form method="post" action="<?= route('/tasks/checklists/' . $it['id'] . '/delete') ?>" data-confirm="حذف قائمة التحقق؟">
                <?= csrf_field() ?><button class="btn btn-outline btn-sm" type="submit">حذف</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>

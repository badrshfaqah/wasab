<div class="page-head">
    <div><h1>قوالب المستندات</h1><p>خلفيات ورؤوس/تذييل جاهزة تُختار عند إنشاء مستند.</p></div>
    <div>
        <a class="btn btn-outline" href="<?= route('/documents') ?>">↩ رجوع للمستندات</a>
        <a class="btn" href="<?= route('/documents/templates/create') ?>">+ قالب جديد</a>
    </div>
</div>

<div class="cards-row" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr));">
<?php if (!$templates): ?>
    <div class="empty-state"><div class="ic">🎨</div>لا توجد قوالب بعد.</div>
<?php endif; ?>
<?php foreach ($templates as $t): ?>
    <div class="card doc-template-card" style="margin-bottom:0;">
        <?php if ($t['background_image']): ?>
            <div class="doc-template-preview" style="background-image:url('<?= e(route('/media/documents/' . $t['company_id'] . '/' . $t['background_image'])) ?>');"></div>
        <?php else: ?>
            <div class="doc-template-preview">بدون صورة خلفية</div>
        <?php endif; ?>
        <div class="card-title"><span><?= e($t['name']) ?></span></div>
        <p class="hint">موضع الرقم: <?= e($t['number_position']) ?></p>
        <div style="display:flex;gap:8px;">
            <a class="btn btn-outline btn-sm" href="<?= route('/documents/templates/' . $t['id'] . '/edit') ?>">تعديل</a>
            <form method="post" action="<?= route('/documents/templates/' . $t['id'] . '/delete') ?>" data-confirm="سيتم حذف القالب. المستندات المرتبطة به ستبقى لكن بلا قالب. متابعة؟">
                <?= csrf_field() ?>
                <button class="btn btn-danger btn-sm" type="submit">حذف</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</div>

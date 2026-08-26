<?php $wid = (int) $workspace['id']; ?>
<div class="page-head">
    <div><h1>القوائم — <?= e($workspace['name']) ?></h1><p>تجميعات مرنة للجهات (جهات مستهدفة، شركاء محتملون، تحتاج متابعة...) والجهة تظهر في عدة قوائم دون تكرار بياناتها.</p></div>
    <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid) ?>">↩ المساحة</a>
</div>

<div class="cards-row" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr));">
    <?php if (!$lists): ?>
        <div class="card"><div class="empty-state"><div class="ic">📋</div>لا قوائم بعد.</div></div>
    <?php endif; ?>
    <?php foreach ($lists as $l): ?>
        <div class="card" style="margin-bottom:0;">
            <div class="card-title"><span><a href="<?= route('/crm/w/' . $wid . '/lists/' . $l['id']) ?>">📋 <?= e($l['name']) ?></a></span></div>
            <?php if (!empty($l['description'])): ?><p class="hint" style="margin-top:0;"><?= e($l['description']) ?></p><?php endif; ?>
            <p class="hint"><span class="badge badge-muted"><?= (int) $l['items_count'] ?> جهة</span></p>
            <?php if ($canManage): ?>
                <form method="post" action="<?= route('/crm/w/' . $wid . '/lists/' . $l['id'] . '/delete') ?>" data-confirm="حذف القائمة «<?= e($l['name']) ?>»؟ الجهات نفسها لن تتأثر.">
                    <?= csrf_field() ?><button class="btn btn-outline btn-sm" type="submit">حذف</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($canManage): ?>
<div class="card" style="max-width:620px;">
    <div class="card-title"><span>➕ قائمة جديدة</span></div>
    <form method="post" action="<?= route('/crm/w/' . $wid . '/lists') ?>">
        <?= csrf_field() ?>
        <div class="field"><label>اسم القائمة</label><input type="text" name="name" required placeholder="مثال: منظمو فعاليات 2026"></div>
        <div class="field"><label>وصف (اختياري)</label><input type="text" name="description" maxlength="500"></div>
        <button class="btn" type="submit">إنشاء</button>
    </form>
</div>
<?php endif; ?>

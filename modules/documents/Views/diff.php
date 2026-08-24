<?php /** عرض "ماذا تغيّر": ما فعله حفظُ صاحب هذه اللقطة - الأخضر مُضاف والأحمر محذوف. */ ?>
<div class="page-head">
    <div>
        <h1>± تغييرات <?= e($version['saved_by_name'] ?? 'النظام') ?></h1>
        <p>
            <?= e($document['title']) ?>
            · الحفظ رقم #<?= (int) $version['version_no'] ?>
            · <?= format_date($version['created_at'], 'Y-m-d H:i') ?>
            <?php if (!$next): ?>
                · (آخر تعديل — مقارنة بالمحتوى الحالي)
            <?php endif; ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;">
        <a class="btn btn-outline" href="<?= route('/documents/' . $document['id'] . '/versions/' . $version['id']) ?>">عرض الإصدار كاملاً</a>
        <a class="btn btn-outline" href="<?= route('/documents/' . $document['id']) ?>">← عودة للمستند</a>
    </div>
</div>

<div class="card">
    <div class="card-title divided">
        <span>الفرق</span>
        <span style="display:flex;gap:10px;font-size:.85em;font-weight:normal;">
            <span><span style="background:color-mix(in srgb, var(--success) 22%, transparent);border-radius:4px;padding:1px 6px;">مُضاف</span></span>
            <span><span style="background:color-mix(in srgb, var(--danger) 20%, transparent);border-radius:4px;padding:1px 6px;text-decoration:line-through;">محذوف</span></span>
        </span>
    </div>
    <?php if (!$diff): ?>
        <p class="hint">لا يوجد نص للمقارنة.</p>
    <?php else: ?>
        <?php
        $unchanged = true;
        foreach ($diff as [$kind]) { if ($kind !== 'same') { $unchanged = false; break; } }
        ?>
        <?php if ($unchanged): ?>
            <p class="hint">لا تغيير في النص بين الإصدارين (ربما تغيّر العنوان أو التنسيق فقط).</p>
        <?php endif; ?>
        <div style="line-height:2;white-space:pre-wrap;word-break:break-word;">
            <?php foreach ($diff as [$kind, $word]): ?><?php
                if ($kind === 'same') {
                    echo e($word) . ' ';
                } elseif ($kind === 'ins') {
                    echo '<span style="background:color-mix(in srgb, var(--success) 22%, transparent);border-radius:4px;padding:1px 3px;">' . e($word) . '</span> ';
                } else {
                    echo '<span style="background:color-mix(in srgb, var(--danger) 20%, transparent);border-radius:4px;padding:1px 3px;text-decoration:line-through;opacity:.75;">' . e($word) . '</span> ';
                }
            ?><?php endforeach; ?>
        </div>
    <?php endif; ?>
    <p class="hint" style="margin-top:14px;">المقارنة على مستوى الكلمات بعد إزالة التنسيق — تكفي لمعرفة من غيّر ماذا في كل حفظ.</p>
</div>

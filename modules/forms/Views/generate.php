<?php
/**
 * توليد خطاب: اختيار الموظف يعيد تحميل الصفحة لملء الحقول المعروفة تلقائياً وعرض
 * الحقول اليدوية المتبقية. تغيير الموظف عبر submit للـ GET (بسيط وبلا جافاسكربت ثقيل).
 */
$fieldLabels = [
    'الجهة' => 'الجهة / المستفيد', 'تاريخ_المباشرة' => 'تاريخ المباشرة', 'تاريخ_الإخلاء' => 'تاريخ إخلاء الطرف',
];
?>
<div class="page-head">
    <div><h1>توليد: <?= e($template['name']) ?></h1><p>املأ الحقول ثم احفظ - الحقول المعروفة تُعبّأ تلقائياً من الملف الوظيفي.</p></div>
    <a class="btn btn-outline" href="<?= route('/forms') ?>">← رجوع</a>
</div>

<div class="grid-2">
    <div class="card">
        <!-- اختيار الموظف (يعيد تحميل الصفحة للتعبئة التلقائية) -->
        <?php if ($employeesActive): ?>
        <form method="get" action="<?= route('/forms/generate') ?>" style="margin-bottom:16px;">
            <input type="hidden" name="template" value="<?= (int) $template['id'] ?>">
            <div class="field" style="margin:0;">
                <label>الموظف (اختياري - للتعبئة التلقائية)</label>
                <select name="employee" onchange="this.form.submit()">
                    <option value="">— بلا موظف (إدخال يدوي كامل) —</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= $employeeId === (int) $emp['id'] ? 'selected' : '' ?>>
                            <?= e($emp['full_name']) ?><?= $emp['job_title'] ? ' — ' . e($emp['job_title']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        <?php endif; ?>

        <form method="post" action="<?= route('/forms') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="template_id" value="<?= (int) $template['id'] ?>">
            <input type="hidden" name="employee_id" value="<?= (int) ($employeeId ?? 0) ?>">

            <?php
            // الحقول المعروفة المُعبّأة فعلاً (لعرضها كملخّص غير قابل للتعديل)
            $filledKnown = array_filter($known, fn ($v) => $v !== '');
            ?>
            <?php if ($filledKnown): ?>
                <div class="card-title"><span>✅ حقول مُعبّأة تلقائياً</span></div>
                <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:10px 12px;margin-bottom:16px;font-size:13px;">
                    <?php foreach ($filledKnown as $k => $v): ?>
                        <div style="padding:2px 0;"><span class="hint"><?= e(str_replace('_', ' ', $k)) ?>:</span> <strong><?= e($v) ?></strong></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($manualFields): ?>
                <div class="card-title"><span>✍️ حقول تحتاج إدخالاً يدوياً</span></div>
                <?php foreach ($manualFields as $mf): ?>
                    <div class="field">
                        <label><?= e($fieldLabels[$mf] ?? str_replace('_', ' ', $mf)) ?></label>
                        <input type="text" name="fields[<?= e($mf) ?>]" maxlength="255"<?= str_contains($mf, 'تاريخ') ? '' : '' ?>>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="hint">كل الحقول مُعبّأة - جاهز للتوليد.</p>
            <?php endif; ?>

            <div class="field">
                <label>توقيعك على الخطاب</label>
                <?php if (!empty($mySignatures)): ?>
                    <select name="signature_id">
                        <?php foreach ($mySignatures as $sig): ?>
                            <option value="<?= $sig['id'] ?>"><?= e($sig['name']) ?></option>
                        <?php endforeach; ?>
                        <option value="">بلا توقيع</option>
                    </select>
                <?php else: ?>
                    <p class="hint">لا توقيع محفوظ لك — أضِف توقيعك من <a href="<?= route('/profile') ?>">ملفك الشخصي</a> ليظهر على الخطاب.</p>
                <?php endif; ?>
            </div>

            <button class="btn" type="submit">توليد الخطاب وحفظه</button>
        </form>
    </div>

    <div class="card">
        <div class="card-title"><span>👁️ معاينة القالب</span></div>
        <div style="white-space:pre-wrap;line-height:2;font-size:13.5px;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:14px;max-height:520px;overflow-y:auto;"><?= e($template['body']) ?></div>
        <p class="hint" style="margin-top:8px;">الحقول بين الأقواس {} تُستبدل بقيمها عند التوليد.</p>
    </div>
</div>

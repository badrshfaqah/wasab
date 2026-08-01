<?php
$fmt = fn ($n) => number_format((float) $n, 2);
$hasData = $result['start_date'] !== null && $wage > 0;
?>
<div class="page-head">
    <div>
        <h1>مكافأة نهاية الخدمة</h1>
        <p><?= e($employee['full_name']) ?><?= $employee['job_title'] ? ' · ' . e($employee['job_title']) : '' ?></p>
    </div>
    <a class="btn btn-outline" href="<?= route('/employees/' . $employee['id']) ?>">↩ عودة للملف</a>
</div>

<div class="card" style="max-width:720px;">
    <form method="get" action="<?= route('/employees/' . $employee['id'] . '/eosb') ?>" class="filters-toolbar" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
        <div class="field" style="margin:0;min-width:180px;">
            <label>سبب انتهاء الخدمة</label>
            <select name="reason" onchange="this.form.submit()">
                <?php foreach ($reasonLabels as $rk => $rl): ?>
                    <option value="<?= $rk ?>" <?= $reason === $rk ? 'selected' : '' ?>><?= e($rl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" style="margin:0;min-width:170px;">
            <label>تاريخ نهاية الخدمة</label>
            <input type="date" name="end_date" value="<?= e($endDate) ?>" onchange="this.form.submit()">
        </div>
        <button class="btn btn-sm" type="submit">احتساب</button>
    </form>
</div>

<?php if (!$hasData): ?>
    <div class="card" style="max-width:720px;border-inline-start:4px solid var(--warning);">
        <p style="margin:0;">
            لا يمكن الاحتساب: تأكد من إدخال
            <?= !$result['start_date'] ? '<strong>تاريخ بدء العقد أو الالتحاق</strong>' : '' ?>
            <?= (!$result['start_date'] && $wage <= 0) ? ' و' : '' ?>
            <?= $wage <= 0 ? '<strong>الراتب الأساسي والبدلات</strong>' : '' ?>
            في الملف الوظيفي (قسم العقد والراتب).
        </p>
    </div>
<?php else: ?>
    <div class="card" style="max-width:720px;">
        <div class="card-title"><span>النتيجة</span></div>
        <div style="text-align:center;padding:14px 0;border-bottom:1px solid var(--border);margin-bottom:14px;">
            <div class="hint">إجمالي المكافأة المستحقة</div>
            <div style="font-size:34px;font-weight:800;color:var(--primary);"><?= $fmt($result['final_award']) ?> <span style="font-size:18px;">ريال</span></div>
            <div class="hint" style="margin-top:4px;"><?= e($result['factor_label']) ?></div>
        </div>
        <table>
            <tr><td class="hint">الأجر الشهري المعتمد (أساسي + بدلات)</td><td><?= $fmt($result['wage']) ?> ريال</td></tr>
            <tr><td class="hint">بداية الخدمة</td><td><?= e($result['start_date']) ?></td></tr>
            <tr><td class="hint">نهاية الخدمة</td><td><?= e($result['end_date']) ?></td></tr>
            <tr><td class="hint">مدة الخدمة</td><td><?= $result['years'] ?> سنة (<?= (int) $result['days'] ?> يوم)</td></tr>
            <tr><td class="hint">مكافأة السنوات الخمس الأولى (نصف شهر/سنة)</td><td><?= $fmt(0.5 * $result['wage'] * $result['first_five_years']) ?> ريال</td></tr>
            <tr><td class="hint">مكافأة ما بعد 5 سنوات (شهر/سنة)</td><td><?= $fmt($result['wage'] * $result['beyond_five_years']) ?> ريال</td></tr>
            <tr><td class="hint">المكافأة الأساسية (قبل معامل السبب)</td><td><?= $fmt($result['base_award']) ?> ريال</td></tr>
            <tr><td class="hint">معامل الاستحقاق</td><td><?= rtrim(rtrim(number_format($result['factor'], 3), '0'), '.') ?> (<?= (int) round($result['factor'] * 100) ?>%)</td></tr>
            <tr style="font-weight:700;"><td>الصافي المستحق</td><td><?= $fmt($result['final_award']) ?> ريال</td></tr>
        </table>
        <p class="hint" style="margin-top:14px;">
            الحساب استرشادي وفق المادتين 84 و85 من نظام العمل السعودي، ولا يشمل أي استقطاعات أو مستحقات أخرى (إجازات، سلف...). يُرجى مراجعة الجهة المختصة قبل الاعتماد.
        </p>
    </div>
<?php endif; ?>

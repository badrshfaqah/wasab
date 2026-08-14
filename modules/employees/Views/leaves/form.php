<?php /** @var ?array $own @var bool $canManage @var array $employees @var array $typeLabels */ ?>
<div class="page-head">
    <div><h1>طلب إجازة / إذن</h1><p>يصل الطلب لمديرك للاعتماد، ويصلك إشعار بالقرار.</p></div>
    <a class="btn btn-outline" href="<?= route('/employees/leaves') ?>">← الإجازات</a>
</div>

<div class="card" style="max-width:620px;">
    <form method="post" action="<?= route('/employees/leaves') ?>">
        <?= csrf_field() ?>

        <?php if ($canManage && $employees): ?>
            <div class="field">
                <label>الموظف <span class="req">*</span></label>
                <select name="employee_id" required>
                    <option value="">— اختر —</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= $own && (int) $own['id'] === (int) $emp['id'] ? 'selected' : '' ?>><?= e($emp['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php elseif ($own): ?>
            <p class="hint" style="margin-bottom:12px;">الطلب باسم: <strong><?= e($own['full_name']) ?></strong> — رصيدك السنوي: <strong><?= (int) $own['annual_leave_balance'] ?> يوم</strong></p>
        <?php endif; ?>

        <div class="field">
            <label>نوع الطلب <span class="req">*</span></label>
            <select name="type" id="lv-type">
                <?php foreach ($typeLabels as $k => $lbl): ?>
                    <option value="<?= $k ?>"><?= e($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="grid-2">
            <div class="field"><label>من تاريخ <span class="req">*</span></label><input type="date" name="start_date" required value="<?= date('Y-m-d') ?>"></div>
            <div class="field" id="lv-end"><label>إلى تاريخ</label><input type="date" name="end_date" value="<?= date('Y-m-d') ?>"><p class="hint">اتركه كما هو ليوم واحد.</p></div>
        </div>

        <div class="field" id="lv-hours" style="display:none;">
            <label>عدد الساعات <span class="req">*</span></label>
            <input type="number" name="hours" min="0.5" max="12" step="0.5" value="2">
        </div>

        <div class="field">
            <label>السبب (اختياري)</label>
            <textarea name="reason" rows="2" maxlength="500" placeholder="سبب مختصر يساعد المدير على القرار"></textarea>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit">📨 تقديم الطلب</button>
            <a class="btn btn-outline" href="<?= route('/employees/leaves') ?>">إلغاء</a>
        </div>
    </form>
</div>
<script>
(function () {
    var t = document.getElementById('lv-type'), h = document.getElementById('lv-hours'), en = document.getElementById('lv-end');
    var sync = function () {
        var isHours = t.value === 'hours';
        h.style.display = isHours ? '' : 'none';
        en.style.display = isHours ? 'none' : '';
    };
    t.addEventListener('change', sync); sync();
})();
</script>

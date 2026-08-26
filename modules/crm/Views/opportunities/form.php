<?php
$wid = (int) $workspace['id'];
$isEdit = $opportunity !== null;
$stagesJson = json_encode($stagesByPipeline, JSON_UNESCAPED_UNICODE);
?>
<div class="page-head">
    <div><h1><?= $isEdit ? 'تعديل الفرصة' : 'فرصة جديدة' ?></h1>
        <?php if (!$isEdit): ?><p>الجهة الواحدة قد يكون معها عدة فرص — كل فرصة تُدار بمراحلها الخاصة.</p><?php endif; ?>
    </div>
    <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid . '/opportunities') ?>">↩ اللوحة</a>
</div>

<div class="card" style="max-width:820px;">
    <form method="post" action="<?= $isEdit ? route('/crm/w/' . $wid . '/opportunities/' . $opportunity['id']) : route('/crm/w/' . $wid . '/opportunities') ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label>اسم الفرصة</label>
            <input type="text" name="name" value="<?= e($opportunity['name'] ?? '') ?>" required placeholder="مثال: الشراكة الإعلامية — معرض 2026">
        </div>
        <div class="grid-2">
            <div class="field">
                <label>الجهة</label>
                <select name="organization_id" id="opp-org" required>
                    <option value="">اختر جهة...</option>
                    <?php foreach ($organizations as $o): ?>
                        <option value="<?= $o['organization_id'] ?>" <?= (int) ($opportunity['organization_id'] ?? $selectedOrg ?? 0) === (int) $o['organization_id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>الشخص المسؤول لدى الجهة</label>
                <select name="contact_id">
                    <option value="">—</option>
                    <?php foreach ($contacts as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (int) ($opportunity['contact_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="hint">تُحدَّث قائمة الأشخاص بعد حفظ الفرصة واختيار الجهة.</p>
            </div>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>المسار</label>
                <select name="pipeline_id" id="opp-pipeline">
                    <?php foreach ($pipelines as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= (int) ($opportunity['pipeline_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>المرحلة</label>
                <select name="stage_id" id="opp-stage"></select>
            </div>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>المسؤول عن الفرصة</label>
                <select name="owner_id">
                    <?php foreach ($members as $m): ?>
                        <option value="<?= $m['user_id'] ?>" <?= (int) ($opportunity['owner_id'] ?? current_user()['id']) === (int) $m['user_id'] ? 'selected' : '' ?>><?= e($m['user_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>تاريخ الإغلاق المتوقع</label>
                <input type="date" name="expected_close_date" value="<?= e($opportunity['expected_close_date'] ?? '') ?>">
            </div>
        </div>
        <div class="grid-2">
            <div class="field"><label>القيمة المتوقعة (اختياري)</label><input type="number" step="0.01" name="value" value="<?= e($opportunity['value'] ?? '') ?>"></div>
            <div class="field"><label>احتمالية النجاح %</label><input type="number" min="0" max="100" name="probability" value="<?= e($opportunity['probability'] ?? '') ?>"></div>
        </div>
        <div class="field"><label>المصدر</label><input type="text" name="source" value="<?= e($opportunity['source'] ?? '') ?>" placeholder="مثال: توصية، معرض، تواصل مباشر"></div>
        <div class="field"><label>الوصف</label><textarea name="description" rows="3"><?= e($opportunity['description'] ?? '') ?></textarea></div>

        <div class="form-actions">
            <button class="btn" type="submit"><?= $isEdit ? 'حفظ' : 'إنشاء الفرصة' ?></button>
            <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid . '/opportunities') ?>">إلغاء</a>
        </div>
    </form>
</div>

<script>
/* مراحل كل مسار تتبع اختيار المسار مباشرة */
(function () {
    var stages = <?= $stagesJson ?>;
    var pipeline = document.getElementById('opp-pipeline');
    var stageSel = document.getElementById('opp-stage');
    var current = <?= (int) ($opportunity['stage_id'] ?? 0) ?>;
    function fill() {
        var list = stages[pipeline.value] || [];
        stageSel.innerHTML = '';
        list.forEach(function (s) {
            var opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.name;
            if (parseInt(s.id, 10) === current) { opt.selected = true; }
            stageSel.appendChild(opt);
        });
    }
    if (pipeline && stageSel) { pipeline.addEventListener('change', fill); fill(); }
})();
</script>

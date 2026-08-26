<form method="post" action="<?= route('/crm/w/' . $wid . '/opportunities/' . $oid) ?>">
    <?= csrf_field() ?>
    <div class="field"><label>اسم الفرصة</label><input type="text" name="name" value="<?= e($opportunity['name']) ?>" required></div>
    <div class="grid-2">
        <div class="field">
            <label>الجهة</label>
            <select name="organization_id" required>
                <?php foreach ($organizations as $o): ?>
                    <option value="<?= $o['organization_id'] ?>" <?= $selectedOrg === (int) $o['organization_id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>الشخص لدى الجهة</label>
            <select name="contact_id">
                <option value="">—</option>
                <?php foreach ($contacts as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (int) ($opportunity['contact_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <input type="hidden" name="pipeline_id" value="<?= (int) $opportunity['pipeline_id'] ?>">
    <input type="hidden" name="stage_id" value="<?= (int) $opportunity['stage_id'] ?>">
    <div class="grid-2">
        <div class="field">
            <label>المسؤول</label>
            <select name="owner_id">
                <?php foreach ($members as $m): ?>
                    <option value="<?= $m['user_id'] ?>" <?= (int) ($opportunity['owner_id'] ?? 0) === (int) $m['user_id'] ? 'selected' : '' ?>><?= e($m['user_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field"><label>الإغلاق المتوقع</label><input type="date" name="expected_close_date" value="<?= e($opportunity['expected_close_date'] ?? '') ?>"></div>
    </div>
    <div class="grid-2">
        <div class="field"><label>القيمة</label><input type="number" step="0.01" name="value" value="<?= e($opportunity['value'] ?? '') ?>"></div>
        <div class="field"><label>الاحتمالية %</label><input type="number" min="0" max="100" name="probability" value="<?= e($opportunity['probability'] ?? '') ?>"></div>
    </div>
    <div class="field"><label>المصدر</label><input type="text" name="source" value="<?= e($opportunity['source'] ?? '') ?>"></div>
    <div class="field"><label>الوصف</label><textarea name="description" rows="3"><?= e($opportunity['description'] ?? '') ?></textarea></div>
    <div class="form-actions">
        <button class="btn" type="submit">حفظ</button>
        <button class="btn btn-danger" type="submit" formaction="<?= route('/crm/w/' . $wid . '/opportunities/' . $oid . '/delete') ?>"
                onclick="return confirm('حذف هذه الفرصة نهائياً؟');">حذف</button>
    </div>
</form>

<?php $isEdit = $contact !== null; ?>
<div class="page-head">
    <div><h1><?= $isEdit ? 'تعديل جهة اتصال' : 'جهة اتصال جديدة' ?></h1></div>
</div>

<div class="card" style="max-width:640px;">
    <form method="post" action="<?= $isEdit ? route('/phone/contacts/' . $contact['id']) : route('/phone/contacts') ?>">
        <?= csrf_field() ?>

        <div class="field">
            <label>النوع</label>
            <select name="type" id="contact-type">
                <option value="external" <?= ($contact['type'] ?? 'external') === 'external' ? 'selected' : '' ?>>خارجي (رقم هاتف عادي)</option>
                <option value="internal" <?= ($contact['type'] ?? '') === 'internal' ? 'selected' : '' ?>>داخلي (تحويلة موظف بالشركة)</option>
            </select>
        </div>

        <div class="field" id="internal-box">
            <label>الموظف</label>
            <select name="linked_user_id">
                <option value="">اختر موظفاً</option>
                <?php foreach ($companyUsers as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= (int) ($contact['linked_user_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>>
                        <?= e($u['name']) ?> (تحويلة <?= e($u['extension']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!$companyUsers): ?>
                <p class="hint">لا يوجد موظفون بتحويلات مفعّلة بعد.</p>
            <?php endif; ?>
        </div>

        <div id="external-box">
            <div class="field">
                <label>الاسم</label>
                <input type="text" name="name" value="<?= e($contact && $contact['type'] === 'external' ? $contact['name'] : '') ?>">
            </div>
            <div class="field">
                <label>رقم الهاتف</label>
                <input type="text" name="phone_number" dir="ltr" value="<?= e($contact['phone_number'] ?? '') ?>">
            </div>
        </div>

        <div class="field">
            <label>ملاحظات (اختياري)</label>
            <input type="text" name="notes" value="<?= e($contact['notes'] ?? '') ?>">
        </div>

        <div class="field">
            <label>الظهور</label>
            <select name="visibility">
                <option value="private" <?= ($contact['visibility'] ?? 'private') === 'private' ? 'selected' : '' ?>>خاص بك فقط</option>
                <?php if ($canManagePublic): ?>
                    <option value="public" <?= ($contact['visibility'] ?? '') === 'public' ? 'selected' : '' ?>>عام لجميع موظفي الشركة</option>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit"><?= $isEdit ? 'حفظ التعديلات' : 'إضافة' ?></button>
            <a class="btn btn-outline" href="<?= route('/phone/contacts') ?>">إلغاء</a>
        </div>
    </form>
</div>
<script>
(function () {
    var typeSel = document.getElementById('contact-type');
    var internalBox = document.getElementById('internal-box');
    var externalBox = document.getElementById('external-box');
    if (!typeSel) return;
    var sync = function () {
        var isInternal = typeSel.value === 'internal';
        internalBox.style.display = isInternal ? '' : 'none';
        externalBox.style.display = isInternal ? 'none' : '';
    };
    typeSel.addEventListener('change', sync);
    sync();
})();
</script>

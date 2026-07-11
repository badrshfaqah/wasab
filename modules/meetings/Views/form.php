<?php
use Modules\Meetings\Models\Meeting;

$isEdit = $meeting !== null;
$typeLabels = Meeting::typeLabels();

$selectedUserIds = array_column(array_filter($attendees, fn ($a) => $a['user_id']), 'user_id');
$externalText = implode("\n", array_map(
    fn ($a) => $a['external_name'] . ($a['external_contact'] ? ', ' . $a['external_contact'] : ''),
    array_filter($attendees, fn ($a) => !$a['user_id'])
));

$toLocalDatetime = function (?string $value) {
    if (!$value) {
        return '';
    }
    return str_replace(' ', 'T', substr($value, 0, 16));
};
?>
<div class="page-head">
    <div><h1><?= $isEdit ? 'تعديل اجتماع' : 'اجتماع جديد' ?></h1></div>
</div>

<div class="card" style="max-width:760px;">
    <form method="post" action="<?= $isEdit ? route('/meetings/' . $meeting['id']) : route('/meetings') ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label>عنوان الاجتماع</label>
            <input type="text" name="title" value="<?= e($meeting['title'] ?? '') ?>" required>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>النوع</label>
                <select name="type">
                    <?php foreach ($typeLabels as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($meeting['type'] ?? 'internal') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>المكان (قاعة، أو رابط اجتماع افتراضي)</label>
                <input type="text" name="location" value="<?= e($meeting['location'] ?? '') ?>">
            </div>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>بداية الاجتماع</label>
                <input type="datetime-local" name="starts_at" value="<?= e($toLocalDatetime($meeting['starts_at'] ?? null)) ?>" required>
            </div>
            <div class="field">
                <label>نهاية الاجتماع (اختياري)</label>
                <input type="datetime-local" name="ends_at" value="<?= e($toLocalDatetime($meeting['ends_at'] ?? null)) ?>">
            </div>
        </div>
        <div class="field">
            <label>تفاصيل الاجتماع</label>
            <textarea name="description"><?= e($meeting['description'] ?? '') ?></textarea>
        </div>

        <div class="field">
            <label>الحاضرون من الشركة</label>
            <select name="attendee_user_ids[]" multiple size="6">
                <?php foreach ($companyUsers as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= in_array((int) $u['id'], array_map('intval', $selectedUserIds), true) ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="hint">اضغط Ctrl (أو Cmd) لاختيار أكثر من موظف.</p>
        </div>
        <div class="field">
            <label>حاضرون خارجيون (اختياري)</label>
            <textarea name="external_attendees" placeholder="سطر لكل حاضر: الاسم, بيانات التواصل (اختياري)&#10;مثال: أحمد العميل, 0500000000"><?= e($externalText) ?></textarea>
            <p class="hint">سطر واحد لكل شخص. اسم الحاضر، وبعد فاصلة بيانات التواصل إن رغبت.</p>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit"><?= $isEdit ? 'حفظ التعديلات' : 'إنشاء الاجتماع' ?></button>
            <a class="btn btn-outline" href="<?= $isEdit ? route('/meetings/' . $meeting['id']) : route('/meetings') ?>">إلغاء</a>
        </div>
    </form>
</div>

<?php
use Modules\Meetings\Models\Meeting;

$typeLabels = Meeting::typeLabels();
$recurrenceLabels = Meeting::recurrenceLabels();
$conflicts = $conflicts ?? [];

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

<?php if ($conflicts): ?>
<div class="alert alert-danger">
    <strong>⚠️ تنبيه: يوجد تعارض بالمواعيد</strong>
    <ul style="margin:8px 0 0 0;padding-inline-start:20px;">
        <?php foreach ($conflicts as $c): ?>
            <li><?= e($c['person_name']) ?> لديه اجتماع "<?= e($c['title']) ?>" الساعة <?= format_date($c['starts_at'], 'Y-m-d H:i') ?></li>
        <?php endforeach; ?>
    </ul>
    <p class="hint" style="margin-top:6px;">يمكنك تعديل الموعد أدناه وإعادة التحقق، أو تجاهل التنبيه والحفظ كما هو.</p>
</div>
<?php endif; ?>

<div class="card" style="max-width:760px;">
    <form method="post" action="<?= $formAction ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label>عنوان الاجتماع</label>
            <input type="text" name="title" value="<?= e($meeting['title'] ?? '') ?>" required>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>النوع</label>
                <select name="type" id="meeting-type">
                    <?php foreach ($typeLabels as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($meeting['type'] ?? 'in_person') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label id="meeting-location-label">المكان</label>
                <input type="text" name="location" id="meeting-location-input" value="<?= e($meeting['location'] ?? '') ?>">
            </div>
        </div>
        <script>
        (function () {
            var typeSelect = document.getElementById('meeting-type');
            var label = document.getElementById('meeting-location-label');
            var input = document.getElementById('meeting-location-input');
            function sync() {
                if (typeSelect.value === 'online') {
                    label.textContent = 'رابط الاجتماع (Zoom أو Google Meet)';
                    input.placeholder = 'https://zoom.us/j/... أو https://meet.google.com/...';
                } else {
                    label.textContent = 'المكان';
                    input.placeholder = '';
                }
            }
            typeSelect.addEventListener('change', sync);
            sync();
        })();
        </script>
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
            <label>جدول الأعمال (اختياري)</label>
            <textarea name="agenda" placeholder="بند لكل سطر:&#10;1. اعتماد محضر الاجتماع السابق&#10;2. مناقشة الميزانية&#10;3. ما يستجد من أعمال"><?= e($meeting['agenda'] ?? '') ?></textarea>
            <p class="hint">يظهر للمدعوين قبل الاجتماع، ويُضمَّن في ملف التقويم.</p>
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

        <?php if (!$isEdit): ?>
        <div class="grid-2">
            <div class="field">
                <label>تكرار الاجتماع</label>
                <select name="recurrence_rule" id="meeting-recurrence">
                    <?php foreach ($recurrenceLabels as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($meeting['recurrence_rule'] ?? 'none') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" id="meeting-recurrence-end-wrap">
                <label>تاريخ نهاية التكرار</label>
                <input type="date" name="recurrence_end_date" id="meeting-recurrence-end" value="<?= e($meeting['recurrence_end_date'] ?? '') ?>">
            </div>
        </div>
        <script>
        (function () {
            var rule = document.getElementById('meeting-recurrence');
            var wrap = document.getElementById('meeting-recurrence-end-wrap');
            function sync() {
                wrap.style.display = rule.value === 'none' ? 'none' : '';
            }
            rule.addEventListener('change', sync);
            sync();
        })();
        </script>
        <?php endif; ?>

        <div class="form-actions">
            <?php if ($conflicts): ?>
                <button class="btn btn-danger" type="submit" name="confirm_conflicts" value="1">تجاهل التعارض والحفظ</button>
                <button class="btn btn-outline" type="submit">إعادة التحقق</button>
            <?php else: ?>
                <button class="btn" type="submit"><?= $isEdit ? 'حفظ التعديلات' : 'إنشاء الاجتماع' ?></button>
            <?php endif; ?>
            <a class="btn btn-outline" href="<?= $isEdit ? route('/meetings/' . $meeting['id']) : route('/meetings') ?>">إلغاء</a>
        </div>
    </form>
</div>

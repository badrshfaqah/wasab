<?php
$dayLabels = [0 => 'الأحد', 1 => 'الاثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء', 4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت'];
?>
<div class="page-head">
    <div><h1>إعدادات المتابعة اليومية</h1></div>
    <a class="btn btn-outline" href="<?= route('/checkins') ?>">← عودة</a>
</div>

<div class="card" style="max-width:560px;">
    <form method="post" action="<?= route('/checkins/settings') ?>">
        <?= csrf_field() ?>

        <div class="field">
            <label>وقت التذكير اليومي</label>
            <input type="time" name="reminder_time" value="<?= e($reminderTime) ?>">
            <p class="hint">يصل تنبيه (بالنظام والجوال) لكل من لم يسجل متابعته بعد هذا الوقت - يتطلب تفعيل مهمة الجدولة (Cron) من صفحة الإضافات.</p>
        </div>

        <div class="field">
            <label>أيام العمل</label>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <?php foreach ($dayLabels as $num => $label): ?>
                    <label style="display:flex;align-items:center;gap:5px;font-weight:400;margin:0;cursor:pointer;">
                        <input type="checkbox" name="workdays[]" value="<?= $num ?>" <?= in_array($num, $workdays, true) ? 'checked' : '' ?>>
                        <?= e($label) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="hint">لا تذكير ولا احتساب التزام خارج أيام العمل.</p>
        </div>

        <button class="btn" type="submit">حفظ</button>
    </form>
</div>

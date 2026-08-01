<div class="page-head">
    <div><h1>نقل عهدة</h1><p>نقل «<?= e($asset['name']) ?>» من حامله الحالي إلى حاملٍ جديد بخطوة واحدة.</p></div>
    <a class="btn btn-outline" href="<?= route('/custody/' . $asset['id']) ?>">← الأصل</a>
</div>

<div class="card" style="max-width:640px;">
    <div class="alert alert-info" style="margin-bottom:16px;">
        الحامل الحالي: <strong><?= e($asset['current_holder_name'] ?: '—') ?></strong>. سيُغلق سجل عهدته تلقائياً ويُنشأ محضر جديد للحامل الجديد.
    </div>
    <form method="post" action="<?= route('/custody/' . $asset['id'] . '/transfer') ?>">
        <?= csrf_field() ?>

        <div class="card-title"><span>👤 الحامل الجديد</span></div>
        <div class="field">
            <label>نوع الحامل</label>
            <select name="holder_type" id="holder-type" onchange="assetsHolderToggle()">
                <?php if ($employees): ?><option value="employee">من الملف الوظيفي</option><?php endif; ?>
                <option value="user">مستخدم بالنظام</option>
                <option value="manual">شخص يدوي (غير موجود بالنظام)</option>
            </select>
        </div>

        <?php if ($employees): ?>
        <div class="field holder-src" data-src="employee">
            <label>الموظف</label>
            <select name="holder_ref_employee">
                <option value="">— اختر —</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>"><?= e($emp['full_name']) ?><?= $emp['job_title'] ? ' — ' . e($emp['job_title']) : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="field holder-src" data-src="user">
            <label>المستخدم</label>
            <select name="holder_ref_user">
                <option value="">— اختر —</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= e($u['name']) ?> (<?= e($u['email']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="holder-src" data-src="manual">
            <div class="grid-2">
                <div class="field"><label>اسم الحامل</label><input type="text" name="holder_manual_name" maxlength="180" placeholder="الاسم الكامل"></div>
                <div class="field"><label>تواصل (اختياري)</label><input type="text" name="holder_contact" maxlength="120" placeholder="جوال / بريد"></div>
            </div>
        </div>

        <div class="grid-2" style="margin-top:16px;">
            <div class="field"><label>تاريخ النقل</label><input type="date" name="handover_date" value="<?= date('Y-m-d') ?>" required></div>
        </div>

        <button class="btn" type="submit">نقل العهدة</button>
    </form>
</div>

<script>
function assetsHolderToggle() {
    var type = document.getElementById('holder-type').value;
    document.querySelectorAll('.holder-src').forEach(function (el) {
        el.style.display = el.getAttribute('data-src') === type ? '' : 'none';
    });
}
document.addEventListener('DOMContentLoaded', function () {
    assetsHolderToggle();
    var form = document.querySelector('form[action*="/transfer"]');
    if (form) form.addEventListener('submit', function () {
        var type = document.getElementById('holder-type').value;
        var val = '';
        if (type === 'employee') { var s = form.querySelector('[name=holder_ref_employee]'); val = s ? s.value : ''; }
        else if (type === 'user') { var s2 = form.querySelector('[name=holder_ref_user]'); val = s2 ? s2.value : ''; }
        var hidden = form.querySelector('[name=holder_ref]') || (function () {
            var h = document.createElement('input'); h.type = 'hidden'; h.name = 'holder_ref'; form.appendChild(h); return h;
        })();
        hidden.value = val;
    });
});
</script>

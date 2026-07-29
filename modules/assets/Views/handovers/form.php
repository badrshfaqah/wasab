<div class="page-head">
    <div><h1>محضر تسليم جديد</h1><p>سلّم أصلاً أو أكثر لحاملٍ واحد — من الملف الوظيفي أو مستخدمي النظام أو شخص يدوي.</p></div>
    <a class="btn btn-outline" href="<?= route('/assets/handovers') ?>">← المحاضر</a>
</div>

<?php if (!$assignable): ?>
    <div class="empty-state"><div class="ic">📦</div><h3>لا توجد أصول متاحة للتسليم</h3><p>كل الأصول إمّا بعهدة أو غير متاحة. أضف أصلاً أو أرجع عهدة أولاً.</p><a class="btn" href="<?= route('/assets/create') ?>">+ إضافة أصل</a></div>
<?php else: ?>
<div class="card" style="max-width:720px;">
    <form method="post" action="<?= route('/assets/handovers') ?>">
        <?= csrf_field() ?>

        <div class="card-title"><span>👤 حامل العهدة</span></div>
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

        <div class="card-title" style="margin-top:16px;"><span>📦 الأصول المُسلَّمة</span></div>
        <p class="hint" style="margin:0 0 8px;">اختر أصلاً واحداً أو أكثر:</p>
        <div style="max-height:260px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:6px;">
            <?php foreach ($assignable as $a): ?>
                <label style="display:flex;align-items:center;gap:8px;padding:7px 8px;border-bottom:1px solid var(--border);font-weight:400;cursor:pointer;">
                    <input type="checkbox" name="asset_ids[]" value="<?= $a['id'] ?>" style="width:auto;" <?= $preselectAsset === (int) $a['id'] ? 'checked' : '' ?>>
                    <span><strong><?= e($a['name']) ?></strong><?= $a['asset_code'] ? ' <span class="hint">(' . e($a['asset_code']) . ')</span>' : '' ?><?= $a['serial_number'] ? ' <span class="hint" dir="ltr">' . e($a['serial_number']) . '</span>' : '' ?></span>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="grid-2" style="margin-top:16px;">
            <div class="field"><label>تاريخ التسليم</label><input type="date" name="handover_date" value="<?= date('Y-m-d') ?>" required></div>
        </div>
        <div class="field"><label>ملاحظات المحضر</label><textarea name="notes" rows="2" maxlength="1000"></textarea></div>

        <button class="btn" type="submit">تسجيل المحضر وإسناد العهد</button>
    </form>
</div>

<script>
// تحويل حقل الموظف/المستخدم المختار إلى holder_ref الموحّد عند الإرسال، وإظهار
// الحقل المناسب لنوع الحامل فقط.
function assetsHolderToggle() {
    var type = document.getElementById('holder-type').value;
    document.querySelectorAll('.holder-src').forEach(function (el) {
        el.style.display = el.getAttribute('data-src') === type ? '' : 'none';
    });
}
document.addEventListener('DOMContentLoaded', function () {
    assetsHolderToggle();
    var form = document.querySelector('form[action$="/assets/handovers"]');
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
<?php endif; ?>

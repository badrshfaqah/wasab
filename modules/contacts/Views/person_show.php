<?php
$pid = (int) $person['id'];
$back = '/contacts/people/' . $pid;
?>
<div class="page-head">
    <div>
        <h1><?= e($person['full_name']) ?></h1>
        <p style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <?php if (!empty($person['job_title'])): ?><span><?= e($person['job_title']) ?></span><?php endif; ?>
            <?php if (!$orgs): ?><span class="badge badge-muted">فرد مستقل</span><?php endif; ?>
            <?php if ($person['status'] === 'archived'): ?><span class="badge badge-warning">مؤرشف</span><?php endif; ?>
        </p>
    </div>
    <a class="btn btn-outline" href="<?= route('/contacts?tab=people') ?>">↩ الدليل</a>
</div>

<div class="grid-2" style="align-items:start;">
    <div class="card">
        <div class="card-title"><span>👤 البيانات</span></div>
        <table class="table-cards"><tbody>
            <?php foreach ([
                'الجوال' => $person['mobile'],
                'هاتف آخر' => $person['phone'],
                'البريد' => $person['email'],
                'المدينة' => $person['city'],
                'LinkedIn' => $person['linkedin'],
            ] as $label => $value): ?>
                <?php if (!empty($value)): ?>
                    <tr><td style="width:110px;color:var(--muted);"><?= e($label) ?></td><td style="word-break:break-all;"><?= e($value) ?></td></tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody></table>
        <?php if (!empty($person['notes'])): ?><p class="hint" style="white-space:pre-wrap;margin-top:10px;"><?= e($person['notes']) ?></p><?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title"><span>🏢 الجهات المرتبط بها (<?= count($orgs) ?>)</span></div>
        <?php if (!$orgs): ?>
            <p class="hint" style="margin-top:0;">فرد مستقل لا يتبع أي جهة — اربطه بجهة أو أكثر من الأسفل.</p>
        <?php endif; ?>
        <?php foreach ($orgs as $o): ?>
            <div class="doc-log" style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start;">
                <div>
                    <a href="<?= route('/contacts/orgs/' . $o['id']) ?>"><strong><?= e($o['name']) ?></strong></a>
                    <?php if (!empty($o['is_primary'])): ?><span class="badge badge-info">شخص التواصل</span><?php endif; ?>
                    <div class="doc-log-meta"><?= e(trim(($o['role_title'] ?? '') . ' · ' . ($o['role_department'] ?? ''), ' ·')) ?: 'بلا مسمّى محدد' ?></div>
                </div>
                <?php if ($canEdit): ?>
                <form method="post" action="<?= route('/contacts/unlink') ?>" data-confirm="فكّ ارتباطه بـ <?= e($o['name']) ?>؟">
                    <?= csrf_field() ?>
                    <input type="hidden" name="back" value="<?= e($back) ?>">
                    <input type="hidden" name="organization_id" value="<?= $o['id'] ?>">
                    <input type="hidden" name="person_id" value="<?= $pid ?>">
                    <button class="btn btn-ghost btn-sm" type="submit">✕</button>
                </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if ($canEdit): ?>
        <details style="margin-top:12px;">
            <summary class="btn btn-outline btn-sm" style="cursor:pointer;">➕ ربط بجهة</summary>
            <form method="post" action="<?= route('/contacts/link') ?>" style="margin-top:10px;">
                <?= csrf_field() ?>
                <input type="hidden" name="back" value="<?= e($back) ?>">
                <input type="hidden" name="person_id" value="<?= $pid ?>">
                <div class="field">
                    <label>الجهة</label>
                    <select name="organization_id" required>
                        <option value="">اختر جهة...</option>
                        <?php foreach ($allOrgs as $ao): ?>
                            <option value="<?= $ao['id'] ?>"><?= e($ao['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid-2">
                    <div class="field"><label>مسمّاه فيها</label><input type="text" name="job_title" placeholder="مثال: مستشار"></div>
                    <div class="field"><label>القسم</label><input type="text" name="department"></div>
                </div>
                <label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-bottom:10px;">
                    <input type="checkbox" name="is_primary" value="1" style="width:auto;"> شخص التواصل الرئيسي لتلك الجهة
                </label>
                <p class="hint">المسمّى صفة لهذه العلاقة وحدها — قد يكون مديراً في جهة ومستشاراً في أخرى.</p>
                <button class="btn btn-sm" type="submit">ربط</button>
            </form>
        </details>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/_linked.php'; ?>

<?php if ($canEdit): ?>
<div class="card">
    <div class="card-title"><span>✏️ تعديل بيانات الفرد</span></div>
    <form method="post" action="<?= route($back) ?>">
        <?= csrf_field() ?>
        <div class="grid-2">
            <div class="field"><label>الاسم الكامل</label><input type="text" name="full_name" value="<?= e($person['full_name']) ?>" required></div>
            <div class="field"><label>المسمّى العام</label><input type="text" name="job_title" value="<?= e($person['job_title'] ?? '') ?>"></div>
        </div>
        <div class="grid-2">
            <div class="field"><label>الجوال</label><input type="text" name="mobile" value="<?= e($person['mobile'] ?? '') ?>"></div>
            <div class="field"><label>هاتف آخر</label><input type="text" name="phone" value="<?= e($person['phone'] ?? '') ?>"></div>
        </div>
        <div class="grid-2">
            <div class="field"><label>البريد</label><input type="email" name="email" value="<?= e($person['email'] ?? '') ?>"></div>
            <div class="field"><label>المدينة</label><input type="text" name="city" value="<?= e($person['city'] ?? '') ?>"></div>
        </div>
        <div class="field"><label>LinkedIn</label><input type="text" name="linkedin" value="<?= e($person['linkedin'] ?? '') ?>"></div>
        <div class="field"><label>ملاحظات</label><textarea name="notes" rows="2"><?= e($person['notes'] ?? '') ?></textarea></div>
        <div class="form-actions">
            <button class="btn" type="submit">حفظ</button>
            <?php if ($canDelete): ?>
                <button class="btn btn-outline" type="submit" formaction="<?= route($back . '/archive') ?>"
                        onclick="return confirm('<?= $person['status'] === 'active' ? 'أرشفة هذا الفرد؟' : 'إعادة تنشيطه؟' ?>');">
                    <?= $person['status'] === 'active' ? '🗄️ أرشفة' : '↩️ تنشيط' ?>
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>

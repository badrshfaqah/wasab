<?php
$oid = (int) $org['id'];
$social = $org['social_json'] ? (array) json_decode($org['social_json'], true) : [];
$back = '/contacts/orgs/' . $oid;
?>
<div class="page-head">
    <div>
        <h1><?= e($org['name']) ?></h1>
        <p style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <?php if (!empty($org['kind'])): ?><span class="badge badge-muted"><?= e($org['kind']) ?></span><?php endif; ?>
            <?php if (!empty($org['sector'])): ?><span><?= e($org['sector']) ?></span><?php endif; ?>
            <?php if ($org['status'] === 'archived'): ?><span class="badge badge-warning">مؤرشفة</span><?php endif; ?>
        </p>
    </div>
    <a class="btn btn-outline" href="<?= route('/contacts') ?>">↩ الدليل</a>
</div>

<div class="grid-2" style="align-items:start;">
    <div class="card">
        <div class="card-title"><span>🏢 البيانات</span></div>
        <table class="table-cards"><tbody>
            <?php foreach ([
                'الاسم التجاري' => $org['trade_name'],
                'المدينة' => trim(($org['city'] ?? '') . ' ' . ($org['country'] ?? '')),
                'العنوان' => $org['address'],
                'الهاتف' => $org['phone'],
                'البريد' => $org['email'],
                'الموقع' => $org['website'],
            ] as $label => $value): ?>
                <?php if (!empty($value)): ?>
                    <tr><td style="width:120px;color:var(--muted);"><?= e($label) ?></td><td><?= e($value) ?></td></tr>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php foreach ($social as $net => $url): ?>
                <tr><td style="color:var(--muted);"><?= e(ucfirst($net)) ?></td><td style="word-break:break-all;"><?= e($url) ?></td></tr>
            <?php endforeach; ?>
        </tbody></table>
        <?php if (!empty($org['notes'])): ?><p class="hint" style="white-space:pre-wrap;margin-top:10px;"><?= e($org['notes']) ?></p><?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title"><span>👥 الأشخاص (<?= count($people) ?>)</span></div>
        <?php foreach ($people as $p): ?>
            <div class="doc-log" style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start;">
                <div>
                    <a href="<?= route('/contacts/people/' . $p['id']) ?>"><strong><?= e($p['full_name']) ?></strong></a>
                    <?php if (!empty($p['is_primary'])): ?><span class="badge badge-info">شخص التواصل</span><?php endif; ?>
                    <div class="doc-log-meta">
                        <?= e(trim(($p['role_title'] ?: $p['job_title'] ?: '') . ' · ' . ($p['mobile'] ?? '') . ' · ' . ($p['email'] ?? ''), ' ·')) ?: '—' ?>
                    </div>
                </div>
                <?php if ($canEdit): ?>
                <form method="post" action="<?= route('/contacts/unlink') ?>" data-confirm="فكّ ارتباط <?= e($p['full_name']) ?> بهذه الجهة؟ يبقى في الدليل.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="back" value="<?= e($back) ?>">
                    <input type="hidden" name="organization_id" value="<?= $oid ?>">
                    <input type="hidden" name="person_id" value="<?= $p['id'] ?>">
                    <button class="btn btn-ghost btn-sm" type="submit">✕</button>
                </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if ($canEdit): ?>
        <details style="margin-top:12px;">
            <summary class="btn btn-outline btn-sm" style="cursor:pointer;">➕ ربط شخص</summary>
            <form method="post" action="<?= route('/contacts/link') ?>" style="margin-top:10px;">
                <?= csrf_field() ?>
                <input type="hidden" name="back" value="<?= e($back) ?>">
                <input type="hidden" name="organization_id" value="<?= $oid ?>">
                <div class="field">
                    <label>فرد مسجّل في الدليل</label>
                    <select name="person_id">
                        <option value="">— أو اكتب اسم شخص جديد بالأسفل —</option>
                        <?php foreach ($allPeople as $ap): ?>
                            <option value="<?= $ap['id'] ?>"><?= e($ap['full_name']) ?><?= $ap['job_title'] ? ' — ' . e($ap['job_title']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid-2">
                    <div class="field"><label>أو اسم شخص جديد</label><input type="text" name="person_name"></div>
                    <div class="field"><label>جواله</label><input type="text" name="person_mobile"></div>
                </div>
                <div class="grid-2">
                    <div class="field"><label>مسمّاه في هذه الجهة</label><input type="text" name="job_title"></div>
                    <div class="field"><label>القسم</label><input type="text" name="department"></div>
                </div>
                <label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-bottom:10px;">
                    <input type="checkbox" name="is_primary" value="1" style="width:auto;"> اجعله شخص التواصل الرئيسي
                </label>
                <button class="btn btn-sm" type="submit">ربط</button>
            </form>
        </details>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/_linked.php'; ?>

<?php if ($canEdit): ?>
<div class="card">
    <div class="card-title"><span>✏️ تعديل بيانات الجهة</span></div>
    <form method="post" action="<?= route($back) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="grid-2">
            <div class="field"><label>اسم الجهة</label><input type="text" name="name" value="<?= e($org['name']) ?>" required></div>
            <div class="field"><label>الاسم التجاري</label><input type="text" name="trade_name" value="<?= e($org['trade_name'] ?? '') ?>"></div>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>نوع الجهة</label>
                <select name="kind">
                    <option value="">—</option>
                    <?php foreach ($kinds as $k): ?>
                        <option value="<?= e($k) ?>" <?= ($org['kind'] ?? '') === $k ? 'selected' : '' ?>><?= e($k) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field"><label>القطاع</label><input type="text" name="sector" value="<?= e($org['sector'] ?? '') ?>"></div>
        </div>
        <div class="grid-2">
            <div class="field"><label>الدولة</label><input type="text" name="country" value="<?= e($org['country'] ?? '') ?>"></div>
            <div class="field"><label>المدينة</label><input type="text" name="city" value="<?= e($org['city'] ?? '') ?>"></div>
        </div>
        <div class="field"><label>العنوان</label><input type="text" name="address" value="<?= e($org['address'] ?? '') ?>"></div>
        <div class="grid-2">
            <div class="field"><label>الهاتف</label><input type="text" name="phone" value="<?= e($org['phone'] ?? '') ?>"></div>
            <div class="field"><label>البريد</label><input type="email" name="email" value="<?= e($org['email'] ?? '') ?>"></div>
        </div>
        <div class="grid-2">
            <div class="field"><label>الموقع</label><input type="text" name="website" value="<?= e($org['website'] ?? '') ?>"></div>
            <div class="field"><label>الشعار</label><input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></div>
        </div>
        <div class="field"><label>ملاحظات</label><textarea name="notes" rows="2"><?= e($org['notes'] ?? '') ?></textarea></div>
        <div class="form-actions">
            <button class="btn" type="submit">حفظ</button>
            <?php if ($canDelete): ?>
                <button class="btn btn-outline" type="submit" formaction="<?= route($back . '/archive') ?>"
                        onclick="return confirm('<?= $org['status'] === 'active' ? 'أرشفة هذه الجهة؟' : 'إعادة تنشيطها؟' ?>');">
                    <?= $org['status'] === 'active' ? '🗄️ أرشفة' : '↩️ تنشيط' ?>
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>

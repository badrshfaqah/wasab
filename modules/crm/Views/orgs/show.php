<?php
$wid = (int) $workspace['id'];
$oid = (int) $organization['id'];
$social = $organization['social_json'] ? (array) json_decode($organization['social_json'], true) : [];
$catIds = array_map(fn ($c) => (int) $c['id'], $categories);
?>
<div class="page-head">
    <div>
        <h1><?= e($organization['name']) ?></h1>
        <p style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <span><?= e($workspace['icon']) ?> <?= e($workspace['name']) ?></span>
            <?php foreach ($categories as $c): ?>
                <span class="badge" style="background:<?= e($c['color']) ?>22;color:<?= e($c['color']) ?>;"><?= e($c['name']) ?></span>
            <?php endforeach; ?>
            <?php if (!empty($relation['relation_status'])): ?>
                <span class="badge badge-info"><?= e($relation['relation_status']) ?></span>
            <?php endif; ?>
        </p>
    </div>
    <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid) ?>">↩ جهات المساحة</a>
</div>

<div class="grid-2" style="align-items:start;">
    <div class="card">
        <div class="card-title"><span>🏢 بيانات الجهة</span></div>
        <p class="hint" style="margin-top:0;">هذه البيانات مشتركة في دليل الشركة — تعديلها ينعكس في كل المساحات المرتبطة بها.</p>
        <div class="table-wrap">
        <table class="table-cards"><tbody>
            <?php foreach ([
                'الاسم التجاري' => $organization['trade_name'],
                'القطاع' => $organization['sector'],
                'المدينة' => trim(($organization['city'] ?? '') . ' ' . ($organization['country'] ?? '')),
                'العنوان' => $organization['address'],
                'الهاتف' => $organization['phone'],
                'البريد' => $organization['email'],
                'الموقع' => $organization['website'],
            ] as $label => $value): ?>
                <?php if (!empty($value)): ?>
                    <tr><td style="width:130px;color:var(--muted);"><?= e($label) ?></td><td><?= e($value) ?></td></tr>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php foreach ($social as $net => $url): ?>
                <tr><td style="color:var(--muted);"><?= e(ucfirst($net)) ?></td><td style="word-break:break-all;"><?= e($url) ?></td></tr>
            <?php endforeach; ?>
        </tbody></table>
        </div>
        <?php if (!empty($organization['description'])): ?>
            <p style="margin-top:10px;"><?= nl2br(e($organization['description'])) ?></p>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title"><span>🔗 العلاقة داخل هذه المساحة</span></div>
        <table class="table-cards"><tbody>
            <tr><td style="width:130px;color:var(--muted);">المسؤول</td><td><?= e($relation['owner_id'] ? (array_column($members, 'user_name', 'user_id')[$relation['owner_id']] ?? '—') : '—') ?></td></tr>
            <tr><td style="color:var(--muted);">آخر تواصل</td><td><?= $relation['last_activity_at'] ? format_date($relation['last_activity_at'], 'Y-m-d H:i') : 'لم يبدأ بعد' ?></td></tr>
            <tr><td style="color:var(--muted);">المتابعة القادمة</td><td><?= $relation['next_action_at'] ? format_date($relation['next_action_at'], 'Y-m-d') : '—' ?></td></tr>
            <tr><td style="color:var(--muted);">أُضيفت</td><td><?= format_date($relation['created_at'], 'Y-m-d') ?></td></tr>
        </tbody></table>
        <?php if (!empty($relation['notes'])): ?>
            <p class="hint" style="margin-top:10px;white-space:pre-wrap;"><?= e($relation['notes']) ?></p>
        <?php endif; ?>

        <?php if ($otherSpaces): ?>
            <div style="margin-top:14px;">
                <div class="hint" style="margin-bottom:6px;">مرتبطة أيضاً بمساحاتك:</div>
                <?php foreach ($otherSpaces as $s): ?>
                    <a class="badge badge-muted" style="margin-inline-end:6px;" href="<?= route('/crm/w/' . $s['workspace_id'] . '/orgs/' . $oid) ?>">
                        <?= e($s['icon']) ?> <?= e($s['workspace_name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title"><span>👥 الأشخاص (<?= count($contacts) ?>)</span></div>
        <p class="hint" style="margin-top:0;">أشخاص الجهة مشتركون بين كل المساحات المرتبطة بها.</p>
        <?php if (!$contacts): ?>
            <p class="hint">لا يوجد أشخاص مسجّلون بعد.</p>
        <?php endif; ?>
        <?php foreach ($contacts as $c): ?>
            <div class="doc-log" style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start;">
                <div>
                    <strong><?= e($c['name']) ?></strong><?= $c['job_title'] ? ' — ' . e($c['job_title']) : '' ?>
                    <?php if ($c['status'] === 'inactive'): ?><span class="badge badge-muted">غير نشط</span><?php endif; ?>
                    <div class="doc-log-meta">
                        <?= e(trim(($c['department'] ?? '') . ' · ' . ($c['mobile'] ?? '') . ' · ' . ($c['email'] ?? ''), ' ·')) ?: '—' ?>
                    </div>
                </div>
                <?php if ($canManageContacts): ?>
                <div style="display:flex;gap:4px;">
                    <details>
                        <summary class="btn btn-ghost btn-sm" style="cursor:pointer;">✎</summary>
                        <form method="post" action="<?= route('/crm/w/' . $wid . '/orgs/' . $oid . '/contacts') ?>" style="margin-top:8px;min-width:220px;text-align:right;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="contact_id" value="<?= $c['id'] ?>">
                            <div class="field"><label>الاسم</label><input type="text" name="name" value="<?= e($c['name']) ?>" required></div>
                            <div class="field"><label>المسمى</label><input type="text" name="job_title" value="<?= e($c['job_title'] ?? '') ?>"></div>
                            <div class="field"><label>الجوال</label><input type="text" name="mobile" value="<?= e($c['mobile'] ?? '') ?>"></div>
                            <div class="field"><label>البريد</label><input type="email" name="email" value="<?= e($c['email'] ?? '') ?>"></div>
                            <div class="field"><label>الحالة</label>
                                <select name="status">
                                    <option value="active" <?= $c['status'] === 'active' ? 'selected' : '' ?>>نشط</option>
                                    <option value="inactive" <?= $c['status'] === 'inactive' ? 'selected' : '' ?>>غير نشط</option>
                                </select>
                            </div>
                            <button class="btn btn-sm" type="submit">حفظ</button>
                        </form>
                    </details>
                    <form method="post" action="<?= route('/crm/w/' . $wid . '/orgs/' . $oid . '/contacts/' . $c['id'] . '/delete') ?>" data-confirm="حذف <?= e($c['name']) ?>؟">
                        <?= csrf_field() ?>
                        <button class="btn btn-ghost btn-sm" type="submit">✕</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if ($canManageContacts): ?>
        <details style="margin-top:12px;">
            <summary class="btn btn-outline btn-sm" style="cursor:pointer;">➕ إضافة شخص</summary>
            <form method="post" action="<?= route('/crm/w/' . $wid . '/orgs/' . $oid . '/contacts') ?>" style="margin-top:10px;">
                <?= csrf_field() ?>
                <div class="grid-2">
                    <div class="field"><label>الاسم</label><input type="text" name="name" required></div>
                    <div class="field"><label>المسمى الوظيفي</label><input type="text" name="job_title"></div>
                </div>
                <div class="grid-2">
                    <div class="field"><label>القسم</label><input type="text" name="department"></div>
                    <div class="field"><label>الجوال</label><input type="text" name="mobile"></div>
                </div>
                <div class="grid-2">
                    <div class="field"><label>البريد</label><input type="email" name="email"></div>
                    <div class="field"><label>LinkedIn</label><input type="text" name="linkedin"></div>
                </div>
                <button class="btn btn-sm" type="submit">إضافة</button>
            </form>
        </details>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title"><span>🕓 سجل التغييرات</span></div>
        <?php if (!$logs): ?><p class="hint" style="margin-top:0;">لا سجل بعد.</p><?php endif; ?>
        <?php foreach ($logs as $log): ?>
            <div class="doc-log">
                <div><?= e($log['description']) ?></div>
                <div class="doc-log-meta"><?= e($log['user_name'] ?? 'النظام') ?> · <?= format_date($log['created_at'], 'Y-m-d H:i') ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/_timeline.php'; ?>

<?php if ($canEdit): ?>
<div class="card">
    <div class="card-title"><span>✏️ تعديل</span></div>
    <form method="post" action="<?= route('/crm/w/' . $wid . '/orgs/' . $oid) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="grid-2">
            <div class="field"><label>اسم الجهة</label><input type="text" name="name" value="<?= e($organization['name']) ?>" required></div>
            <div class="field"><label>الاسم التجاري</label><input type="text" name="trade_name" value="<?= e($organization['trade_name'] ?? '') ?>"></div>
        </div>
        <div class="grid-2">
            <div class="field"><label>القطاع</label><input type="text" name="sector" value="<?= e($organization['sector'] ?? '') ?>"></div>
            <div class="field"><label>المدينة</label><input type="text" name="city" value="<?= e($organization['city'] ?? '') ?>"></div>
        </div>
        <div class="grid-2">
            <div class="field"><label>الدولة</label><input type="text" name="country" value="<?= e($organization['country'] ?? '') ?>"></div>
            <div class="field"><label>العنوان</label><input type="text" name="address" value="<?= e($organization['address'] ?? '') ?>"></div>
        </div>
        <div class="grid-2">
            <div class="field"><label>البريد</label><input type="email" name="email" value="<?= e($organization['email'] ?? '') ?>"></div>
            <div class="field"><label>الهاتف</label><input type="text" name="phone" value="<?= e($organization['phone'] ?? '') ?>"></div>
        </div>
        <div class="grid-2">
            <div class="field"><label>الموقع</label><input type="text" name="website" value="<?= e($organization['website'] ?? '') ?>"></div>
            <div class="field"><label>LinkedIn</label><input type="text" name="social_linkedin" value="<?= e($social['linkedin'] ?? '') ?>"></div>
        </div>
        <div class="field"><label>وصف</label><textarea name="description" rows="2"><?= e($organization['description'] ?? '') ?></textarea></div>
        <div class="field"><label>ملاحظات (بيانات الجهة)</label><textarea name="notes" rows="2"><?= e($organization['notes'] ?? '') ?></textarea></div>

        <div class="card-title divided" style="margin-top:14px;"><span>بيانات العلاقة في هذه المساحة</span></div>
        <div class="grid-2">
            <div class="field">
                <label>المسؤول عن العلاقة</label>
                <select name="owner_id">
                    <option value="">—</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?= $m['user_id'] ?>" <?= (int) ($relation['owner_id'] ?? 0) === (int) $m['user_id'] ? 'selected' : '' ?>><?= e($m['user_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>حالة العلاقة</label>
                <input type="text" name="relation_status" maxlength="60" value="<?= e($relation['relation_status'] ?? '') ?>" placeholder="مثال: شريك نشط">
            </div>
        </div>
        <?php if ($allCategories): ?>
        <div class="field">
            <label>التصنيفات</label>
            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                <?php foreach ($allCategories as $c): ?>
                    <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
                        <input type="checkbox" name="categories[]" value="<?= $c['id'] ?>" style="width:auto;" <?= in_array((int) $c['id'], $catIds, true) ? 'checked' : '' ?>>
                        <?= e($c['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="field"><label>ملاحظات العلاقة</label><textarea name="relation_notes" rows="2"><?= e($relation['notes'] ?? '') ?></textarea></div>

        <div class="form-actions">
            <button class="btn" type="submit">حفظ</button>
            <?php if ($canDelete): ?>
                <button class="btn btn-danger" type="submit" formaction="<?= route('/crm/w/' . $wid . '/orgs/' . $oid . '/unlink') ?>"
                        onclick="return confirm('إزالة الجهة من هذه المساحة؟ تبقى في الدليل المركزي وفي المساحات الأخرى.');">إزالة من المساحة</button>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>

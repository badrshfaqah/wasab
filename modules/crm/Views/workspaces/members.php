<div class="page-head">
    <div>
        <h1>أعضاء: <?= e($workspace['name']) ?></h1>
        <p>من ليس في هذه القائمة لا يرى المساحة ولا جهاتها إطلاقاً — حتى لو كان مستخدماً في النظام.</p>
    </div>
    <div style="display:flex;gap:8px;">
        <a class="btn btn-outline" href="<?= route('/crm/w/' . $workspace['id'] . '/edit') ?>">⚙️ الإعدادات</a>
        <a class="btn btn-outline" href="<?= route('/crm/w/' . $workspace['id']) ?>">↩ المساحة</a>
    </div>
</div>

<div class="card">
    <div class="card-title"><span>الأعضاء الحاليون (<?= count($members) ?>)</span></div>
    <div class="table-wrap">
    <table class="table-cards">
        <thead><tr><th>العضو</th><th>الدور</th><th>الصلاحيات</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($members as $m): ?>
            <tr>
                <td><?= e($m['user_name']) ?><div class="hint"><?= e($m['email'] ?? '') ?></div></td>
                <td><span class="badge badge-<?= $m['role'] === 'manager' ? 'info' : 'muted' ?>"><?= e($roleLabels[$m['role']]) ?></span></td>
                <td>
                    <?php if ($m['role'] === 'manager'): ?>
                        <span class="hint">كل الصلاحيات داخل المساحة</span>
                    <?php else: ?>
                        <span class="hint" style="display:flex;gap:4px;flex-wrap:wrap;">
                            <?php foreach ($m['abilities'] as $ab): ?>
                                <span class="badge badge-muted" style="font-size:.75em;"><?= e($abilityLabels[$ab] ?? $ab) ?></span>
                            <?php endforeach; ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td style="white-space:nowrap;">
                    <details>
                        <summary class="btn btn-outline btn-sm" style="display:inline-block;cursor:pointer;">تعديل</summary>
                        <form method="post" action="<?= route('/crm/w/' . $workspace['id'] . '/members') ?>" style="margin-top:8px;text-align:right;min-width:240px;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= $m['user_id'] ?>">
                            <div class="field" style="margin-bottom:8px;">
                                <label>الدور</label>
                                <select name="role">
                                    <?php foreach ($roleLabels as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= $m['role'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="max-height:180px;overflow:auto;display:flex;flex-direction:column;gap:4px;">
                                <?php foreach ($abilityLabels as $key => $label): ?>
                                    <label style="display:flex;align-items:center;gap:6px;font-weight:400;font-size:.85em;">
                                        <input type="checkbox" name="abilities[]" value="<?= $key ?>" style="width:auto;"
                                            <?= in_array($key, $m['abilities'], true) ? 'checked' : '' ?>>
                                        <?= e($label) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="hint" style="margin:6px 0;">اترك الصلاحيات كما هي ليأخذ العضو صلاحيات دوره الافتراضية.</p>
                            <button class="btn btn-sm" type="submit">حفظ</button>
                        </form>
                    </details>
                    <?php if ((int) $m['user_id'] !== (int) $workspace['created_by']): ?>
                        <form method="post" action="<?= route('/crm/w/' . $workspace['id'] . '/members/' . $m['user_id'] . '/remove') ?>" style="display:inline;" data-confirm="إزالة <?= e($m['user_name']) ?> من المساحة؟">
                            <?= csrf_field() ?>
                            <button class="btn btn-ghost btn-sm" type="submit">✕</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="card" style="max-width:620px;">
    <div class="card-title"><span>➕ إضافة عضو</span></div>
    <form method="post" action="<?= route('/crm/w/' . $workspace['id'] . '/members') ?>">
        <?= csrf_field() ?>
        <div class="grid-2">
            <div class="field">
                <label>الموظف</label>
                <select name="user_id" required>
                    <option value="">اختر موظفاً...</option>
                    <?php
                    $existing = array_map(fn ($m) => (int) $m['user_id'], $members);
                    foreach ($companyUsers as $u):
                        if (in_array((int) $u['id'], $existing, true)) continue; ?>
                        <option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>الدور</label>
                <select name="role">
                    <?php foreach ($roleLabels as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $key === 'member' ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <p class="hint">مدير المساحة يملك كل الصلاحيات · العضو يضيف الجهات والأنشطة والفرص · المشاهد يطّلع فقط.</p>
        <button class="btn" type="submit">إضافة العضو</button>
    </form>
</div>

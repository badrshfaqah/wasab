<?php $wid = (int) $workspace['id']; $outcomes = ['open' => 'مرحلة عمل', 'won' => 'نهائية — تم الاتفاق', 'lost' => 'نهائية — لم يكتمل']; ?>
<div class="page-head">
    <div>
        <h1>مراحل العمل — <?= e($workspace['name']) ?></h1>
        <p>صمّم مسار عملك كما يناسب نشاط هذه المساحة: أضف المراحل ورتّبها وحدد أيّها ينهي الفرصة.</p>
    </div>
    <div style="display:flex;gap:8px;">
        <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid . '/opportunities') ?>">💼 الفرص</a>
        <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid) ?>">↩ المساحة</a>
    </div>
</div>

<?php foreach ($pipelines as $p): ?>
<div class="card">
    <div class="card-title divided">
        <span>🪜 <?= e($p['name']) ?> <?php if ($p['is_default']): ?><span class="badge badge-info">الافتراضي</span><?php endif; ?></span>
    </div>
    <div class="table-wrap">
    <table class="table-cards">
        <thead><tr><th>المرحلة</th><th>النوع</th><th>الحالة</th><th>الترتيب</th><th></th></tr></thead>
        <tbody>
        <?php foreach (($stages[$p['id']] ?? []) as $s): ?>
            <tr>
                <td>
                    <form method="post" action="<?= route('/crm/w/' . $wid . '/stages/' . $s['id']) ?>" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save">
                        <input type="color" name="color" value="<?= e($s['color']) ?>" style="width:44px;height:34px;padding:2px;">
                        <input type="text" name="name" value="<?= e($s['name']) ?>" style="width:150px;">
                        <select name="outcome" style="width:auto;">
                            <?php foreach ($outcomes as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $s['outcome'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline btn-sm" type="submit">حفظ</button>
                    </form>
                </td>
                <td><span class="badge badge-<?= $s['outcome'] === 'won' ? 'success' : ($s['outcome'] === 'lost' ? 'danger' : 'muted') ?>"><?= e($outcomes[$s['outcome']]) ?></span></td>
                <td><?= $s['is_active'] ? '<span class="badge badge-success">نشطة</span>' : '<span class="badge badge-muted">معطّلة</span>' ?></td>
                <td style="white-space:nowrap;">
                    <form method="post" action="<?= route('/crm/w/' . $wid . '/stages/' . $s['id']) ?>" style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="action" value="up"><button class="btn btn-ghost btn-sm" type="submit">▲</button>
                    </form>
                    <form method="post" action="<?= route('/crm/w/' . $wid . '/stages/' . $s['id']) ?>" style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="action" value="down"><button class="btn btn-ghost btn-sm" type="submit">▼</button>
                    </form>
                </td>
                <td>
                    <form method="post" action="<?= route('/crm/w/' . $wid . '/stages/' . $s['id']) ?>">
                        <?= csrf_field() ?><input type="hidden" name="action" value="toggle">
                        <button class="btn btn-outline btn-sm" type="submit"><?= $s['is_active'] ? 'تعطيل' : 'تفعيل' ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p class="hint">المرحلة المعطّلة تختفي من اللوحة لكن تبقى الفرص القديمة عليها كما هي.</p>

    <form method="post" action="<?= route('/crm/w/' . $wid . '/stages') ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-top:10px;">
        <?= csrf_field() ?>
        <input type="hidden" name="pipeline_id" value="<?= $p['id'] ?>">
        <div class="field" style="margin:0;"><label>مرحلة جديدة</label><input type="text" name="name" required placeholder="اسم المرحلة"></div>
        <div class="field" style="margin:0;"><label>اللون</label><input type="color" name="color" value="#6b7280" style="width:56px;height:38px;padding:2px;"></div>
        <div class="field" style="margin:0;">
            <label>النوع</label>
            <select name="outcome">
                <?php foreach ($outcomes as $key => $label): ?><option value="<?= $key ?>"><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-sm" type="submit">إضافة</button>
    </form>
</div>
<?php endforeach; ?>

<div class="card" style="max-width:620px;">
    <div class="card-title"><span>➕ مسار جديد</span></div>
    <p class="hint" style="margin-top:0;">مسار مستقل لنوع آخر من الفرص (مثال: مسار الرعاية ومسار التوريد).</p>
    <form method="post" action="<?= route('/crm/w/' . $wid . '/pipelines') ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
        <?= csrf_field() ?>
        <div class="field" style="margin:0;flex:1;min-width:200px;"><label>اسم المسار</label><input type="text" name="name" required></div>
        <label style="display:flex;align-items:center;gap:6px;font-weight:400;margin-bottom:8px;">
            <input type="checkbox" name="is_default" value="1" style="width:auto;"> الافتراضي
        </label>
        <button class="btn btn-sm" type="submit">إنشاء</button>
    </form>
</div>

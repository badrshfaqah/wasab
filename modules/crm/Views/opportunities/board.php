<?php
$wid = (int) $workspace['id'];
$money = fn ($v) => $v ? number_format((float) $v, 0) : '';
?>
<div class="page-head">
    <div>
        <h1>الفرص — <?= e($workspace['name']) ?></h1>
        <p>اسحب البطاقة بين المراحل لتحديث حالتها، وكل نقلة تُسجَّل في سجل علاقة الجهة.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php if ($canManagePipeline): ?>
            <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid . '/pipelines') ?>">🪜 المراحل</a>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid) ?>">🏢 الجهات</a>
        <?php if ($canCreate): ?>
            <a class="btn" href="<?= route('/crm/w/' . $wid . '/opportunities/create') ?>">+ فرصة جديدة</a>
        <?php endif; ?>
    </div>
</div>

<div class="cards-row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:14px;">
    <div class="card" style="margin-bottom:0;"><div class="hint">فرص مفتوحة</div><div style="font-size:1.6em;font-weight:700;"><?= (int) $stats['open'] ?></div></div>
    <div class="card" style="margin-bottom:0;"><div class="hint">قيمة متوقعة</div><div style="font-size:1.6em;font-weight:700;"><?= $money($stats['open_value']) ?: '—' ?></div></div>
    <div class="card" style="margin-bottom:0;"><div class="hint">تم الاتفاق</div><div style="font-size:1.6em;font-weight:700;color:var(--success);"><?= (int) $stats['won'] ?></div></div>
    <div class="card" style="margin-bottom:0;"><div class="hint">لم تكتمل</div><div style="font-size:1.6em;font-weight:700;color:var(--danger);"><?= (int) $stats['lost'] ?></div></div>
</div>

<div class="card">
    <form method="get" action="<?= route('/crm/w/' . $wid . '/opportunities') ?>" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
        <?php if (count($pipelines) > 1): ?>
        <div class="field" style="margin:0;min-width:170px;">
            <label>المسار</label>
            <select name="pipeline" onchange="this.form.submit()">
                <?php foreach ($pipelines as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= (int) $pipeline['id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php else: ?><input type="hidden" name="pipeline" value="<?= (int) $pipeline['id'] ?>"><?php endif; ?>
        <div class="field" style="margin:0;flex:1;min-width:160px;">
            <label>بحث</label>
            <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="اسم الفرصة أو الجهة...">
        </div>
        <div class="field" style="margin:0;min-width:150px;">
            <label>المسؤول</label>
            <select name="owner">
                <option value="">الكل</option>
                <?php foreach ($members as $m): ?>
                    <option value="<?= $m['user_id'] ?>" <?= (int) $filters['owner'] === (int) $m['user_id'] ? 'selected' : '' ?>><?= e($m['user_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <label style="display:flex;align-items:center;gap:6px;font-weight:400;margin-bottom:8px;">
            <input type="checkbox" name="closed" value="1" style="width:auto;" <?= $filters['include_closed'] ? 'checked' : '' ?>>
            إظهار المغلقة
        </label>
        <button class="btn btn-sm" type="submit">تطبيق</button>
    </form>
</div>

<div id="crm-board" data-move-url="<?= route('/crm/w/' . $wid . '/opportunities') ?>" data-csrf="<?= e(\App\Core\Csrf::token()) ?>"
     style="display:flex;gap:12px;overflow-x:auto;padding-bottom:10px;align-items:flex-start;">
    <?php foreach ($stages as $stage): ?>
        <?php $cards = $grouped[(int) $stage['id']] ?? []; ?>
        <div class="crm-col" data-stage="<?= (int) $stage['id'] ?>"
             style="flex:0 0 260px;background:var(--card-bg,#fff);border:1px solid var(--border);border-radius:12px;padding:10px;min-height:120px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:6px;padding-bottom:8px;border-bottom:2px solid <?= e($stage['color']) ?>;">
                <strong style="font-size:.95em;"><?= e($stage['name']) ?></strong>
                <span class="badge badge-muted"><?= count($cards) ?></span>
            </div>
            <div class="crm-drop" style="display:flex;flex-direction:column;gap:8px;margin-top:10px;min-height:60px;">
                <?php foreach ($cards as $c): ?>
                    <div class="crm-card" draggable="<?= $canEdit ? 'true' : 'false' ?>" data-id="<?= (int) $c['id'] ?>"
                         style="border:1px solid var(--border);border-radius:10px;padding:10px;background:var(--bg,#fff);cursor:<?= $canEdit ? 'grab' : 'default' ?>;">
                        <a href="<?= route('/crm/w/' . $wid . '/opportunities/' . $c['id']) ?>" style="font-weight:600;"><?= e($c['name']) ?></a>
                        <div class="hint" style="margin-top:4px;">🏢 <?= e($c['organization_name']) ?></div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;align-items:center;">
                            <?php if (!empty($c['value'])): ?><span class="badge badge-muted"><?= $money($c['value']) ?></span><?php endif; ?>
                            <?php if (!empty($c['probability'])): ?><span class="badge badge-muted"><?= (int) $c['probability'] ?>%</span><?php endif; ?>
                            <?php if ($c['status'] === 'won'): ?><span class="badge badge-success">تم</span><?php endif; ?>
                            <?php if ($c['status'] === 'lost'): ?><span class="badge badge-danger">لم يكتمل</span><?php endif; ?>
                        </div>
                        <div class="doc-log-meta" style="margin-top:6px;">
                            <?= e($c['owner_name'] ?? '—') ?>
                            <?php if (!empty($c['expected_close_date'])): ?> · 📅 <?= format_date($c['expected_close_date'], 'Y-m-d') ?><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($canEdit): ?>
<script>
/* سحب وإفلات الفرص بين المراحل: نقل فوري ثم حفظ على الخادم، ومع أي فشل نعيد
   البطاقة لمكانها ونُعلم المستخدم بدل ترك اللوحة تكذب عليه. */
(function () {
    var board = document.getElementById('crm-board');
    if (!board) { return; }
    var dragged = null, origin = null;

    board.addEventListener('dragstart', function (e) {
        var card = e.target.closest('.crm-card');
        if (!card) { return; }
        dragged = card;
        origin = card.parentNode;
        card.style.opacity = '.5';
    });
    board.addEventListener('dragend', function () {
        if (dragged) { dragged.style.opacity = ''; }
        dragged = null;
    });
    board.addEventListener('dragover', function (e) {
        if (dragged && e.target.closest('.crm-col')) { e.preventDefault(); }
    });
    board.addEventListener('drop', function (e) {
        var col = e.target.closest('.crm-col');
        if (!dragged || !col) { return; }
        e.preventDefault();
        var drop = col.querySelector('.crm-drop');
        var card = dragged, from = origin;
        drop.appendChild(card);
        card.style.opacity = '';

        var body = new URLSearchParams();
        body.set('stage_id', col.getAttribute('data-stage'));
        body.set('_csrf', board.getAttribute('data-csrf'));
        body.set('ajax', '1');
        fetch(board.getAttribute('data-move-url') + '/' + card.getAttribute('data-id') + '/move', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
            body: body.toString(),
            credentials: 'same-origin'
        }).then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
          .then(function () { window.location.reload(); })
          .catch(function () {
              from.appendChild(card);
              alert('تعذّر نقل الفرصة — حدّث الصفحة وحاول مجدداً.');
          });
    });
})();
</script>
<?php endif; ?>

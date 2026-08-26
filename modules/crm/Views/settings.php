<?php $wid = (int) $workspace['id']; ?>
<div class="page-head">
    <div>
        <h1>تصنيفات ووسوم — <?= e($workspace['name']) ?></h1>
        <p>التصنيف يصف دور الجهة في هذه المساحة (منظم فعاليات، راعٍ، مورّد...) والوسم تسمية حرة للفرز السريع.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid . '/pipelines') ?>">🪜 المراحل</a>
        <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid . '/members') ?>">👥 الأعضاء</a>
        <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid . '/logs') ?>">🕓 سجل التغييرات</a>
        <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid) ?>">↩ المساحة</a>
    </div>
</div>

<div class="card">
    <div class="card-title"><span>🏷️ التصنيفات (<?= count($categories) ?>)</span></div>
    <p class="hint" style="margin-top:0;">الجهة الواحدة تحمل أكثر من تصنيف، والتصنيفات تخص هذه المساحة وحدها.</p>
    <div class="table-wrap">
    <table class="table-cards">
        <thead><tr><th>التصنيف</th><th>الاستخدام</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($categories as $c): ?>
            <tr>
                <td>
                    <form method="post" action="<?= route('/crm/w/' . $wid . '/categories/' . $c['id']) ?>" style="display:flex;gap:6px;align-items:center;">
                        <?= csrf_field() ?>
                        <input type="color" name="color" value="<?= e($c['color']) ?>" style="width:44px;height:34px;padding:2px;">
                        <input type="text" name="name" value="<?= e($c['name']) ?>" style="width:180px;">
                        <button class="btn btn-outline btn-sm" type="submit">حفظ</button>
                    </form>
                </td>
                <td><span class="badge badge-muted"><?= (int) $c['uses'] ?> جهة</span></td>
                <td>
                    <form method="post" action="<?= route('/crm/w/' . $wid . '/categories/' . $c['id']) ?>" data-confirm="حذف التصنيف «<?= e($c['name']) ?>»؟ سيُزال عن الجهات التي تحمله.">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete">
                        <button class="btn btn-ghost btn-sm" type="submit">✕</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <form method="post" action="<?= route('/crm/w/' . $wid . '/categories') ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-top:10px;">
        <?= csrf_field() ?>
        <div class="field" style="margin:0;flex:1;min-width:180px;"><label>تصنيف جديد</label><input type="text" name="name" required placeholder="مثال: راعٍ ذهبي"></div>
        <div class="field" style="margin:0;"><label>اللون</label><input type="color" name="color" value="#6b7280" style="width:56px;height:38px;padding:2px;"></div>
        <button class="btn btn-sm" type="submit">إضافة</button>
    </form>
</div>

<div class="card">
    <div class="card-title"><span>#️⃣ الوسوم (<?= count($tags) ?>)</span></div>
    <?php if (!$tags): ?>
        <p class="hint" style="margin-top:0;">لا وسوم بعد — تُضاف من صفحة أي جهة وتظهر هنا تلقائياً.</p>
    <?php endif; ?>
    <div style="display:flex;flex-wrap:wrap;gap:8px;">
        <?php foreach ($tags as $t): ?>
            <span class="badge badge-muted" style="display:inline-flex;align-items:center;gap:6px;">
                #<?= e($t['name']) ?> <span class="hint">(<?= (int) $t['uses'] ?>)</span>
                <form method="post" action="<?= route('/crm/w/' . $wid . '/tags/' . $t['id'] . '/delete') ?>" style="display:inline;" data-confirm="حذف الوسم #<?= e($t['name']) ?> من المساحة؟">
                    <?= csrf_field() ?><button type="submit" style="background:none;border:0;cursor:pointer;color:inherit;">✕</button>
                </form>
            </span>
        <?php endforeach; ?>
    </div>
</div>

<?php
$priorityLabels = ['low' => 'منخفضة', 'medium' => 'متوسطة', 'high' => 'عالية', 'urgent' => 'عاجلة'];
?>
<div class="page-head">
    <div><h1>المهام المتكررة</h1><p>قوالب تُولّد مهمة جديدة تلقائياً كل فترة (يومي/أسبوعي/شهري).</p></div>
    <a class="btn btn-outline" href="<?= route('/tasks') ?>">↩ المهام</a>
</div>

<div class="card">
    <div class="card-title"><span>إضافة مهمة متكررة</span></div>
    <form method="post" action="<?= route('/tasks/recurring') ?>">
        <?= csrf_field() ?>
        <div class="grid-2">
            <div class="field"><label>العنوان</label><input type="text" name="title" required placeholder="مثال: التقرير الأسبوعي"></div>
            <div class="field">
                <label>المسؤول</label>
                <select name="assignee_id" required>
                    <option value="">اختر...</option>
                    <?php foreach ($companyUsers as $u): ?><option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="field"><label>الوصف (اختياري)</label><textarea name="description" style="min-height:60px;"></textarea></div>
        <div class="grid-2">
            <div class="field">
                <label>التكرار</label>
                <select name="frequency">
                    <?php foreach ($frequencyLabels as $k => $lbl): ?><option value="<?= $k ?>" <?= $k === 'weekly' ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>الأولوية</label>
                <select name="priority">
                    <?php foreach ($priorities as $p): ?><option value="<?= $p ?>" <?= $p === 'medium' ? 'selected' : '' ?>><?= e($priorityLabels[$p]) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="grid-2">
            <div class="field"><label>تاريخ أول توليد</label><input type="date" name="next_run" value="<?= date('Y-m-d') ?>"></div>
            <div class="field"><label>مهلة الاستحقاق (أيام بعد التوليد)</label><input type="number" name="due_offset_days" value="0" min="0" max="365"></div>
        </div>
        <button class="btn btn-sm" type="submit">إضافة</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
    <table class="table-cards">
        <thead><tr><th>العنوان</th><th>المسؤول</th><th>التكرار</th><th>التوليد القادم</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        <?php if (!$items): ?><tr><td colspan="6"><div class="empty-state"><div class="ic">🔁</div>لا توجد مهام متكررة</div></td></tr><?php endif; ?>
        <?php foreach ($items as $it): ?>
            <tr<?= $it['is_active'] ? '' : ' style="opacity:.55;"' ?>>
                <td><strong><?= e($it['title']) ?></strong></td>
                <td><?= e($it['assignee_name'] ?? '—') ?></td>
                <td><?= e($frequencyLabels[$it['frequency']] ?? $it['frequency']) ?></td>
                <td><?= e($it['next_run']) ?></td>
                <td><?= $it['is_active'] ? '<span class="badge badge-success">مفعّلة</span>' : '<span class="badge badge-muted">موقوفة</span>' ?></td>
                <td style="display:flex;gap:6px;">
                    <form method="post" action="<?= route('/tasks/recurring/' . $it['id'] . '/toggle') ?>"><?= csrf_field() ?><button class="btn btn-outline btn-sm" type="submit"><?= $it['is_active'] ? 'إيقاف' : 'تفعيل' ?></button></form>
                    <form method="post" action="<?= route('/tasks/recurring/' . $it['id'] . '/delete') ?>" data-confirm="حذف هذه المهمة المتكررة؟"><?= csrf_field() ?><button class="btn btn-outline btn-sm" type="submit">حذف</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

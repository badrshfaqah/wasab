<?php
// خريطة أنواع الكيانات لتسميات مقروءة (تُترجم subject_type التقني)
$typeLabels = [
    'task' => 'مهمة', 'meeting' => 'اجتماع', 'document' => 'مستند', 'archive' => 'أرشيف',
    'employee' => 'موظف', 'checkin' => 'متابعة', 'asset' => 'أصل', 'handover' => 'محضر عهدة',
    'user' => 'مستخدم', 'company' => 'شركة', 'role' => 'دور', 'setting' => 'إعداد',
    'form' => 'نموذج', 'inbox' => 'مراسلة', 'letter' => 'خطاب',
];
$hasFilters = (bool) array_filter($filters, fn ($v) => $v !== null && $v !== '');
$exportQuery = http_build_query($filters);
?>
<div class="page-head">
    <div><h1>سجل العمليات</h1><p>سجل تدقيق لكل العمليات المهمة التي جرت في النظام.</p></div>
    <div style="display:flex;gap:8px;">
        <a class="btn btn-outline btn-sm" href="<?= route('/activity-log/export' . ($exportQuery ? '?' . $exportQuery : '')) ?>">⬇️ تصدير Excel</a>
    </div>
</div>

<div class="card">
    <details class="filters-collapse"<?= $hasFilters ? ' open' : '' ?>>
    <summary>🔍 البحث والتصفية (مستخدم، عملية، نوع، تاريخ)<?= $hasFilters ? ' <span class="badge badge-info">مفعّلة</span>' : '' ?></summary>
    <form method="get" action="<?= route('/activity-log') ?>" class="filters-toolbar" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:16px;">
        <div class="field" style="margin:0;flex:1;min-width:160px;">
            <label>بحث بالوصف</label>
            <input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="اكتب كلمة...">
        </div>
        <?php if ($isSystemAdmin && !empty($options['companies'])): ?>
        <div class="field" style="margin:0;min-width:150px;">
            <label>الشركة</label>
            <select name="company_id">
                <option value="">الكل</option>
                <?php foreach ($options['companies'] as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (int) ($filters['company_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <?php if (!empty($options['users'])): ?>
        <div class="field" style="margin:0;min-width:150px;">
            <label>المستخدم</label>
            <select name="user_id">
                <option value="">الكل</option>
                <?php foreach ($options['users'] as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= (int) ($filters['user_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="field" style="margin:0;min-width:140px;">
            <label>النوع</label>
            <select name="subject_type">
                <option value="">الكل</option>
                <?php foreach ($options['subject_types'] as $st): ?>
                    <option value="<?= e($st) ?>" <?= ($filters['subject_type'] ?? '') === $st ? 'selected' : '' ?>><?= e($typeLabels[$st] ?? $st) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" style="margin:0;min-width:150px;">
            <label>العملية</label>
            <select name="action">
                <option value="">الكل</option>
                <?php foreach ($options['actions'] as $a): ?>
                    <option value="<?= e($a) ?>" <?= ($filters['action'] ?? '') === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" style="margin:0;min-width:130px;">
            <label>من تاريخ</label>
            <input type="date" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>">
        </div>
        <div class="field" style="margin:0;min-width:130px;">
            <label>إلى تاريخ</label>
            <input type="date" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>">
        </div>
        <button class="btn btn-sm" type="submit">بحث</button>
        <?php if ($hasFilters): ?>
            <a class="btn btn-outline btn-sm" href="<?= route('/activity-log') ?>">مسح</a>
        <?php endif; ?>
    </form>
    </details>

    <div class="table-wrap">
    <table>
        <thead><tr><th>المستخدم</th><th>العملية</th><th>النوع</th><th>الوصف</th><th>التاريخ</th></tr></thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="5"><div class="empty-state"><div class="ic">📜</div>لا توجد عمليات مطابقة</div></td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['user_name'] ?? 'النظام') ?></td>
                <td><code><?= e($r['action']) ?></code></td>
                <td><?= $r['subject_type'] ? e($typeLabels[$r['subject_type']] ?? $r['subject_type']) : '—' ?></td>
                <td><?= e($r['description']) ?></td>
                <td style="white-space:nowrap;"><?= format_date($r['created_at'], 'Y-m-d H:i') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?= render_pagination($total, $perPage, $page, route('/activity-log' . ($exportQuery ? '?' . $exportQuery : ''))) ?>
</div>

<div class="page-head">
    <div><h1>سجل العمليات</h1><p>سجل تدقيق لكل العمليات المهمة التي جرت في النظام.</p></div>
</div>

<div class="card">
    <div class="table-wrap">
    <table>
        <thead><tr><th>المستخدم</th><th>العملية</th><th>الوصف</th><th>التاريخ</th></tr></thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="4"><div class="empty-state"><div class="ic">📜</div>لا توجد عمليات مسجلة بعد</div></td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['user_name'] ?? 'النظام') ?></td>
                <td><code><?= e($r['action']) ?></code></td>
                <td><?= e($r['description']) ?></td>
                <td><?= format_date($r['created_at'], 'Y-m-d H:i') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?= render_pagination($total, $perPage, $page, route('/activity-log')) ?>
</div>

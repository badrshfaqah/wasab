<div class="page-head">
    <div><h1>قوالب النماذج</h1><p>القوالب الجاهزة القابلة للتعديل، وحقول الدمج بين الأقواس {} تُملأ عند التوليد.</p></div>
    <div style="display:flex;gap:8px;">
        <a class="btn btn-outline" href="<?= route('/forms') ?>">← النماذج</a>
        <a class="btn" href="<?= route('/forms/templates/create') ?>">+ قالب جديد</a>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
    <table class="table-cards">
        <thead><tr><th>القالب</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        <?php if (!$templates): ?>
            <tr><td colspan="3"><div class="empty-state"><div class="ic">📄</div>لا قوالب بعد</div></td></tr>
        <?php endif; ?>
        <?php foreach ($templates as $t): ?>
            <tr>
                <td><strong><?= e($t['name']) ?></strong></td>
                <td><?= $t['is_active'] ? '<span class="badge badge-success">مفعّل</span>' : '<span class="badge badge-muted">معطّل</span>' ?></td>
                <td style="display:flex;gap:6px;flex-wrap:wrap;">
                    <a class="btn btn-outline btn-sm" href="<?= route('/forms/templates/' . $t['id'] . '/edit') ?>">تعديل</a>
                    <form method="post" action="<?= route('/forms/templates/' . $t['id'] . '/delete') ?>" onsubmit="return confirm('حذف هذا القالب؟');">
                        <?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit">حذف</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

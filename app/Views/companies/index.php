<div class="page-head">
    <div><h1>الشركات</h1><p>إدارة الشركات المسجلة في النظام.</p></div>
    <a class="btn" href="<?= route('/companies/create') ?>">+ إضافة شركة</a>
</div>

<div class="card">
    <div class="table-wrap">
    <table>
        <thead><tr><th>الشعار</th><th>الاسم</th><th>عدد المستخدمين</th><th>الحالة</th><th>تاريخ الإنشاء</th><th></th></tr></thead>
        <tbody>
        <?php if (!$companies): ?>
            <tr><td colspan="6"><div class="empty-state"><div class="ic">🏢</div>لا توجد شركات بعد</div></td></tr>
        <?php endif; ?>
        <?php foreach ($companies as $c): ?>
            <tr>
                <td><span class="color-swatch" style="background:<?= e($c['primary_color']) ?>"></span></td>
                <td><?= e($c['name']) ?></td>
                <td><?= (int) $c['users_count'] ?></td>
                <td><?= status_badge($c['status']) ?></td>
                <td><?= format_date($c['created_at']) ?></td>
                <td style="white-space:nowrap;">
                    <a class="btn btn-outline btn-sm" href="<?= route('/companies/' . $c['id'] . '/edit') ?>">تعديل</a>
                    <form method="post" action="<?= route('/companies/' . $c['id'] . '/delete') ?>" style="display:inline;" data-confirm="هل أنت متأكد من حذف هذه الشركة؟">
                        <?= csrf_field() ?>
                        <button class="btn btn-danger btn-sm" type="submit">حذف</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?= render_pagination($total, $perPage, $page, route('/companies')) ?>
</div>

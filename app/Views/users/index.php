<?php
$typeLabels = ['system_admin' => 'مدير نظام', 'company_admin' => 'مدير شركة', 'user' => 'مستخدم'];
?>
<div class="page-head">
    <div><h1>المستخدمون</h1><p>إدارة حسابات المستخدمين وصلاحياتهم.</p></div>
    <a class="btn" href="<?= route('/users/create') ?>">+ إضافة مستخدم</a>
</div>

<div class="card">
    <div class="table-wrap">
    <table>
        <thead><tr><th>الاسم</th><th>البريد الإلكتروني</th><th>النوع</th><th>الشركة</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        <?php if (!$users): ?>
            <tr><td colspan="6"><div class="empty-state"><div class="ic">👥</div>لا يوجد مستخدمون بعد</div></td></tr>
        <?php endif; ?>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e($u['name']) ?></td>
                <td><?= e($u['email']) ?></td>
                <td><?= e($typeLabels[$u['membership_type']] ?? $u['membership_type']) ?></td>
                <td><?= e($u['company_name'] ?? '-') ?></td>
                <td><?= status_badge($u['status']) ?></td>
                <td style="white-space:nowrap;">
                    <a class="btn btn-outline btn-sm" href="<?= route('/users/' . $u['id'] . '/edit') ?>">تعديل</a>
                    <form method="post" action="<?= route('/users/' . $u['id'] . '/delete') ?>" style="display:inline;" data-confirm="هل تريد حذف هذا المستخدم؟">
                        <?= csrf_field() ?>
                        <button class="btn btn-danger btn-sm" type="submit">حذف</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?= render_pagination($total, $perPage, $page, route('/users')) ?>
</div>

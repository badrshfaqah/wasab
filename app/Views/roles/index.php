<?php use App\Core\Auth; ?>
<div class="page-head">
    <div><h1>الأدوار والصلاحيات</h1><p>أنشئ أدواراً وحدد صلاحياتها لمنحها للمستخدمين العاديين.</p></div>
    <a class="btn" href="<?= route('/roles/create') ?>">+ إضافة دور</a>
</div>

<div class="card">
    <div class="table-wrap">
    <table>
        <thead><tr><th>اسم الدور</th><?php if (Auth::isSystemAdmin()): ?><th>الشركة</th><?php endif; ?><th>عدد المستخدمين</th><th></th></tr></thead>
        <tbody>
        <?php if (!$roles): ?>
            <tr><td colspan="4"><div class="empty-state"><div class="ic">🛡️</div>لا توجد أدوار بعد</div></td></tr>
        <?php endif; ?>
        <?php foreach ($roles as $r): ?>
            <tr>
                <td><?= e($r['name']) ?></td>
                <?php if (Auth::isSystemAdmin()): ?><td><?= e($r['company_name'] ?? '-') ?></td><?php endif; ?>
                <td><?= (int) $r['users_count'] ?></td>
                <td style="white-space:nowrap;">
                    <a class="btn btn-outline btn-sm" href="<?= route('/roles/' . $r['id'] . '/edit') ?>">تعديل</a>
                    <form method="post" action="<?= route('/roles/' . $r['id'] . '/delete') ?>" style="display:inline;" data-confirm="هل تريد حذف هذا الدور؟">
                        <?= csrf_field() ?>
                        <button class="btn btn-danger btn-sm" type="submit">حذف</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

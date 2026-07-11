<?php use Modules\Archive\Models\ArchiveFile; ?>
<div class="page-head">
    <div><h1>سلة المحذوفات</h1><p>الملفات المحذوفة تبقى هنا حتى تُستعاد أو تُحذف نهائياً.</p></div>
    <a class="btn btn-outline" href="<?= route('/archive') ?>">↩ رجوع للملفات</a>
</div>

<div class="card">
    <div class="table-wrap">
    <table>
        <thead><tr><th></th><th>الاسم</th><th>التصنيف</th><th>حذفه</th><th>تاريخ الحذف</th><th></th></tr></thead>
        <tbody>
        <?php if (!$files): ?>
            <tr><td colspan="6"><div class="empty-state"><div class="ic">🗑️</div>سلة المحذوفات فارغة</div></td></tr>
        <?php endif; ?>
        <?php foreach ($files as $f): ?>
            <tr>
                <td><?= ArchiveFile::icon($f['extension']) ?></td>
                <td><?= e($f['title'] ?: $f['original_name']) ?></td>
                <td><?= e($f['category_name']) ?></td>
                <td><?= e($f['deleter_name'] ?? '-') ?></td>
                <td><?= format_date($f['updated_at'] ?? $f['created_at'], 'Y-m-d H:i') ?></td>
                <td style="display:flex;gap:6px;justify-content:flex-end;">
                    <form method="post" action="<?= route('/archive/' . $f['id'] . '/restore') ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline btn-sm" type="submit">استعادة</button>
                    </form>
                    <form method="post" action="<?= route('/archive/' . $f['id'] . '/permanent-delete') ?>" data-confirm="سيتم حذف الملف نهائياً بلا إمكانية استعادة. متابعة؟">
                        <?= csrf_field() ?>
                        <button class="btn btn-danger btn-sm" type="submit">حذف نهائي</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

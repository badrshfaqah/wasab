<?php use Modules\Archive\Models\ArchiveFile; ?>
<div class="page-head">
    <div><h1>تصنيفات الأرشيف</h1><p>تصنيفات رئيسية غير محدودة، وتصنيفات فرعية داخل كل واحدة.</p></div>
    <div>
        <a class="btn btn-outline" href="<?= route('/archive') ?>">↩ رجوع للملفات</a>
        <?php if ($canCreate): ?>
            <a class="btn" href="<?= route('/archive/categories/create') ?>">+ تصنيف جديد</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
    <table>
        <thead><tr><th>الاسم</th><th>من يراه</th><th></th></tr></thead>
        <tbody>
        <?php if (!$categories): ?>
            <tr><td colspan="3"><div class="empty-state"><div class="ic">🗂️</div>لا توجد تصنيفات بعد</div></td></tr>
        <?php endif; ?>
        <?php foreach ($categories as $c): ?>
            <tr>
                <td style="padding-right:<?= 16 + $c['depth'] * 24 ?>px;">
                    <?= $c['depth'] > 0 ? '└ ' : '' ?><?= e($c['name']) ?>
                </td>
                <td><?= e(ArchiveFile::visibilityLabels()[$c['visibility_type']] ?? $c['visibility_type']) ?></td>
                <td style="display:flex;gap:8px;justify-content:flex-end;">
                    <?php if ($canCreate): ?>
                        <a class="btn btn-outline btn-sm" href="<?= route('/archive/categories/create?parent_id=' . $c['id']) ?>">+ فرعي</a>
                    <?php endif; ?>
                    <?php if ($canEdit): ?>
                        <a class="btn btn-outline btn-sm" href="<?= route('/archive/categories/' . $c['id'] . '/edit') ?>">تعديل</a>
                    <?php endif; ?>
                    <?php if ($canDelete): ?>
                        <form method="post" action="<?= route('/archive/categories/' . $c['id'] . '/delete') ?>" data-confirm="سيتم حذف التصنيف إن كان فارغاً من ملفات وتصنيفات فرعية. متابعة؟">
                            <?= csrf_field() ?>
                            <button class="btn btn-danger btn-sm" type="submit">حذف</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

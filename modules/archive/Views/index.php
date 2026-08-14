<?php
use Modules\Archive\Models\ArchiveFile;

$statusLabels = ArchiveFile::statusLabels();

$filesQuery = function (array $filters, array $overrides = []) {
    $params = array_merge($filters, $overrides);
    $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
    return route('/archive?' . http_build_query($params));
};

$formatSize = function (int $bytes) {
    if ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024), 1) . ' م.ب';
    }
    return round($bytes / 1024) . ' ك.ب';
};
// رأس عمود قابل للفرز (قائمة العرض): ينقر ليعكس الاتجاه بين تصاعدي/تنازلي
$curSort = $filters['sort'] ?? 'date_desc';
$sortHead = function (string $ascKey, string $descKey, string $label) use ($curSort, $filters, $view, $filesQuery): string {
    $isActive = in_array($curSort, [$ascKey, $descKey], true);
    $nextKey = ($curSort === $ascKey) ? $descKey : $ascKey; // إن كنا تصاعدياً انتقل لتنازلي
    $arrow = $isActive ? ($curSort === $ascKey ? ' ▲' : ' ▼') : '';
    $url = $filesQuery($filters, ['sort' => $nextKey, 'view' => $view, 'page' => null]);
    return '<a href="' . $url . '" style="color:inherit;white-space:nowrap;">' . e($label) . $arrow . '</a>';
};
?>
<div class="page-head">
    <div><h1>أرشيف الملفات</h1><p>الوثائق والملفات الرسمية للشركة، منظّمة بتصنيفات.</p></div>
    <div style="display:flex;gap:8px;">
        <?php if ($canManage): ?>
            <a class="btn btn-outline" href="<?= route('/archive/categories') ?>">🗂️ التصنيفات</a>
            <a class="btn btn-outline" href="<?= route('/archive/trash') ?>">🗑️ سلة المحذوفات</a>
            <a class="btn btn-outline" href="<?= route('/archive/settings') ?>">⚙️ الإعدادات</a>
        <?php endif; ?>
        <?php if ($canCreate): ?>
            <a class="btn" href="<?= route('/archive/upload') ?>">+ رفع ملف</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <form method="get" action="<?= route('/archive') ?>" class="filters-toolbar" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:16px;">
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <div class="field" style="margin:0;flex:1;min-width:200px;">
            <label>بحث بالاسم أو الوصف أو الكلمات المفتاحية</label>
            <input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="اكتب كلمة للبحث...">
        </div>
        <div class="field" style="margin:0;min-width:180px;">
            <label>التصنيف</label>
            <select name="category_id">
                <option value="">الكل</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (int) ($filters['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= str_repeat('— ', $c['depth']) . e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" style="margin:0;min-width:150px;">
            <label>الوسم</label>
            <select name="tag_id">
                <option value="">الكل</option>
                <?php foreach ($tags as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= (int) ($filters['tag_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" style="margin:0;min-width:150px;">
            <label>الحالة</label>
            <select name="status">
                <option value="">الكل</option>
                <?php foreach ($statusLabels as $key => $label): ?>
                    <option value="<?= $key ?>" <?= ($filters['status'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" style="margin:0;min-width:150px;">
            <label>الترتيب</label>
            <select name="sort">
                <option value="date_desc" <?= ($filters['sort'] ?? '') === 'date_desc' ? 'selected' : '' ?>>الأحدث رفعاً</option>
                <option value="date_asc" <?= ($filters['sort'] ?? '') === 'date_asc' ? 'selected' : '' ?>>الأقدم رفعاً</option>
                <option value="modified_desc" <?= ($filters['sort'] ?? '') === 'modified_desc' ? 'selected' : '' ?>>آخر تعديل</option>
                <option value="name_asc" <?= ($filters['sort'] ?? '') === 'name_asc' ? 'selected' : '' ?>>الاسم (أ-ي)</option>
                <option value="name_desc" <?= ($filters['sort'] ?? '') === 'name_desc' ? 'selected' : '' ?>>الاسم (ي-أ)</option>
                <option value="size_desc" <?= ($filters['sort'] ?? '') === 'size_desc' ? 'selected' : '' ?>>الأكبر حجماً</option>
                <option value="size_asc" <?= ($filters['sort'] ?? '') === 'size_asc' ? 'selected' : '' ?>>الأصغر حجماً</option>
            </select>
        </div>
        <button class="btn btn-sm" type="submit">بحث</button>
        <?php if (array_diff_key($filters, ['sort' => 1])): ?>
            <a class="btn btn-outline btn-sm" href="<?= $filesQuery(['sort' => $filters['sort'] ?? null], ['view' => $view]) ?>">مسح الفلاتر</a>
        <?php endif; ?>
        <div class="view-toggle" style="margin-inline-start:auto;display:flex;gap:6px;">
            <a class="btn btn-sm <?= $view === 'list' ? '' : 'btn-outline' ?>" href="<?= $filesQuery($filters, ['view' => 'list']) ?>">☰ قائمة</a>
            <a class="btn btn-sm <?= $view === 'grid' ? '' : 'btn-outline' ?>" href="<?= $filesQuery($filters, ['view' => 'grid']) ?>">▦ بطاقات</a>
        </div>
    </form>

    <?php if (!$files): ?>
        <div class="empty-state"><div class="ic">🗂️</div>لا توجد ملفات مطابقة</div>
    <?php elseif ($view === 'grid'): ?>
        <div class="cards-row" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));">
            <?php foreach ($files as $f): ?>
                <a class="card archive-card" href="<?= route('/archive/' . $f['id']) ?>" style="margin-bottom:0;color:inherit;text-decoration:none;">
                    <?php if (ArchiveFile::isImage($f['extension'])): ?>
                        <div class="archive-thumb" style="background-image:url('<?= e(route('/archive/' . $f['id'] . '/preview')) ?>');"></div>
                    <?php else: ?>
                        <div class="archive-thumb archive-thumb-icon"><?= ArchiveFile::icon($f['extension']) ?></div>
                    <?php endif; ?>
                    <div class="archive-card-name" title="<?= e($f['title'] ?: $f['original_name']) ?>"><?= e($f['title'] ?: $f['original_name']) ?></div>
                    <div class="hint"><?= e($f['category_name']) ?> · <?= $formatSize((int) $f['size']) ?></div>
                    <?= status_badge($f['status']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
        <table class="table-cards">
            <thead><tr>
                <th></th>
                <th><?= $sortHead('name_asc', 'name_desc', 'الاسم') ?></th>
                <th><?= $sortHead('cat_asc', 'cat_desc', 'التصنيف') ?></th>
                <th><?= $sortHead('size_asc', 'size_desc', 'الحجم') ?></th>
                <th><?= $sortHead('status_asc', 'status_desc', 'الحالة') ?></th>
                <th><?= $sortHead('uploader_asc', 'uploader_desc', 'رافع الملف') ?></th>
                <th><?= $sortHead('date_asc', 'date_desc', 'تاريخ الرفع') ?></th>
                <th style="text-align:end;">إجراءات</th>
            </tr></thead>
            <tbody>
            <?php foreach ($files as $f): ?>
                <tr>
                    <td><?= ArchiveFile::icon($f['extension']) ?></td>
                    <td><a href="<?= route('/archive/' . $f['id']) ?>"><?= e($f['title'] ?: $f['original_name']) ?></a></td>
                    <td><?= e($f['category_name']) ?></td>
                    <td><?= $formatSize((int) $f['size']) ?></td>
                    <td><?= status_badge($f['status']) ?></td>
                    <td><?= e($f['uploader_name'] ?? '-') ?></td>
                    <td><?= format_date($f['created_at'], 'Y-m-d') ?></td>
                    <td style="text-align:end;white-space:nowrap;">
                        <?php if ($canDownload): ?>
                            <a class="btn btn-ghost btn-sm" href="<?= route('/archive/' . $f['id'] . '/download') ?>" title="تنزيل">⬇️</a>
                        <?php endif; ?>
                        <?php if ($canShare): ?>
                            <form method="post" action="<?= route('/archive/' . $f['id'] . '/share') ?>" style="display:inline;" title="إنشاء رابط مشاركة (٧ أيام)">
                                <?= csrf_field() ?>
                                <button class="btn btn-ghost btn-sm" type="submit">🔗</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
    <?= render_pagination($total, $perPage, $page, $filesQuery($filters, ['view' => $view])) ?>
</div>

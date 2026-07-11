<?php
use Modules\Archive\Models\ArchiveFile;

$daysLeft = $file['expires_at'] ? (int) ((strtotime($file['expires_at']) - strtotime(date('Y-m-d'))) / 86400) : null;
$expiringSoon = $file['status'] === 'active' && $daysLeft !== null && $daysLeft <= $expiryWarningDays;

$formatSize = function (int $bytes) {
    if ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024), 1) . ' م.ب';
    }
    return round($bytes / 1024) . ' ك.ب';
};

$logLabels = [
    'uploaded' => 'رفع الملف', 'updated' => 'تعديل بيانات الملف', 'viewed' => 'مشاهدة الملف',
    'downloaded' => 'تحميل الملف', 'moved' => 'نقل الملف', 'category_changed' => 'تغيير التصنيف',
    'expiry_changed' => 'تغيير تاريخ الانتهاء', 'version_replaced' => 'رفع إصدار جديد',
    'closed' => 'إغلاق الملف', 'archived' => 'أرشفة الملف', 'trashed' => 'نقل لسلة المحذوفات',
    'restored' => 'استعادة من سلة المحذوفات', 'shared' => 'إنشاء رابط مشاركة', 'share_revoked' => 'إلغاء رابط مشاركة',
];
?>
<div class="page-head">
    <div>
        <h1><?= e($file['title'] ?: $file['original_name']) ?></h1>
        <p><?= ArchiveFile::icon($file['extension']) ?> <?= e($file['original_name']) ?> · الإصدار <?= (int) $file['version'] ?> · <?= status_badge($file['status']) ?></p>
        <?php if ($tags): ?>
            <p>
                <?php foreach ($tags as $t): ?><span class="badge badge-muted" style="margin-left:4px;">🏷️ <?= e($t['name']) ?></span><?php endforeach; ?>
            </p>
        <?php endif; ?>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php if ($canDownload): ?>
            <a class="btn btn-outline" href="<?= route('/archive/' . $file['id'] . '/download') ?>">⬇ تحميل</a>
        <?php endif; ?>
        <?php if ($canEdit): ?>
            <a class="btn btn-outline" href="<?= route('/archive/' . $file['id'] . '/edit') ?>">تعديل البيانات</a>
        <?php endif; ?>
        <?php if ($canDelete): ?>
            <form method="post" action="<?= route('/archive/' . $file['id'] . '/delete') ?>" data-confirm="سيتم حذف الملف نهائياً بكل إصداراته. متابعة؟">
                <?= csrf_field() ?>
                <button class="btn btn-danger" type="submit">حذف</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($expiringSoon): ?>
    <div class="alert alert-danger">
        <span>⏰ <?= $daysLeft >= 0 ? "هذا الملف تنتهي صلاحيته خلال {$daysLeft} يوم." : 'انتهت صلاحية هذا الملف.' ?></span>
    </div>
<?php endif; ?>

<div class="grid-2" style="align-items:start;">
    <div class="card">
        <div class="card-title"><span>المعاينة</span></div>
        <?php if (ArchiveFile::isPdf($file['extension'])): ?>
            <iframe src="<?= route('/archive/' . $file['id'] . '/preview') ?>" style="width:100%;height:520px;border:1px solid var(--border);border-radius:8px;"></iframe>
        <?php elseif (ArchiveFile::isImage($file['extension'])): ?>
            <img src="<?= route('/archive/' . $file['id'] . '/preview') ?>" alt="" style="max-width:100%;border-radius:8px;border:1px solid var(--border);">
        <?php else: ?>
            <div class="empty-state"><div class="ic"><?= ArchiveFile::icon($file['extension']) ?></div>لا تتوفر معاينة لهذا النوع، حمّل الملف لعرضه.</div>
        <?php endif; ?>

        <div class="card-title" style="margin-top:20px;"><span>بيانات الملف</span></div>
        <table>
            <tr><td class="hint">التصنيف</td><td><?= e($category['name']) ?></td></tr>
            <tr><td class="hint">الحجم</td><td><?= $formatSize((int) $file['size']) ?></td></tr>
            <tr><td class="hint">الوصف</td><td><?= e($file['description'] ?: '—') ?></td></tr>
            <tr><td class="hint">الكلمات المفتاحية</td><td><?= e($file['keywords'] ?: '—') ?></td></tr>
            <tr><td class="hint">ملاحظات</td><td><?= e($file['notes'] ?: '—') ?></td></tr>
            <tr><td class="hint">تاريخ انتهاء الصلاحية</td><td><?= $file['expires_at'] ? format_date($file['expires_at']) : '—' ?></td></tr>
            <tr><td class="hint">رافع الملف</td><td><?= e($file['uploader_name'] ?? '-') ?></td></tr>
            <tr><td class="hint">تاريخ الإنشاء</td><td><?= format_date($file['created_at'], 'Y-m-d H:i') ?></td></tr>
            <tr><td class="hint">آخر تعديل</td><td><?= $file['updated_at'] ? format_date($file['updated_at'], 'Y-m-d H:i') : '—' ?></td></tr>
            <tr><td class="hint">عدد المشاهدات</td><td><?= (int) $file['view_count'] ?></td></tr>
            <tr><td class="hint">عدد التحميلات</td><td><?= (int) $file['download_count'] ?></td></tr>
        </table>

        <?php if ($canEdit): ?>
            <div class="card-title" style="margin-top:20px;"><span>إجراءات إضافية</span></div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <form method="post" action="<?= route('/archive/' . $file['id'] . '/replace') ?>" enctype="multipart/form-data" style="display:flex;gap:6px;align-items:center;">
                    <?= csrf_field() ?>
                    <input type="file" name="file" required style="width:auto;">
                    <button class="btn btn-outline btn-sm" type="submit">استبدال الملف (إصدار جديد)</button>
                </form>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;">
                <form method="post" action="<?= route('/archive/' . $file['id'] . '/move') ?>" style="display:flex;gap:6px;align-items:center;">
                    <?= csrf_field() ?>
                    <select name="category_id" style="width:auto;">
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (int) $file['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= str_repeat('— ', $c['depth']) . e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline btn-sm" type="submit">نقل إلى تصنيف آخر</button>
                </form>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;">
                <?php if ($file['status'] !== 'archived'): ?>
                <form method="post" action="<?= route('/archive/' . $file['id'] . '/status') ?>" style="display:flex;gap:6px;align-items:center;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="renew">
                    <input type="date" name="expires_at" value="<?= e($file['expires_at'] ?? '') ?>" style="width:auto;">
                    <button class="btn btn-outline btn-sm" type="submit">تجديد تاريخ الصلاحية</button>
                </form>
                <?php endif; ?>
                <?php if ($file['status'] !== 'closed'): ?>
                <form method="post" action="<?= route('/archive/' . $file['id'] . '/status') ?>" data-confirm="إغلاق هذا الملف؟">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="close">
                    <button class="btn btn-outline btn-sm" type="submit">إغلاق الملف</button>
                </form>
                <?php endif; ?>
                <?php if ($file['status'] !== 'archived'): ?>
                <form method="post" action="<?= route('/archive/' . $file['id'] . '/status') ?>" data-confirm="أرشفة هذا الملف؟">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="archive">
                    <button class="btn btn-outline btn-sm" type="submit">أرشفة</button>
                </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($canShare): ?>
            <div class="card-title" style="margin-top:20px;"><span>مشاركة برابط مؤقت</span></div>
            <form method="post" action="<?= route('/archive/' . $file['id'] . '/share') ?>" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
                <?= csrf_field() ?>
                <label class="hint" style="margin:0;">ينتهي خلال (أيام)</label>
                <input type="number" name="expires_in_days" min="1" max="90" value="7" style="width:80px;">
                <label class="hint" style="margin:0;">حد التحميلات (اختياري)</label>
                <input type="number" name="max_downloads" min="1" style="width:80px;">
                <button class="btn btn-outline btn-sm" type="submit">إنشاء رابط مشاركة</button>
            </form>
            <?php if ($shares): ?>
                <div style="margin-top:10px;">
                <?php foreach ($shares as $s): ?>
                    <?php $usable = \Modules\Archive\Models\ArchiveFileShare::isUsable($s); ?>
                    <div class="doc-log">
                        <div style="word-break:break-all;">
                            <?php if ($usable): ?>
                                <a href="<?= e(base_url('archive/share/' . $s['token'])) ?>" target="_blank"><?= e(base_url('archive/share/' . $s['token'])) ?></a>
                            <?php else: ?>
                                <span class="hint">(منتهي/ملغى) <?= e(base_url('archive/share/' . $s['token'])) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="doc-log-meta" style="display:flex;justify-content:space-between;align-items:center;">
                            <span>
                                ينتهي: <?= format_date($s['expires_at'], 'Y-m-d H:i') ?>
                                · تحميلات: <?= (int) $s['download_count'] ?><?= $s['max_downloads'] ? '/' . (int) $s['max_downloads'] : '' ?>
                            </span>
                            <?php if ($usable): ?>
                                <form method="post" action="<?= route('/archive/' . $file['id'] . '/share/' . $s['id'] . '/revoke') ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-danger btn-sm" type="submit">إلغاء</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div>
        <div class="card">
            <div class="card-title"><span>سجل العمليات</span></div>
            <?php if (!$logs): ?><p class="hint">لا يوجد سجل بعد.</p><?php endif; ?>
            <?php foreach ($logs as $log): ?>
                <div class="doc-log">
                    <div><?= e($logLabels[$log['action']] ?? $log['description']) ?></div>
                    <div class="doc-log-meta"><?= e($log['user_name'] ?? 'النظام') ?> · <?= format_date($log['created_at'], 'Y-m-d H:i') ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <div class="card-title"><span>سجل التحميل</span></div>
            <?php if (!$downloads): ?><p class="hint">لم يُحمَّل الملف بعد.</p><?php endif; ?>
            <?php foreach ($downloads as $d): ?>
                <div class="doc-log">
                    <div><?= e($d['user_name'] ?? 'مستخدم محذوف') ?></div>
                    <div class="doc-log-meta"><?= format_date($d['created_at'], 'Y-m-d H:i') ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (count($versions) > 0): ?>
        <div class="card">
            <div class="card-title"><span>الإصدارات السابقة</span></div>
            <?php foreach ($versions as $v): ?>
                <div class="doc-log">
                    <div>الإصدار <?= (int) $v['version'] ?> - <?= e($v['original_name']) ?> (<?= $formatSize((int) $v['size']) ?>)</div>
                    <div class="doc-log-meta"><?= e($v['user_name'] ?? '-') ?> · <?= format_date($v['created_at'], 'Y-m-d H:i') ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

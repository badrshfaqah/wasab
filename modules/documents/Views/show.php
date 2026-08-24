<?php
use Modules\Documents\Models\Document;

$typeLabels = Document::typeLabels();
$isOwner = !empty($isOwner);
// الإصدار الرسمي (توقيع + رقم تسلسلي) متاح للمالك والمدير وصاحب صلاحية التوقيع
$canSignNow = in_array($document['status'], ['draft', 'pending_approval', 'approved'], true)
    && ($isOwner || $canManage || $canSign);
$canShare = $isOwner || $canManage;
$canArchive = $document['status'] !== 'archived' && ($canManage || $isOwner);
$canRestore = $document['status'] === 'archived' && $canManage;
?>
<div class="page-head">
    <div>
        <h1><?= e($document['title']) ?></h1>
        <p>
            <?= e($typeLabels[$document['type']] ?? $document['type']) ?>
            <?php if ($document['number']): ?> · رقم: <strong><?= e($document['number']) ?></strong><?php endif; ?>
            · <?= status_badge($document['status']) ?>
            <?php
            $confLabels = Document::confidentialityLabels();
            $conf = $document['confidentiality'] ?? 'normal';
            if ($conf !== 'normal'):
                $confColor = $conf === 'secret' ? 'danger' : ($conf === 'confidential' ? 'warning' : 'info');
            ?>
                · <span class="badge badge-<?= $confColor ?>">🔒 <?= e($confLabels[$conf]) ?></span>
            <?php endif; ?>
            <?php if (!empty($document['follow_up_date'])): ?> · 📅 متابعة: <?= format_date($document['follow_up_date']) ?><?php endif; ?>
            <?php if (!empty($document['expiry_date'])): ?>
                <?php $expired = $document['expiry_date'] < date('Y-m-d'); ?>
                · <span class="badge badge-<?= $expired ? 'danger' : 'muted' ?>"><?= $expired ? '⛔ انتهت الصلاحية' : '⏳ تنتهي' ?> <?= format_date($document['expiry_date']) ?></span>
            <?php endif; ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn" href="#doc-pdf-preview">👁️ عرض PDF</a>
        <a class="btn btn-outline" href="<?= route('/documents/' . $document['id'] . '/print') ?>" target="_blank">🖨️ طباعة / حفظ PDF</a>
        <?php if (!empty($canDuplicate)): ?>
        <form method="post" action="<?= route('/documents/' . $document['id'] . '/duplicate') ?>" onsubmit="return confirm('نسخ هذا المستند كمسودة جديدة؟ عند إصدارها رسمياً تأخذ رقماً جديداً.');">
            <?= csrf_field() ?><button class="btn btn-outline" type="submit">📋 نسخ كمسودة</button>
        </form>
        <?php endif; ?>
        <?php if ($canEdit): ?>
            <a class="btn btn-outline" href="<?= route('/documents/' . $document['id'] . '/edit') ?>">تعديل</a>
        <?php endif; ?>
        <?php if ($canDelete): ?>
            <form method="post" action="<?= route('/documents/' . $document['id']) ?>" data-confirm="سيتم حذف المستند نهائياً. متابعة؟">
                <?= csrf_field() ?>
                <button class="btn btn-danger" type="submit">حذف</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($document['verify_token'])): ?>
    <?php $verifyUrl = base_url('documents/verify/' . $document['verify_token']); ?>
    <div class="card" style="border-inline-start:4px solid var(--success);">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span>✅ رابط التحقق العام من صحة هذا المستند:</span>
            <a href="<?= e($verifyUrl) ?>" target="_blank" rel="noopener" style="word-break:break-all;"><?= e($verifyUrl) ?></a>
            <button type="button" class="btn btn-outline btn-sm" data-copy="<?= e($verifyUrl) ?>">نسخ</button>
        </div>
        <p class="hint" style="margin:6px 0 0;">يمكن لأي شخص فتح هذا الرابط للتأكد من أصالة المستند دون الاطلاع على محتواه. يظهر أيضاً على النسخة المطبوعة.</p>
    </div>
<?php endif; ?>

<!-- معاينة PDF داخل الموقع: الورقة النهائية (بالقالب والتوقيع والختم وQR) دون مغادرة الصفحة -->
<div class="card" id="doc-pdf-preview">
    <div class="card-title divided">
        <span>📄 معاينة المستند (PDF)</span>
        <a class="btn btn-ghost btn-sm" href="<?= route('/documents/' . $document['id'] . '/print') ?>" target="_blank" rel="noopener">⛶ ملء الشاشة</a>
    </div>
    <iframe src="<?= route('/documents/' . $document['id'] . '/print?embed=1') ?>"
            style="width:100%;height:75vh;min-height:420px;border:1px solid var(--border);border-radius:10px;background:#9ca3af;"
            loading="lazy" title="معاينة المستند"></iframe>
    <p class="hint" style="margin:8px 0 0;">هذه هي الورقة النهائية كما ستُطبع — بالقالب والتوقيع والختم ورمز التحقق. للحفظ كملف PDF استخدم «🖨️ طباعة / حفظ PDF».</p>
</div>

<div class="grid-2" style="align-items:start;">
    <div class="card">
        <div class="card-title"><span>محتوى المستند</span></div>
        <?php if ($document['content']): ?>
            <div class="doc-content-preview"><?= $document['content'] ?></div>
        <?php else: ?>
            <p class="hint">لا يوجد محتوى.</p>
        <?php endif; ?>

        <?php if ($canSignNow || $canArchive || $canRestore): ?>
            <div class="form-actions" style="margin-top:20px;">
                <?php if ($canSignNow): ?>
                    <form method="post" action="<?= route('/documents/' . $document['id'] . '/status') ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;" onsubmit="return confirm('إصدار المستند رسمياً؟ سيُوقَّع ويأخذ رقماً تسلسلياً ويُقفل عن التعديل.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="sign">
                        <?php if (!empty($mySignatures)): ?>
                            <select name="signature_id" style="width:auto;min-width:150px;">
                                <?php foreach ($mySignatures as $sig): ?>
                                    <option value="<?= $sig['id'] ?>"><?= e($sig['name']) ?><?= !empty($sig['owner_name']) ? ' (مشاركة من ' . e($sig['owner_name']) . ')' : '' ?></option>
                                <?php endforeach; ?>
                                <option value="">بلا صورة توقيع</option>
                            </select>
                        <?php endif; ?>
                        <button class="btn" type="submit">🔏 إصدار رسمي (توقيع)</button>
                    </form>
                    <?php if (empty($mySignatures)): ?>
                        <span class="hint">لإظهار صورة توقيعك، أضِفه من <a href="<?= route('/profile') ?>">ملفك الشخصي</a>.</span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($canArchive): ?>
                    <form method="post" action="<?= route('/documents/' . $document['id'] . '/status') ?>" data-confirm="أرشفة هذا المستند؟">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="archive">
                        <button class="btn btn-outline" type="submit">🗄️ أرشفة</button>
                    </form>
                <?php endif; ?>
                <?php if ($canRestore): ?>
                    <form method="post" action="<?= route('/documents/' . $document['id'] . '/status') ?>" data-confirm="استعادة هذا المستند من الأرشيف؟">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="restore">
                        <button class="btn btn-outline" type="submit">↩️ استعادة من الأرشيف</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($versions)): ?>
    <div class="card">
        <div class="card-title"><span>سجل الإصدارات <span class="badge badge-info"><?= count($versions) ?></span></span></div>
        <p class="hint" style="margin-top:0;">تُحفظ لقطة من المحتوى قبل كل تعديل. يمكنك عرض أي إصدار سابق أو استعادته.</p>
        <?php foreach ($versions as $v): ?>
            <div class="doc-log" style="align-items:center;">
                <div>
                    <strong>إصدار #<?= (int) $v['version_no'] ?></strong> — <?= e($v['title']) ?>
                    <div class="doc-log-meta"><?= e($v['saved_by_name'] ?? 'النظام') ?> · <?= format_date($v['created_at'], 'Y-m-d H:i') ?></div>
                </div>
                <div style="display:flex;gap:6px;align-items:center;">
                    <a class="btn btn-outline btn-sm" href="<?= route('/documents/' . $document['id'] . '/versions/' . $v['id']) ?>">عرض</a>
                    <a class="btn btn-outline btn-sm" href="<?= route('/documents/' . $document['id'] . '/versions/' . $v['id'] . '/diff') ?>" title="ماذا تغيّر في هذا الحفظ؟">± التغييرات</a>
                    <?php if ($canEdit): ?>
                    <form method="post" action="<?= route('/documents/' . $document['id'] . '/versions/' . $v['id'] . '/restore') ?>" data-confirm="استعادة هذا الإصدار؟ سيُحفظ المحتوى الحالي كإصدار قبل الاستبدال.">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline btn-sm">استعادة</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-title"><span>🤝 المشاركة (<?= count($shares ?? []) ?>)</span></div>
        <?php if (!empty($myShareRole) && !$isOwner): ?>
            <p class="hint" style="margin-top:0;">هذا المستند مُشارك معك بدور <strong><?= $myShareRole === 'editor' ? 'مشاهدة وتعديل' : 'مشاهدة فقط' ?></strong>.</p>
        <?php endif; ?>
        <?php if (empty($shares) && $canShare): ?>
            <p class="hint" style="margin-top:0;">لم يُشارك المستند مع أحد بعد — أضف زميلاً وحدد دوره.</p>
        <?php endif; ?>
        <?php foreach (($shares ?? []) as $s): ?>
            <div class="doc-log" style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                <div>
                    👤 <?= e($s['user_name']) ?>
                    <span class="badge badge-<?= $s['role'] === 'editor' ? 'info' : 'muted' ?>"><?= $s['role'] === 'editor' ? '✏️ مشاهدة وتعديل' : '👁️ مشاهدة' ?></span>
                </div>
                <?php if ($canShare): ?>
                <form method="post" action="<?= route('/documents/' . $document['id'] . '/share/' . $s['user_id'] . '/unshare') ?>" data-confirm="إلغاء مشاركة <?= e($s['user_name']) ?>؟">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost btn-sm" type="submit">✕</button>
                </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if ($canShare): ?>
            <form method="post" action="<?= route('/documents/' . $document['id'] . '/share') ?>" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <?= csrf_field() ?>
                <select name="user_id" required style="flex:1;min-width:150px;">
                    <option value="">اختر موظفاً...</option>
                    <?php foreach (($companyUsers ?? []) as $u): ?>
                        <?php if ((int) $u['id'] === (int) $document['created_by']) continue; ?>
                        <option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="role" style="width:auto;">
                    <option value="viewer">👁️ مشاهدة فقط</option>
                    <option value="editor">✏️ مشاهدة وتعديل</option>
                </select>
                <button class="btn btn-sm" type="submit">مشاركة</button>
            </form>
            <p class="hint" style="margin:8px 0 0;">من له دور «مشاهدة وتعديل» يكتب معك في المستند، ويظهر كل تعديل في سجل الإصدارات مع اسم صاحبه.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title"><span>💬 التعليقات (<?= count($comments ?? []) ?>)</span></div>
        <?php foreach (($comments ?? []) as $c): ?>
            <div class="doc-log">
                <div style="white-space:pre-wrap;"><?= e($c['body']) ?></div>
                <div class="doc-log-meta"><?= e($c['user_name'] ?? '') ?> · <?= format_date($c['created_at'], 'Y-m-d H:i') ?></div>
            </div>
        <?php endforeach; ?>
        <form method="post" action="<?= route('/documents/' . $document['id'] . '/comments') ?>" style="margin-top:10px;display:flex;gap:8px;align-items:flex-start;">
            <?= csrf_field() ?>
            <textarea name="body" rows="2" required placeholder="اكتب تعليقاً أو ملاحظة مراجعة..." style="flex:1;"></textarea>
            <button class="btn btn-sm" type="submit">إرسال</button>
        </form>
    </div>

    <div class="card">
        <div class="card-title"><span>سجل العمليات</span></div>
        <?php if (!$logs): ?>
            <p class="hint">لا يوجد سجل بعد.</p>
        <?php endif; ?>
        <?php foreach ($logs as $log): ?>
            <div class="doc-log">
                <div><?= e($log['description']) ?></div>
                <div class="doc-log-meta"><?= e($log['user_name'] ?? 'النظام') ?> · <?= format_date($log['created_at'], 'Y-m-d H:i') ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

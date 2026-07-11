<?php
use Modules\Meetings\Models\Meeting;

$typeLabels = Meeting::typeLabels();
$recurrenceLabels = Meeting::recurrenceLabels();
$beforeNotes = array_filter($notes, fn ($n) => $n['phase'] === 'before');
$afterNotes = array_filter($notes, fn ($n) => $n['phase'] === 'after');
$isRecurring = ($meeting['recurrence_rule'] ?? 'none') !== 'none';
?>
<div class="page-head">
    <div>
        <h1><?= e($meeting['title']) ?></h1>
        <p>
            <?= e($typeLabels[$meeting['type']] ?? $meeting['type']) ?> · <?= status_badge($meeting['status']) ?> · <?= format_date($meeting['starts_at'], 'Y-m-d H:i') ?>
            <?php if ($isRecurring): ?>
                · 🔁 يتكرر <?= e($recurrenceLabels[$meeting['recurrence_rule']]) ?> حتى <?= e($meeting['recurrence_end_date']) ?>
            <?php endif; ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn btn-outline" href="<?= route('/meetings/' . $meeting['id'] . '/print') ?>" target="_blank">🖨️ طباعة / PDF</a>
        <?php if ($canEdit): ?>
            <a class="btn btn-outline" href="<?= route('/meetings/' . $meeting['id'] . '/edit') ?>">تعديل</a>
        <?php endif; ?>
        <?php if ($canEdit && !empty($meeting['recurrence_group_id'])): ?>
            <form method="post" action="<?= route('/meetings/' . $meeting['id'] . '/stop-recurrence') ?>" data-confirm="سيتم حذف كل المواعيد القادمة من هذه السلسلة المتكررة (عدا هذا الموعد). متابعة؟">
                <?= csrf_field() ?>
                <button class="btn btn-outline" type="submit">⏹️ إيقاف التكرار</button>
            </form>
        <?php endif; ?>
        <?php if ($canDelete): ?>
            <form method="post" action="<?= route('/meetings/' . $meeting['id']) ?>" data-confirm="سيتم حذف الاجتماع نهائياً. متابعة؟">
                <?= csrf_field() ?>
                <button class="btn btn-danger" type="submit">حذف</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($myAttendee && $myAttendee['response'] === 'pending' && $meeting['status'] === 'scheduled'): ?>
    <div class="alert alert-danger">
        <span>📩 أنت مدعو لهذا الاجتماع، يرجى تسجيل ردك.</span>
        <div style="display:flex;gap:8px;margin-top:8px;">
            <form method="post" action="<?= route('/meetings/' . $meeting['id'] . '/respond') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="response" value="accepted">
                <button class="btn btn-sm" type="submit" style="background:var(--success);">✓ قبول</button>
            </form>
            <form method="post" action="<?= route('/meetings/' . $meeting['id'] . '/respond') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="response" value="declined">
                <button class="btn btn-outline btn-sm" type="submit">✗ اعتذار</button>
            </form>
        </div>
    </div>
<?php elseif ($myAttendee): ?>
    <div class="alert alert-success">
        <span>ردك المسجّل: <?= status_badge($myAttendee['response']) ?></span>
        <?php if ($meeting['status'] === 'scheduled'): ?>
        <form method="post" action="<?= route('/meetings/' . $meeting['id'] . '/respond') ?>" style="display:inline;margin-inline-start:10px;">
            <?= csrf_field() ?>
            <input type="hidden" name="response" value="<?= $myAttendee['response'] === 'accepted' ? 'declined' : 'accepted' ?>">
            <button class="btn btn-outline btn-sm" type="submit">تغيير الرد</button>
        </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="grid-2" style="align-items:start;">
    <div class="card">
        <div class="card-title"><span>تفاصيل الاجتماع</span></div>
        <table>
            <tr>
                <td class="hint"><?= $meeting['type'] === 'online' ? 'رابط الاجتماع' : 'المكان' ?></td>
                <td>
                    <?php if ($meeting['location'] && $meeting['type'] === 'online'): ?>
                        <a href="<?= e($meeting['location']) ?>" target="_blank" rel="noopener">الانضمام للاجتماع (Zoom / Google Meet)</a>
                    <?php else: ?>
                        <?= e($meeting['location'] ?: '—') ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr><td class="hint">البداية</td><td><?= format_date($meeting['starts_at'], 'Y-m-d H:i') ?></td></tr>
            <tr><td class="hint">النهاية</td><td><?= $meeting['ends_at'] ? format_date($meeting['ends_at'], 'Y-m-d H:i') : '—' ?></td></tr>
            <tr><td class="hint">المنشئ</td><td><?= e($meeting['creator_name'] ?? '-') ?></td></tr>
        </table>
        <?php if ($meeting['description']): ?>
            <p style="margin-top:12px;"><?= nl2br(e($meeting['description'])) ?></p>
        <?php endif; ?>

        <?php if ($canEdit && $meeting['status'] !== 'cancelled'): ?>
            <div class="form-actions" style="margin-top:16px;">
                <?php if ($meeting['status'] === 'scheduled'): ?>
                    <form method="post" action="<?= route('/meetings/' . $meeting['id'] . '/status') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="complete">
                        <button class="btn btn-outline btn-sm" type="submit">✔️ تحديد كمنتهٍ</button>
                    </form>
                    <form method="post" action="<?= route('/meetings/' . $meeting['id'] . '/status') ?>" data-confirm="إلغاء هذا الاجتماع؟">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="cancel">
                        <button class="btn btn-outline btn-sm" type="submit">إلغاء الاجتماع</button>
                    </form>
                <?php elseif ($meeting['status'] === 'completed'): ?>
                    <form method="post" action="<?= route('/meetings/' . $meeting['id'] . '/status') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reopen">
                        <button class="btn btn-outline btn-sm" type="submit">إعادة جدولته</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card-title" style="margin-top:20px;"><span>مخرجات الاجتماع</span></div>
        <?php if ($canEdit): ?>
            <form method="post" action="<?= route('/meetings/' . $meeting['id'] . '/outcomes') ?>">
                <?= csrf_field() ?>
                <textarea name="outcomes" placeholder="القرارات والمهام الناتجة عن الاجتماع..."><?= e($meeting['outcomes'] ?? '') ?></textarea>
                <button class="btn btn-sm" type="submit" style="margin-top:8px;">حفظ المخرجات</button>
            </form>
        <?php else: ?>
            <p><?= $meeting['outcomes'] ? nl2br(e($meeting['outcomes'])) : '<span class="hint">لا توجد مخرجات مسجّلة بعد.</span>' ?></p>
        <?php endif; ?>
    </div>

    <div>
        <div class="card">
            <div class="card-title"><span>الحاضرون (<?= count($attendees) ?>)</span></div>
            <?php foreach ($attendees as $a): ?>
                <div class="doc-log">
                    <div>
                        <?= e($a['user_name'] ?? $a['external_name']) ?><?= !$a['user_id'] ? ' <span class="hint">(خارجي)</span>' : '' ?>
                        <?php if (!$a['user_id'] && $a['token'] && $canEdit): ?>
                            <div class="hint" style="margin-top:4px;word-break:break-all;">
                                رابط تأكيد الحضور: <a href="<?= route('/meetings/rsvp/' . $a['token']) ?>" target="_blank" rel="noopener"><?= route('/meetings/rsvp/' . $a['token']) ?></a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="doc-log-meta" style="display:flex;align-items:center;gap:8px;">
                        <?= status_badge($a['response']) ?>
                        <?php if ($a['response'] === 'pending' && $canEdit): ?>
                            <form method="post" action="<?= route('/meetings/' . $meeting['id'] . '/attendees/' . $a['id'] . '/respond') ?>" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="response" value="accepted">
                                <button class="btn btn-sm" type="submit" title="تأكيد نيابة عنه" style="background:var(--success);">✓</button>
                            </form>
                            <form method="post" action="<?= route('/meetings/' . $meeting['id'] . '/attendees/' . $a['id'] . '/respond') ?>" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="response" value="declined">
                                <button class="btn btn-outline btn-sm" type="submit" title="رفض نيابة عنه">✗</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$attendees): ?><p class="hint">لا يوجد حاضرون مدعوون.</p><?php endif; ?>
        </div>

        <div class="card">
            <div class="card-title"><span>ملاحظات ما قبل الاجتماع</span></div>
            <?php foreach ($beforeNotes as $n): ?>
                <div class="doc-log">
                    <div><?= nl2br(e($n['body'])) ?></div>
                    <div class="doc-log-meta"><?= e($n['user_name'] ?? 'النظام') ?> · <?= format_date($n['created_at'], 'Y-m-d H:i') ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$beforeNotes): ?><p class="hint">لا توجد ملاحظات بعد.</p><?php endif; ?>
            <form method="post" action="<?= route('/meetings/' . $meeting['id'] . '/notes') ?>" style="margin-top:10px;">
                <?= csrf_field() ?>
                <input type="hidden" name="phase" value="before">
                <textarea name="body" placeholder="أضف ملاحظة قبل الاجتماع..." style="min-height:60px;"></textarea>
                <button class="btn btn-outline btn-sm" type="submit" style="margin-top:6px;">إضافة</button>
            </form>
        </div>

        <div class="card">
            <div class="card-title"><span>ملاحظات ما بعد الاجتماع</span></div>
            <?php foreach ($afterNotes as $n): ?>
                <div class="doc-log">
                    <div><?= nl2br(e($n['body'])) ?></div>
                    <div class="doc-log-meta"><?= e($n['user_name'] ?? 'النظام') ?> · <?= format_date($n['created_at'], 'Y-m-d H:i') ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$afterNotes): ?><p class="hint">لا توجد ملاحظات بعد.</p><?php endif; ?>
            <form method="post" action="<?= route('/meetings/' . $meeting['id'] . '/notes') ?>" style="margin-top:10px;">
                <?= csrf_field() ?>
                <input type="hidden" name="phase" value="after">
                <textarea name="body" placeholder="أضف ملاحظة بعد الاجتماع..." style="min-height:60px;"></textarea>
                <button class="btn btn-outline btn-sm" type="submit" style="margin-top:6px;">إضافة</button>
            </form>
        </div>
    </div>
</div>

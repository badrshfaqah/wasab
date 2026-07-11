<?php
$isOnline = $meeting['type'] === 'online';
$flashSuccess = flash_get('success');
$flashError = flash_get('error');
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>تأكيد الحضور - <?= e($meeting['title']) ?></title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
<div class="center" style="max-width:560px;margin:60px auto;">
    <div class="card">
        <div class="card-title"><span>دعوة لحضور اجتماع</span></div>

        <?php if ($flashSuccess): ?><div class="alert alert-success"><?= e($flashSuccess) ?></div><?php endif; ?>
        <?php if ($flashError): ?><div class="alert alert-danger"><?= e($flashError) ?></div><?php endif; ?>

        <h1><?= e($meeting['title']) ?></h1>
        <table>
            <tr><td class="hint">النوع</td><td><?= e($typeLabels[$meeting['type']] ?? $meeting['type']) ?></td></tr>
            <tr><td class="hint">الموعد</td><td><?= format_date($meeting['starts_at'], 'Y-m-d H:i') ?></td></tr>
            <tr>
                <td class="hint"><?= $isOnline ? 'رابط الاجتماع' : 'المكان' ?></td>
                <td>
                    <?php if ($meeting['location'] && $isOnline): ?>
                        <a href="<?= e($meeting['location']) ?>" target="_blank" rel="noopener">الانضمام للاجتماع (Zoom / Google Meet)</a>
                    <?php else: ?>
                        <?= e($meeting['location'] ?: '—') ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php if ($meeting['description']): ?>
            <p style="margin-top:12px;"><?= nl2br(e($meeting['description'])) ?></p>
        <?php endif; ?>

        <div class="card-title" style="margin-top:20px;"><span>تأكيد الحضور</span></div>
        <?php if ($attendee['response'] === 'pending'): ?>
            <p class="hint">مرحباً <?= e($attendee['external_name']) ?>، يرجى تأكيد حضورك لهذا الاجتماع.</p>
            <div style="display:flex;gap:8px;margin-top:8px;">
                <form method="post" action="<?= route('/meetings/rsvp/' . $attendee['token']) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="response" value="accepted">
                    <button class="btn" type="submit" style="background:var(--success);">✓ سأحضر</button>
                </form>
                <form method="post" action="<?= route('/meetings/rsvp/' . $attendee['token']) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="response" value="declined">
                    <button class="btn btn-outline" type="submit">✗ اعتذار</button>
                </form>
            </div>
        <?php else: ?>
            <p>ردك المسجّل: <?= status_badge($attendee['response']) ?></p>
            <form method="post" action="<?= route('/meetings/rsvp/' . $attendee['token']) ?>" style="margin-top:8px;">
                <?= csrf_field() ?>
                <input type="hidden" name="response" value="<?= $attendee['response'] === 'accepted' ? 'declined' : 'accepted' ?>">
                <button class="btn btn-outline btn-sm" type="submit">تغيير الرد</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

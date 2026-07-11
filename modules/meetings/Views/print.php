<?php
use Modules\Meetings\Models\Meeting;

$typeLabels = Meeting::typeLabels();
$statusLabels = Meeting::statusLabels();
$beforeNotes = array_filter($notes, fn ($n) => $n['phase'] === 'before');
$afterNotes = array_filter($notes, fn ($n) => $n['phase'] === 'after');
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>محضر اجتماع - <?= e($meeting['title']) ?></title>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap');
    body { font-family: 'Cairo', sans-serif; padding: 24px; color: #111; max-width: 800px; margin: 0 auto; }
    h1 { margin-bottom: 4px; }
    .meta { color: #555; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    td, th { border: 1px solid #ccc; padding: 8px 10px; text-align: right; vertical-align: top; }
    h2 { font-size: 16px; border-bottom: 2px solid #333; padding-bottom: 4px; margin-top: 28px; }
    .note { border-bottom: 1px solid #eee; padding: 6px 0; }
    .note-meta { color: #777; font-size: 12px; }
    .no-print { margin-bottom: 20px; }
    @media print {
        .no-print { display: none; }
        body { padding: 0; }
    }
</style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()">🖨️ طباعة / حفظ كـ PDF</button>
</div>

<h1><?= e($meeting['title']) ?></h1>
<p class="meta">
    <?= e($typeLabels[$meeting['type']] ?? $meeting['type']) ?> ·
    <?= e($statusLabels[$meeting['status']] ?? $meeting['status']) ?> ·
    <?= format_date($meeting['starts_at'], 'Y-m-d H:i') ?>
</p>

<table>
    <tr><th><?= $meeting['type'] === 'online' ? 'رابط الاجتماع' : 'المكان' ?></th><td><?= e($meeting['location'] ?: '—') ?></td></tr>
    <tr><th>البداية</th><td><?= format_date($meeting['starts_at'], 'Y-m-d H:i') ?></td></tr>
    <tr><th>النهاية</th><td><?= $meeting['ends_at'] ? format_date($meeting['ends_at'], 'Y-m-d H:i') : '—' ?></td></tr>
</table>

<?php if ($meeting['description']): ?>
    <h2>تفاصيل الاجتماع</h2>
    <p><?= nl2br(e($meeting['description'])) ?></p>
<?php endif; ?>

<h2>الحاضرون (<?= count($attendees) ?>)</h2>
<table>
    <thead><tr><th>الاسم</th><th>النوع</th><th>الرد</th></tr></thead>
    <tbody>
    <?php foreach ($attendees as $a): ?>
        <tr>
            <td><?= e($a['user_name'] ?? $a['external_name']) ?></td>
            <td><?= $a['user_id'] ? 'داخلي' : 'خارجي' ?></td>
            <td><?= $a['response'] === 'accepted' ? 'مقبول' : ($a['response'] === 'declined' ? 'معتذر' : 'بانتظار الرد') ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$attendees): ?><tr><td colspan="3">لا يوجد حاضرون مدعوون.</td></tr><?php endif; ?>
    </tbody>
</table>

<?php if ($beforeNotes): ?>
    <h2>ملاحظات ما قبل الاجتماع</h2>
    <?php foreach ($beforeNotes as $n): ?>
        <div class="note">
            <div><?= nl2br(e($n['body'])) ?></div>
            <div class="note-meta"><?= e($n['user_name'] ?? 'النظام') ?> · <?= format_date($n['created_at'], 'Y-m-d H:i') ?></div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($afterNotes): ?>
    <h2>ملاحظات ما بعد الاجتماع</h2>
    <?php foreach ($afterNotes as $n): ?>
        <div class="note">
            <div><?= nl2br(e($n['body'])) ?></div>
            <div class="note-meta"><?= e($n['user_name'] ?? 'النظام') ?> · <?= format_date($n['created_at'], 'Y-m-d H:i') ?></div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($meeting['outcomes']): ?>
    <h2>مخرجات الاجتماع</h2>
    <p><?= nl2br(e($meeting['outcomes'])) ?></p>
<?php endif; ?>

</body>
</html>

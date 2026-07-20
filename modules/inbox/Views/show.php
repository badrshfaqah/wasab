<?php
$extraRows = [];
if (!empty($message['extra'])) {
    $decoded = json_decode($message['extra'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $k => $v) {
            $extraRows[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
        }
    } else {
        $extraRows['بيانات إضافية'] = (string) $message['extra'];
    }
}
?>
<div class="page-head">
    <div>
        <h1><?= e($message['subject'] ?: 'رسالة من ' . ($message['sender_name'] ?: 'مجهول')) ?></h1>
        <p>
            وردت من <span class="badge badge-info"><?= e($message['site_name'] ?? 'موقع محذوف') ?></span>
            بتاريخ <?= format_date($message['received_at'], 'Y-m-d H:i') ?>
            <?= $message['is_read'] ? '· <span class="badge badge-muted">مقروءة</span>' : '· <span class="badge badge-warning">غير مقروءة</span>' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn btn-outline" href="<?= route('/inbox') ?>">← عودة للرسائل</a>
        <?php if ($canMarkRead): ?>
            <form method="post" action="<?= route('/inbox/' . $message['id'] . '/toggle-read') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline" type="submit"><?= $message['is_read'] ? 'تأشير كغير مقروءة' : 'تأشير كمقروءة' ?></button>
            </form>
        <?php endif; ?>
        <?php if ($canDelete): ?>
            <form method="post" action="<?= route('/inbox/' . $message['id'] . '/delete') ?>" onsubmit="return confirm('حذف هذه الرسالة نهائياً؟');">
                <?= csrf_field() ?>
                <button class="btn btn-danger" type="submit">حذف</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-title"><span>بيانات المرسل</span></div>
    <div class="table-wrap">
    <table>
        <tbody>
            <tr><th style="width:160px;">الاسم</th><td><?= e($message['sender_name'] ?: '-') ?></td></tr>
            <tr>
                <th>البريد الإلكتروني</th>
                <td>
                    <?php if ($message['sender_email']): ?>
                        <a href="mailto:<?= e($message['sender_email']) ?>"><?= e($message['sender_email']) ?></a>
                    <?php else: ?>-<?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>الجوال</th>
                <td>
                    <?php if ($message['sender_phone']): ?>
                        <a href="tel:<?= e($message['sender_phone']) ?>" dir="ltr"><?= e($message['sender_phone']) ?></a>
                    <?php else: ?>-<?php endif; ?>
                </td>
            </tr>
            <?php foreach ($extraRows as $label => $value): ?>
                <tr><th><?= e($label) ?></th><td dir="auto"><?= e($value) ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="card">
    <div class="card-title"><span>نص الرسالة</span></div>
    <div style="white-space:pre-wrap;line-height:2;"><?= e($message['body']) ?></div>
</div>

<div class="card">
    <div class="card-title"><span>تفاصيل تقنية</span></div>
    <div class="table-wrap">
    <table>
        <tbody>
            <tr><th style="width:160px;">الموقع المصدر</th><td><?= e($message['site_name'] ?? 'موقع محذوف') ?><?= !empty($message['site_url']) ? ' · <a href="' . e($message['site_url']) . '" target="_blank" rel="noopener" dir="ltr">' . e($message['site_url']) . '</a>' : '' ?></td></tr>
            <tr><th>IP المصدر</th><td dir="ltr" style="text-align:end;"><?= e($message['source_ip'] ?: '-') ?></td></tr>
            <tr><th>وقت الاستلام</th><td><?= format_date($message['received_at'], 'Y-m-d H:i:s') ?></td></tr>
            <?php if ($message['is_read']): ?>
                <tr><th>قرأها</th><td><?= e($message['reader_name'] ?? '-') ?> · <?= format_date($message['read_at'], 'Y-m-d H:i') ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php
$platformLabels = ['wordpress' => 'ووردبريس', 'custom' => 'مخصص'];

/** كود ربط جاهز للنسخ لكل موقع: إرسال غير معطِّل بمهلة قصيرة وكل الأخطاء متجاهَلة. */
$buildSnippet = function (array $site) use ($endpointUrl): string {
    return <<<PHP
/**
 * إرسال نسخة من رسالة التواصل لمركز المراسلات في وصاب - موقع: {$site['name']}
 * غير معطِّل (non-blocking): مهلة قصوى ثانيتان وكل الأخطاء مُتجاهَلة، فلا يتأثر
 * الزائر إطلاقاً حتى لو كان وصاب متوقفاً. استدعِ الدالة بعد نجاح حفظ/إرسال
 * الرسالة الحالي في موقعك، ولا تجعل نجاح النموذج معتمداً عليها أبداً.
 */
function wasab_inbox_notify(array \$fields): void
{
    if (!function_exists('curl_init')) {
        return;
    }
    try {
        \$ch = curl_init('{$endpointUrl}');
        curl_setopt_array(\$ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'name'    => \$fields['name'] ?? '',
                'email'   => \$fields['email'] ?? '',
                'phone'   => \$fields['phone'] ?? '',
                'subject' => \$fields['subject'] ?? '',
                'message' => \$fields['message'] ?? '',
                'extra'   => \$fields['extra'] ?? null,
            ], JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Api-Key: {$site['api_key']}',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => 1500,
            CURLOPT_TIMEOUT_MS => 2000,
        ]);
        curl_exec(\$ch);
        curl_close(\$ch);
    } catch (\\Throwable \$e) {
        // تجاهل تام - لا يجوز أن يتأثر الموقع بأي خلل هنا.
    }
}

// مثال الاستدعاء بعد نجاح حفظ رسالة النموذج:
// wasab_inbox_notify([
//     'name'    => \$name,
//     'email'   => \$email,
//     'phone'   => \$phone,
//     'message' => \$messageText,
// ]);
PHP;
};
?>
<div class="page-head">
    <div><h1>مواقع مركز المراسلات</h1><p>كل موقع له مفتاح ربط خاص به، وأي إرسال بمفتاح غير صحيح أو من موقع معطّل يُرفض.</p></div>
    <div style="display:flex;gap:8px;">
        <a class="btn btn-outline" href="<?= route('/inbox') ?>">← عودة للرسائل</a>
        <a class="btn" href="<?= route('/inbox/sites/create') ?>">+ إضافة موقع</a>
    </div>
</div>

<div class="card">
    <div class="card-title"><span>نقطة الاستقبال (API Endpoint)</span></div>
    <p class="hint" style="margin:0 0 8px;">المواقع ترسل رسائلها بطلب <code>POST</code> على هذا الرابط، مع المفتاح بترويسة <code dir="ltr">X-Api-Key</code>:</p>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <code dir="ltr" style="padding:6px 10px;background:var(--bg);border:1px solid var(--border);border-radius:8px;"><?= e($endpointUrl) ?></code>
        <button type="button" class="btn btn-outline btn-sm" data-copy="<?= e($endpointUrl) ?>">نسخ الرابط</button>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
    <table>
        <thead><tr><th>الموقع</th><th>المنصة</th><th>مفتاح الربط</th><th>الرسائل</th><th>آخر رسالة</th><th>الحالة</th><th>إجراءات</th></tr></thead>
        <tbody>
        <?php if (!$sites): ?>
            <tr><td colspan="7"><div class="empty-state"><div class="ic">🌐</div>لا توجد مواقع بعد - أضف أول موقع ليبدأ استقبال الرسائل</div></td></tr>
        <?php endif; ?>
        <?php foreach ($sites as $s): ?>
            <tr>
                <td>
                    <strong><?= e($s['name']) ?></strong>
                    <?php if ($s['url']): ?>
                        <div class="hint"><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener" dir="ltr"><?= e($s['url']) ?></a></div>
                    <?php endif; ?>
                </td>
                <td><?= e($platformLabels[$s['platform']] ?? $s['platform']) ?></td>
                <td>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <code dir="ltr"><?= e(substr($s['api_key'], 0, 8)) ?>...</code>
                        <button type="button" class="btn btn-outline btn-sm" data-copy="<?= e($s['api_key']) ?>">نسخ</button>
                    </div>
                </td>
                <td>
                    <?= (int) $s['messages_count'] ?>
                    <?php if ((int) $s['unread_count'] > 0): ?>
                        <span class="badge badge-warning"><?= (int) $s['unread_count'] ?> جديدة</span>
                    <?php endif; ?>
                </td>
                <td><?= $s['last_message_at'] ? format_date($s['last_message_at'], 'Y-m-d H:i') : '-' ?></td>
                <td><?= $s['status'] === 'active' ? '<span class="badge badge-success">مفعّل</span>' : '<span class="badge badge-danger">معطّل</span>' ?></td>
                <td>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <a class="btn btn-outline btn-sm" href="<?= route('/inbox/sites/' . $s['id'] . '/edit') ?>">تعديل</a>
                        <form method="post" action="<?= route('/inbox/sites/' . $s['id'] . '/toggle') ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-outline btn-sm" type="submit"><?= $s['status'] === 'active' ? 'تعطيل' : 'تفعيل' ?></button>
                        </form>
                        <form method="post" action="<?= route('/inbox/sites/' . $s['id'] . '/regenerate') ?>" onsubmit="return confirm('توليد مفتاح جديد يُبطل المفتاح الحالي فوراً، وستحتاج تحديث الكود بالموقع. متابعة؟');">
                            <?= csrf_field() ?>
                            <button class="btn btn-outline btn-sm" type="submit">مفتاح جديد</button>
                        </form>
                        <form method="post" action="<?= route('/inbox/sites/' . $s['id'] . '/delete') ?>" onsubmit="return confirm('حذف الموقع يحذف كل رسائله نهائياً. متابعة؟');">
                            <?= csrf_field() ?>
                            <button class="btn btn-danger btn-sm" type="submit">حذف</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php if ($sites): ?>
<div class="card">
    <div class="card-title"><span>كود الربط الجاهز</span></div>
    <p class="hint" style="margin:0 0 12px;">
        انسخ كود الموقع المطلوب وضعه في نقطة حفظ/إرسال رسالة نموذج التواصل الحالية بالموقع (بعد نجاح الحفظ).
        يعمل في ووردبريس (functions.php أو ملف النموذج المخصص) وفي المواقع المخصصة على حد سواء.
    </p>
    <?php foreach ($sites as $s): ?>
        <?php $snippet = $buildSnippet($s); ?>
        <details style="margin-bottom:10px;border:1px solid var(--border);border-radius:8px;padding:10px 14px;">
            <summary style="cursor:pointer;font-weight:700;">
                <?= e($s['name']) ?>
                <button type="button" class="btn btn-outline btn-sm" data-copy="<?= e($snippet) ?>" onclick="event.preventDefault();">نسخ الكود</button>
            </summary>
            <pre dir="ltr" style="margin:10px 0 0;padding:12px;background:var(--bg);border:1px solid var(--border);border-radius:8px;overflow-x:auto;font-size:12px;line-height:1.7;"><code><?= e($snippet) ?></code></pre>
        </details>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        navigator.clipboard.writeText(btn.getAttribute('data-copy')).then(function () {
            var original = btn.textContent;
            btn.textContent = 'تم النسخ ✓';
            setTimeout(function () { btn.textContent = original; }, 1500);
        });
    });
});
</script>

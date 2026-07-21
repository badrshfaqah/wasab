<?php
$platformLabels = ['wordpress' => 'ووردبريس', 'custom' => 'مخصص'];

/**
 * شرح تكامل متكامل قائم بذاته لكل موقع (بمفتاحه الحقيقي): يُنسخ ويُلصق كما هو
 * لأي أداة برمجة بالذكاء الاصطناعي أو فريق تطوير، فلا يحتاج مدير النظام شرح
 * الخدمة من جديد مع كل عملية ربط.
 */
$buildBrief = function (array $site) use ($endpointUrl): string {
    return <<<TEXT
# مهمة: ربط نموذج التواصل بمركز المراسلات في نظام "وصاب"

## ما هي هذه الخدمة؟
"مركز المراسلات" ميزة في نظام وصاب الإداري تجمع رسائل نماذج التواصل من كل مواقع
الشركة في شاشة واحدة، بدل مراجعة كل موقع على حدة. كل موقع يرسل نسخة من أي رسالة
جديدة تصله إلى نقطة استقبال مركزية عبر طلب HTTP بسيط، ويظهر فوراً بشاشة موحدة
مع تنبيهات لجوال الموظفين المختصين.

## المطلوب منك (كمطوّر أو أداة برمجة)
هذا الموقع ({$site['name']}) فيه نموذج تواصل مبرمج مسبقاً. المطلوب فقط: بعد نجاح
حفظ/إرسال رسالة النموذج بطريقته الحالية، أرسل نسخة من الرسالة للنقطة أدناه.
لا تغيّر أي شيء في سلوك النموذج الحالي - الإرسال إضافة جانبية بحتة.

## بيانات الاتصال
- Endpoint: {$endpointUrl}
- Method: POST
- التوثيق: ترويسة X-Api-Key بالمفتاح الخاص بهذا الموقع:
  {$site['api_key']}
- الصيغة: JSON أو Form-Data (كلاهما مقبول)

## حقول الطلب
- message (إلزامي): نص الرسالة - حتى 10000 حرف
- name (اختياري): اسم المرسل
- email (اختياري): بريد المرسل
- phone (اختياري): جوال المرسل
- subject (اختياري): الموضوع
- extra (اختياري): كائن JSON بأي حقول إضافية يجمعها النموذج (المدينة، نوع
  الخدمة، رابط الصفحة...) وستظهر تلقائياً بشاشة وصاب

## الردود المتوقعة
- 201 مع {"success":true,"id":123} => تم الاستلام
- 401 => مفتاح خاطئ أو الموقع معطّل في وصاب
- 422 => حقل message فارغ

## شرط إلزامي: الإرسال غير معطِّل (Non-blocking)
تجربة زائر الموقع مقدّمة على كل شيء:
1. استدعِ الإرسال بعد نجاح المعالجة الأصلية للنموذج، وليس قبلها.
2. مهلة اتصال قصوى 1.5 ثانية ومهلة كلية ثانيتان.
3. تجاهل تام لأي فشل: لو كان وصاب متوقفاً بالكامل يكمل النموذج عمله الطبيعي 100%.
4. لا تجعل رد النموذج للزائر ينتظر نتيجة وصاب أو يتأثر بها بأي شكل.

## قواعد أمان
- المفتاح سرّي وخاص بهذا الموقع وحده: يبقى في كود الخادم فقط. ممنوع وضعه في
  JavaScript أو HTML أو أي كود يصل للمتصفح، وممنوع رفعه لمستودع عام.
- الإرسال من خادم الموقع فقط، وليس من متصفح الزائر (لا fetch/AJAX مباشر).

## كود جاهز (PHP - يصلح لووردبريس والمواقع المخصصة)
function wasab_inbox_notify(array \$fields): void
{
    if (!function_exists('curl_init')) { return; }
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

// الاستدعاء بعد نجاح حفظ رسالة النموذج (واءم أسماء المتغيرات مع كود النموذج الفعلي):
// wasab_inbox_notify(['name' => \$name, 'email' => \$email, 'phone' => \$phone, 'message' => \$messageText]);

(لو كان الموقع بلغة أخرى غير PHP - Node/Python/غيرها - طبّق نفس الطلب بنفس
الترويسة والحقول والمُهل بنفس شرط عدم التعطيل.)

## اختبار التحقق
curl -X POST {$endpointUrl} \\
  -H "Content-Type: application/json" \\
  -H "X-Api-Key: {$site['api_key']}" \\
  -d '{"name":"اختبار","message":"رسالة تجريبية من الموقع"}'

الرد المتوقع: {"success":true,"id":...} - وستظهر الرسالة فوراً في وصاب.

## خطوات التنفيذ المطلوبة
1. حدّد مكان معالجة نموذج التواصل الحالي (نقطة نجاح الحفظ/الإرسال).
2. أضف الدالة والاستدعاء أعلاه مع مواءمة أسماء المتغيرات الفعلية.
3. لا تغيّر أي سلوك موجود ولا تضف رسائل جديدة للزائر.
4. اختبر بإرسال رسالة من النموذج وتأكد أنه يعمل كالسابق تماماً.
TEXT;
};

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

<?php if ($sites): ?>
<div class="card">
    <div class="card-title"><span>📄 شرح الخدمة الجاهز (للمطورين وأدوات البرمجة)</span></div>
    <p class="hint" style="margin:0 0 12px;">
        بدل ما تشرح الخدمة من جديد مع كل عملية ربط: انسخ شرح الموقع المطلوب والصقه كما هو
        في أي أداة برمجة بالذكاء الاصطناعي (كلاود وغيره) أو أرسله لفريق التطوير - شرح متكامل
        قائم بذاته فيه تعريف الخدمة، بيانات الاتصال بمفتاح الموقع، الشروط، الكود، وخطوات الاختبار.
    </p>
    <?php foreach ($sites as $s): ?>
        <?php $brief = $buildBrief($s); ?>
        <details style="margin-bottom:10px;border:1px solid var(--border);border-radius:8px;padding:10px 14px;">
            <summary style="cursor:pointer;font-weight:700;">
                <?= e($s['name']) ?>
                <button type="button" class="btn btn-outline btn-sm" data-copy="<?= e($brief) ?>" onclick="event.preventDefault();">نسخ الشرح</button>
            </summary>
            <pre dir="rtl" style="margin:10px 0 0;padding:12px;background:var(--bg);border:1px solid var(--border);border-radius:8px;overflow-x:auto;font-size:12px;line-height:1.8;white-space:pre-wrap;font-family:inherit;"><?= e($brief) ?></pre>
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

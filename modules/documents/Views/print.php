<?php
use Modules\Documents\Models\Document;

// وضع المعاينة المدمجة (داخل iframe بصفحة المستند): بلا شريط أدوات ولا خلفية رمادية
$embedded = !empty($_GET['embed']);

$typeLabels = Document::typeLabels();
$headerHtml = ($template['header_html'] ?? '') ?: ($settings['header_html'] ?? '');
$footerHtml = ($template['footer_html'] ?? '') ?: ($settings['footer_html'] ?? '');
$numberPosition = $template['number_position'] ?? 'top-right';
$showNumber = $template ? (bool) $template['show_number'] : true;
$showDate = $template ? (bool) $template['show_date'] : true;
/*
  التوثيق (رمز التحقق) اختيار يخص الورقة نفسها كالختم والتوقيع: قرار الإظهار
  من المستند، وموضعه وحجمه ولونه من القالب (وإن لم يكن للمستند قالب فبقيم
  افتراضية). NULL على المستند = اتباع إعداد القالب كما كان قبل هذه الميزة.
*/
$qrOn = $document['qr_enabled'] === null || !array_key_exists('qr_enabled', $document)
    ? !empty($template['qr_enabled'])
    : (bool) $document['qr_enabled'];
$qrX = (int) ($template['qr_x'] ?? 40);
$qrY = (int) ($template['qr_y'] ?? 40);
$qrSize = (int) ($template['qr_size'] ?? 90);
$qrColor = $template['qr_color'] ?? '#000000';

// هوامش النص وعنوان الورقة من القالب (لكل ورق ترويسة مساحته البيضاء)
$marginTop = (int) ($template['margin_top'] ?? 30);
$marginBottom = (int) ($template['margin_bottom'] ?? 25);
$marginX = (int) ($template['margin_x'] ?? 22);
$showTitle = $template ? !empty($template['show_title']) : true;
$titleAboveDate = ($template['title_position'] ?? 'below_date') === 'above_date';

$bgUrl = $bgUrl ?? ($template && $template['background_image']
    ? route('/media/documents/' . $template['company_id'] . '/' . $template['background_image'])
    : null);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title><?= e($document['title']) ?><?= $document['number'] ? ' - ' . e($document['number']) : '' ?></title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap');
*{box-sizing:border-box;}
body{margin:0;background:#e5e7eb;font-family:'Cairo','Segoe UI',Tahoma,Arial,sans-serif;color:#1f2937;}
.toolbar{position:sticky;top:0;background:#111827;color:#fff;padding:10px 20px;display:flex;justify-content:space-between;align-items:center;z-index:10;}
.toolbar button, .toolbar a{background:#2563eb;color:#fff;border:0;border-radius:6px;padding:8px 16px;font-size:14px;cursor:pointer;text-decoration:none;}
.page-wrap{display:flex;justify-content:center;padding:24px 12px;overflow-x:auto;}
.doc-page{
  position:relative;z-index:0;width:210mm;min-height:297mm;background:#fff;
  box-shadow:0 2px 16px rgba(0,0,0,.15);
  -webkit-print-color-adjust:exact;print-color-adjust:exact;
}
/*
  ورق الترويسة طبقة مستقلة بمقاس A4 بالضبط تتكرر رأسياً: حين يطول المحتوى
  ويتجاوز صفحة، تحصل كل صفحة على ترويستها كاملةً غير ممطوطة - بدل صورة واحدة
  تُمدّد على ارتفاع المستند كله فتخرج مشوّهة. z-index سالب ليبقى خلف النص.
*/
.paper-bg{
  position:absolute;inset:0;z-index:-1;pointer-events:none;
  <?= $bgUrl ? "background:url('" . e($bgUrl) . "') top center/210mm 297mm repeat-y;" : '' ?>
  -webkit-print-color-adjust:exact;print-color-adjust:exact;
}
/*
  هوامش الورقة عبر جدول: المتصفحات تعيد طباعة thead/tfoot أعلى وأسفل كل صفحة
  وتحجز ارتفاعهما - فتحصل الصفحة الثانية وما بعدها على نفس الهوامش تلقائياً،
  وهذا ما لا يفعله حشو العنصر (يُطبَّق على أول صفحة وآخرها فقط). وتبقى هوامش
  @page صفراً حتى تغطي الترويسة الورقة من الحافة للحافة.
*/
/* بلا position/z-index هنا عمداً: أي سياق تكديس هنا يعزل محتوى الورقة عن طبقة
   ورق الترويسة خلفها ويربك ترتيب الرسم. */
.paper-flow{width:100%;border-collapse:collapse;}
.paper-flow td{padding:0;}
/* خصوصية أعلى من القاعدة أعلاه وإلا أُلغي هامش النص الجانبي */
.pad-top{height:<?= $marginTop ?>mm;}
.pad-bottom{height:<?= $marginBottom ?>mm;}
.paper-flow td.body-cell{padding:0 <?= $marginX ?>mm;vertical-align:top;}
/* خط رفيع يوضّح أين تنتهي كل صفحة أثناء المعاينة على الشاشة فقط */
@media screen{
  .page-guides{position:absolute;inset:0;z-index:2;pointer-events:none;
    background:repeating-linear-gradient(to bottom, transparent 0 296.6mm, rgba(0,0,0,.18) 296.6mm 297mm);}
}
.doc-badge{position:absolute;font-size:11px;color:#374151;background:rgba(255,255,255,.85);padding:4px 10px;border-radius:6px;}
.doc-badge.top-right{top:10mm;right:14mm;text-align:left;}
.doc-badge.top-left{top:10mm;left:14mm;text-align:right;}
.doc-badge.bottom-right{bottom:10mm;right:14mm;text-align:left;}
.doc-badge.bottom-left{bottom:10mm;left:14mm;text-align:right;}
.doc-header{margin-bottom:18px;font-size:13px;}
.doc-footer{margin-top:24px;font-size:12px;color:#4b5563;}
.doc-verify{margin-top:28px;padding-top:10px;border-top:1px dashed #d1d5db;font-size:11px;color:#6b7280;text-align:center;}
.doc-verify-url{direction:ltr;word-break:break-all;margin-top:2px;font-size:11px;}
.doc-verify-code{margin-top:2px;letter-spacing:1px;font-weight:600;color:#374151;}
.doc-title{font-size:20px;font-weight:700;margin:0 0 18px;text-align:center;}
.doc-meta-line{display:flex;justify-content:space-between;font-size:12px;color:#374151;margin:0 0 16px;}
.doc-content{line-height:2;font-size:14.5px;min-height:120mm;}
.doc-content :is(ul,ol){padding-inline-start:24px;}
.doc-content img{max-width:100%;max-height:200mm;height:auto;}
.doc-content table{border-collapse:collapse;max-width:100%;}
.doc-content :is(td,th){padding:4px 8px;}
.doc-signature{margin-top:40px;display:flex;justify-content:flex-end;gap:20px;text-align:center;}
.doc-signature img{max-height:70px;display:block;margin:0 auto 6px;
  /* بلا mix-blend-mode: الدمج كان يذيب خلفية الأختام الممسوحة البيضاء لكنه
     يغيّر ألوان الختم الشفاف فوق الورق الملوّن. الأصح عرض الصورة كما رُفعت -
     ومن أراد ختماً بلا مربع أبيض يرفعه PNG بخلفية شفافة. */
-webkit-print-color-adjust:exact;print-color-adjust:exact;}
/*
  بدون @page بهوامش صفرية: المتصفح يضيف هوامشه الافتراضية (~13مم لكل جهة) فوق
  هوامش المستند الداخلية (30/22مم)، فيضيق عمود النص وتُطبع الصفحة "صغيرة".
  الحل: تصفير هوامش الطابعة وجعل عنصر الصفحة بمقاس A4 الفعلي - فتُطبع مطابقة
  تماماً لما يظهر بالشاشة، والخلفية تغطي الورقة كاملة من الحافة للحافة.
*/
/*
  هوامش الصفحة تُترك للطابعة (@page) بدل حشو العنصر: هكذا تحترم كل صفحة جديدة
  الهوامش نفسها، فلا يلتصق النص بحافة الصفحة الثانية وما بعدها. وطبقة الورق
  تُثبَّت بإزاحة سالبة بمقدار الهوامش لتغطي الورقة من الحافة للحافة، والعناصر
  المثبّتة تُعاد طباعتها على كل صفحة تلقائياً.
*/
@page{size:A4;margin:0;}
@media print{
  body{background:#fff;}
  .toolbar{display:none;}
  .page-wrap{padding:0;display:block;overflow:visible;}
  .doc-page{box-shadow:none;width:210mm;min-height:297mm;margin:0;}
  .page-guides{display:none;}
  /* عنصر مثبّت بمقاس الورقة: المتصفح يعيد رسمه على كل صفحة مطبوعة */
  .paper-bg{position:fixed;inset:auto;top:0;left:0;width:210mm;height:297mm;
    <?= $bgUrl ? "background:url('" . e($bgUrl) . "') center/210mm 297mm no-repeat;" : '' ?>}
  /* لا يُقطع العنوان ولا كتلة التوقيع بين صفحتين */
  .doc-title{break-after:avoid;}
  .doc-signature{break-inside:avoid;}
  .doc-content :is(p,li,tr,h1,h2,h3,img){break-inside:avoid-page;}
}
<?php if ($embedded): ?>
/* معاينة مدمجة: صفحة نظيفة بلا شريط ولا خلفية - تُعرض داخل iframe */
body{background:#9ca3af;}
.page-wrap{padding:10px 6px;}
.doc-page{box-shadow:0 1px 8px rgba(0,0,0,.25);}
<?php endif; ?>
</style>
</head>
<body>
<?php if (!$embedded): ?>
<div class="toolbar no-print">
    <span><?= e($document['title']) ?></span>
    <div style="display:flex;gap:8px;">
        <button onclick="window.print()">🖨️ طباعة / حفظ PDF</button>
        <a href="javascript:window.close()">إغلاق</a>
    </div>
</div>
<?php endif; ?>
<div class="page-wrap">
    <div class="doc-page">
        <div class="paper-bg"></div>
        <div class="page-guides"></div>
        <table class="paper-flow">
        <thead><tr><td class="pad-top"></td></tr></thead>
        <tfoot><tr><td class="pad-bottom"></td></tr></tfoot>
        <tbody><tr><td class="body-cell">
        <?php
        // «العنوان أعلى التاريخ»: يُطبع العنوان أولاً ثم سطر الرقم/التاريخ تحته
        // داخل المتن. وإلا يبقى الرقم/التاريخ وسماً في زاوية الورقة كما هو.
        $metaInFlow = $titleAboveDate && ($showNumber || $showDate);
        ?>
        <?php if (($showNumber || $showDate) && !$metaInFlow): ?>
            <div class="doc-badge <?= e($numberPosition) ?>">
                <?php if ($showNumber && $document['number']): ?><div>رقم: <?= e($document['number']) ?></div><?php endif; ?>
                <?php if ($showDate): ?><div><?= format_date($document['created_at'], 'Y-m-d') ?></div><?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($showTitle && $titleAboveDate): ?>
            <h1 class="doc-title"><?= e($document['title']) ?></h1>
        <?php endif; ?>
        <?php if ($metaInFlow): ?>
            <div class="doc-meta-line">
                <?php if ($showNumber && $document['number']): ?><span>رقم: <?= e($document['number']) ?></span><?php endif; ?>
                <?php if ($showDate): ?><span><?= format_date($document['created_at'], 'Y-m-d') ?></span><?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($headerHtml): ?>
            <div class="doc-header"><?= $headerHtml ?></div>
        <?php endif; ?>

        <?php if ($showTitle && !$titleAboveDate): ?>
            <h1 class="doc-title"><?= e($document['title']) ?></h1>
        <?php endif; ?>

        <div class="doc-content"><?= $document['content'] ?: '' ?></div>

        <?php
        // كتلة التوقيع تظهر متى اختار الكاتب توقيعاً/ختماً/سطرَي الموقّع أثناء
        // الكتابة، أو بعد الإصدار الرسمي (القيم القديمة من الإعدادات مقيّدة به).
        $chosenWhileWriting = !empty($document['signature_id']) || !empty($document['stamp_id'])
            || !empty($document['signer_title']) || !empty($document['signer_name']);
        $showSignatureBlock = ($document['status'] === 'signed' || $chosenWhileWriting)
            && (!empty($signatureUrl) || !empty($stampUrl) || !empty($signerName) || !empty($signerTitle));
        ?>
        <?php if ($showSignatureBlock): ?>
            <div class="doc-signature">
                <?php if (!empty($stampUrl)): ?>
                    <div><img src="<?= e($stampUrl) ?>" alt=""></div>
                <?php endif; ?>
                <div>
                    <?php // الترتيب: المسمى ثم الاسم ثم التوقيع أسفلهما - وبلا توقيع يبقى الاسم آخر السطور ?>
                    <?php if (!empty($signerTitle)): ?><div style="font-size:13px;"><strong><?= e($signerTitle) ?></strong></div><?php endif; ?>
                    <?php if (!empty($signerName)): ?><div style="margin-top:2px;"><strong><?= e($signerName) ?></strong></div><?php endif; ?>
                    <?php if (!empty($signatureUrl)): ?>
                        <img src="<?= e($signatureUrl) ?>" alt="" style="margin-top:6px;margin-bottom:0;">
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($footerHtml): ?>
            <div class="doc-footer"><?= $footerHtml ?></div>
        <?php endif; ?>

        <?php // عبارة التحقق النصية تُخفى عندما يكون رمز QR مفعّلاً بالقالب (الرمز يغني عنها) ?>
        <?php if (!empty($verifyUrl) && !$qrOn): ?>
            <div class="doc-verify">
                <span>🔎 للتحقق من صحة هذا المستند، امسح الرمز أو افتح الرابط:</span>
                <div class="doc-verify-url"><?= e($verifyUrl) ?></div>
                <div class="doc-verify-code">رمز التحقق: <?= e(strtoupper(substr((string) $document['verify_token'], 0, 8))) ?></div>
            </div>
        <?php endif; ?>

        <?php
        // رمز QR للتحقق بموضع/حجم/لون القالب (بكسل من أسفل ويسار الورقة) - لا يؤثر
        // على شكل الورقة الرسمية لأنه يوضع في الفراغ الذي يحدده المدير.
        if ($qrOn && !empty($verifyUrl)):
            $qrSvg = \App\Core\QrCode::svg($verifyUrl, $qrSize);
            if ($qrSvg):
        ?>
            <div id="doc-qr" style="position:absolute;left:<?= $qrX ?>px;bottom:<?= $qrY ?>px;
                        width:<?= $qrSize ?>px;height:<?= $qrSize ?>px;
                        color:<?= e($qrColor) ?>;line-height:0;
                        -webkit-print-color-adjust:exact;print-color-adjust:exact;"><?= $qrSvg ?></div>
            <script>
            /*
              رمز التحقق يخصّ الورقة كلها لا نهاية النص: مكانه المضبوط بالقالب
              (بكسل من الأسفل واليسار) على الصفحة الأخيرة - صفحةً كان المستند أم
              خمس صفحات. الإسناد المطلق وحده يضعه عند نهاية المحتوى لأن ارتفاع
              الورقة يقف عندها، فنحسب عدد الصفحات ونثبّته في آخر واحدة.
            */
            (function () {
                var page = document.querySelector('.doc-page');
                var qr = document.getElementById('doc-qr');
                if (!page || !qr) { return; }
                var qrY = <?= $qrY ?>, qrSize = <?= $qrSize ?>;

                function place() {
                    var probe = document.createElement('div');
                    probe.style.cssText = 'position:absolute;visibility:hidden;width:0;height:297mm;';
                    page.appendChild(probe);
                    var pageH = probe.offsetHeight;
                    probe.parentNode.removeChild(probe);
                    if (!pageH) { return; }

                    qr.style.display = 'none';
                    var pages = Math.max(1, Math.ceil((page.scrollHeight - 2) / pageH));
                    qr.style.display = '';
                    // إكمال الصفحة الأخيرة حتى يقع الرمز عند حافتها لا عند آخر سطر
                    page.style.minHeight = (pages * pageH) + 'px';
                    qr.style.bottom = 'auto';
                    qr.style.top = ((pages - 1) * pageH + (pageH - qrY - qrSize)) + 'px';
                }

                place();
                window.addEventListener('beforeprint', place);
                if (document.fonts && document.fonts.ready) { document.fonts.ready.then(place); }
                window.addEventListener('load', place);
            })();
            </script>
        <?php endif; endif; ?>
        </td></tr></tbody>
        </table>
    </div>
</div>
</body>
</html>

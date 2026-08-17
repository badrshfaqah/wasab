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
$bgUrl = $template && $template['background_image']
    ? route('/media/documents/' . $template['company_id'] . '/' . $template['background_image'])
    : null;
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
  position:relative;width:210mm;min-height:297mm;background:#fff <?= $bgUrl ? 'url(' . e($bgUrl) . ') center/cover no-repeat' : '' ?>;
  box-shadow:0 2px 16px rgba(0,0,0,.15);padding:30mm 22mm;
  -webkit-print-color-adjust:exact;print-color-adjust:exact;
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
.doc-content{line-height:2;font-size:14.5px;min-height:120mm;}
.doc-content :is(ul,ol){padding-inline-start:24px;}
.doc-signature{margin-top:40px;display:flex;justify-content:flex-end;gap:20px;text-align:center;}
.doc-signature img{max-height:70px;display:block;margin:0 auto 6px;}
/*
  بدون @page بهوامش صفرية: المتصفح يضيف هوامشه الافتراضية (~13مم لكل جهة) فوق
  هوامش المستند الداخلية (30/22مم)، فيضيق عمود النص وتُطبع الصفحة "صغيرة".
  الحل: تصفير هوامش الطابعة وجعل عنصر الصفحة بمقاس A4 الفعلي - فتُطبع مطابقة
  تماماً لما يظهر بالشاشة، والخلفية تغطي الورقة كاملة من الحافة للحافة.
*/
@page{size:A4;margin:0;}
@media print{
  body{background:#fff;}
  .toolbar{display:none;}
  .page-wrap{padding:0;display:block;overflow:visible;}
  .doc-page{box-shadow:none;width:210mm;min-height:297mm;margin:0;}
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
        <?php if ($showNumber || $showDate): ?>
            <div class="doc-badge <?= e($numberPosition) ?>">
                <?php if ($showNumber && $document['number']): ?><div>رقم: <?= e($document['number']) ?></div><?php endif; ?>
                <?php if ($showDate): ?><div><?= format_date($document['created_at'], 'Y-m-d') ?></div><?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($headerHtml): ?>
            <div class="doc-header"><?= $headerHtml ?></div>
        <?php endif; ?>

        <h1 class="doc-title"><?= e($document['title']) ?></h1>

        <div class="doc-content"><?= $document['content'] ?: '' ?></div>

        <?php if ($document['status'] === 'signed' && (!empty($signatureUrl) || !empty($stampUrl) || !empty($signerName))): ?>
            <div class="doc-signature">
                <?php if (!empty($stampUrl)): ?>
                    <div><img src="<?= e($stampUrl) ?>" alt=""></div>
                <?php endif; ?>
                <div>
                    <?php if (!empty($signatureUrl)): ?>
                        <img src="<?= e($signatureUrl) ?>" alt="">
                    <?php endif; ?>
                    <?php if (!empty($signerName)): ?><div><strong><?= e($signerName) ?></strong></div><?php endif; ?>
                    <?php if (!empty($settings['signer_title'])): ?><div style="font-size:12px;color:#6b7280;"><?= e($settings['signer_title']) ?></div><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($footerHtml): ?>
            <div class="doc-footer"><?= $footerHtml ?></div>
        <?php endif; ?>

        <?php // عبارة التحقق النصية تُخفى عندما يكون رمز QR مفعّلاً بالقالب (الرمز يغني عنها) ?>
        <?php if (!empty($verifyUrl) && empty($template['qr_enabled'])): ?>
            <div class="doc-verify">
                <span>🔎 للتحقق من صحة هذا المستند، امسح الرمز أو افتح الرابط:</span>
                <div class="doc-verify-url"><?= e($verifyUrl) ?></div>
                <div class="doc-verify-code">رمز التحقق: <?= e(strtoupper(substr((string) $document['verify_token'], 0, 8))) ?></div>
            </div>
        <?php endif; ?>

        <?php
        // رمز QR للتحقق بموضع/حجم/لون القالب (بكسل من أسفل ويسار الورقة) - لا يؤثر
        // على شكل الورقة الرسمية لأنه يوضع في الفراغ الذي يحدده المدير.
        if ($template && !empty($template['qr_enabled']) && !empty($verifyUrl)):
            $qrSvg = \App\Core\QrCode::svg($verifyUrl, (int) $template['qr_size']);
            if ($qrSvg):
        ?>
            <div style="position:absolute;left:<?= (int) $template['qr_x'] ?>px;bottom:<?= (int) $template['qr_y'] ?>px;
                        width:<?= (int) $template['qr_size'] ?>px;height:<?= (int) $template['qr_size'] ?>px;
                        color:<?= e($template['qr_color']) ?>;line-height:0;
                        -webkit-print-color-adjust:exact;print-color-adjust:exact;"><?= $qrSvg ?></div>
        <?php endif; endif; ?>
    </div>
</div>
</body>
</html>

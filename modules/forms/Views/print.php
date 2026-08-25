<?php
// وضع المعاينة المدمجة (داخل iframe بصفحة الخطاب): بلا شريط أدوات
$embedded = !empty($_GET['embed']);
$companyId = $letter['company_id'];
$bg = $bgUrl ?? ($settings['background_image'] ? route('/media/forms/' . $companyId . '/' . $settings['background_image']) : null);
$header = $settings['header_html'] ?? '';
$footer = $settings['footer_html'] ?? '';

/*
  التوثيق اختيار يخص الخطاب نفسه (كالختم والتوقيع): قرار الإظهار من الخطاب،
  وموضعه وحجمه ولونه من القالب. NULL على الخطاب = اتباع إعداد القالب.
*/
$qrOn = !array_key_exists('qr_enabled', $letter) || $letter['qr_enabled'] === null
    ? !empty($template['qr_enabled'])
    : (bool) $letter['qr_enabled'];
$qrX = (int) ($template['qr_x'] ?? 40);
$qrY = (int) ($template['qr_y'] ?? 40);
$qrSize = (int) ($template['qr_size'] ?? 90);
$qrColor = $template['qr_color'] ?? '#000000';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title><?= e($letter['title']) ?> - <?= e($letter['number']) ?></title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap');
*{box-sizing:border-box;}
body{margin:0;background:#e5e7eb;font-family:'Cairo','Segoe UI',Tahoma,Arial,sans-serif;color:#1f2937;}
.toolbar{position:sticky;top:0;background:#111827;color:#fff;padding:10px 20px;display:flex;justify-content:space-between;align-items:center;z-index:10;}
.toolbar button,.toolbar a{background:#2563eb;color:#fff;border:0;border-radius:6px;padding:8px 16px;font-size:14px;cursor:pointer;text-decoration:none;font-family:inherit;}
.page-wrap{display:flex;justify-content:center;padding:24px 12px;}
.doc-page{
  position:relative;width:210mm;min-height:297mm;background:#fff <?= $bg ? 'url(' . e($bg) . ') center/cover no-repeat' : '' ?>;
  box-shadow:0 2px 16px rgba(0,0,0,.15);padding:35mm 25mm;-webkit-print-color-adjust:exact;print-color-adjust:exact;
}
.doc-number{position:absolute;top:14mm;left:20mm;font-size:12px;color:#374151;}
.doc-header{margin-bottom:20px;font-size:13px;}
.doc-body{line-height:2.2;font-size:15px;white-space:pre-wrap;min-height:120mm;}
.doc-signature{margin-top:50px;display:flex;justify-content:flex-start;gap:20px;text-align:center;}
.doc-signature img{max-height:80px;display:block;margin:0 auto 6px;
  /* انظر ملاحظة نفس القاعدة في مستندات/print.php: إذابة الخلفية البيضاء للأختام الممسوحة */
  mix-blend-mode:multiply;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
.doc-footer{margin-top:26px;font-size:12px;color:#4b5563;}
@page{size:A4;margin:0;}
@media print{
  body{background:#fff;}
  .toolbar{display:none;}
  .page-wrap{padding:0;}
  .doc-page{box-shadow:none;width:210mm;min-height:297mm;margin:0;}
}
<?php if ($embedded): ?>
body{background:#9ca3af;}
.page-wrap{padding:10px 6px;}
.doc-page{box-shadow:0 1px 8px rgba(0,0,0,.25);}
<?php endif; ?>
</style>
</head>
<body>
<?php if (!$embedded): ?>
<div class="toolbar">
    <span><?= e($letter['title']) ?> - <?= e($letter['number']) ?></span>
    <div style="display:flex;gap:8px;">
        <button onclick="window.print()">🖨️ طباعة / حفظ PDF</button>
        <a href="javascript:window.close()">إغلاق</a>
    </div>
</div>
<?php endif; ?>
<div class="page-wrap">
    <div class="doc-page">
        <div class="doc-number">رقم: <?= e($letter['number']) ?></div>
        <?php if ($header): ?><div class="doc-header"><?= $header ?></div><?php endif; ?>

        <div class="doc-body"><?= e($letter['body']) ?></div>

        <?php if (!empty($signatureUrl) || !empty($stampUrl) || !empty($signerName)): ?>
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

        <?php if ($footer): ?><div class="doc-footer"><?= $footer ?></div><?php endif; ?>
        <?php // عبارة التحقق النصية تُخفى عندما يكون رمز QR مفعّلاً بالقالب (الرمز يغني عنها) ?>
        <?php if (!empty($letter['verify_token']) && !$qrOn): ?>
            <div style="margin-top:18px;padding-top:8px;border-top:1px dashed #cbd5e1;font-size:11px;color:#6b7280;text-align:center;">
                للتحقق من صحة هذا الخطاب: <?= e(base_url('forms/verify/' . $letter['verify_token'])) ?>
                — رمز التحقق: <strong><?= e(strtoupper(substr((string) $letter['verify_token'], 0, 8))) ?></strong>
            </div>
        <?php endif; ?>

        <?php
        if ($qrOn && !empty($verifyUrl)):
            $qrSvg = \App\Core\QrCode::svg($verifyUrl, $qrSize);
            if ($qrSvg):
        ?>
            <div style="position:absolute;left:<?= $qrX ?>px;bottom:<?= $qrY ?>px;
                        width:<?= $qrSize ?>px;height:<?= $qrSize ?>px;
                        color:<?= e($qrColor) ?>;line-height:0;
                        -webkit-print-color-adjust:exact;print-color-adjust:exact;"><?= $qrSvg ?></div>
        <?php endif; endif; ?>
    </div>
</div>
</body>
</html>

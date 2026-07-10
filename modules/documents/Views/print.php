<?php
use Modules\Documents\Models\Document;

$typeLabels = Document::typeLabels();
$headerHtml = ($template['header_html'] ?? '') ?: ($settings['header_html'] ?? '');
$footerHtml = ($template['footer_html'] ?? '') ?: ($settings['footer_html'] ?? '');
$numberPosition = $template['number_position'] ?? 'top-right';
$showNumber = $template ? (bool) $template['show_number'] : true;
$showDate = $template ? (bool) $template['show_date'] : true;
$bgUrl = $template && $template['background_image']
    ? base_url('storage/uploads/documents/' . $template['background_image'])
    : null;
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title><?= e($document['title']) ?><?= $document['number'] ? ' - ' . e($document['number']) : '' ?></title>
<style>
*{box-sizing:border-box;}
body{margin:0;background:#e5e7eb;font-family:'Segoe UI',Tahoma,Arial,sans-serif;color:#1f2937;}
.toolbar{position:sticky;top:0;background:#111827;color:#fff;padding:10px 20px;display:flex;justify-content:space-between;align-items:center;z-index:10;}
.toolbar button, .toolbar a{background:#2563eb;color:#fff;border:0;border-radius:6px;padding:8px 16px;font-size:14px;cursor:pointer;text-decoration:none;}
.page-wrap{display:flex;justify-content:center;padding:24px 12px;}
.doc-page{
  position:relative;width:210mm;min-height:297mm;background:#fff <?= $bgUrl ? 'url(' . e($bgUrl) . ') center/cover no-repeat' : '' ?>;
  box-shadow:0 2px 16px rgba(0,0,0,.15);padding:30mm 22mm;
}
.doc-badge{position:absolute;font-size:11px;color:#374151;background:rgba(255,255,255,.85);padding:4px 10px;border-radius:6px;}
.doc-badge.top-right{top:10mm;right:14mm;text-align:left;}
.doc-badge.top-left{top:10mm;left:14mm;text-align:right;}
.doc-badge.bottom-right{bottom:10mm;right:14mm;text-align:left;}
.doc-badge.bottom-left{bottom:10mm;left:14mm;text-align:right;}
.doc-header{margin-bottom:18px;font-size:13px;}
.doc-footer{margin-top:24px;font-size:12px;color:#4b5563;}
.doc-title{font-size:20px;font-weight:700;margin:0 0 18px;text-align:center;}
.doc-content{line-height:2;font-size:14.5px;min-height:120mm;}
.doc-content :is(ul,ol){padding-inline-start:24px;}
.doc-signature{margin-top:40px;display:flex;justify-content:flex-end;gap:20px;text-align:center;}
.doc-signature img{max-height:70px;display:block;margin:0 auto 6px;}
@media print{
  body{background:#fff;}
  .toolbar{display:none;}
  .page-wrap{padding:0;}
  .doc-page{box-shadow:none;width:auto;min-height:auto;margin:0;}
}
</style>
</head>
<body>
<div class="toolbar no-print">
    <span><?= e($document['title']) ?></span>
    <div style="display:flex;gap:8px;">
        <button onclick="window.print()">🖨️ طباعة / حفظ PDF</button>
        <a href="javascript:window.close()">إغلاق</a>
    </div>
</div>
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

        <?php if ($document['status'] === 'signed' && (!empty($settings['signature_image']) || !empty($settings['stamp_image']) || !empty($settings['signer_name']))): ?>
            <div class="doc-signature">
                <?php if (!empty($settings['stamp_image'])): ?>
                    <div><img src="<?= e(base_url('storage/uploads/documents/' . $settings['stamp_image'])) ?>" alt=""></div>
                <?php endif; ?>
                <div>
                    <?php if (!empty($settings['signature_image'])): ?>
                        <img src="<?= e(base_url('storage/uploads/documents/' . $settings['signature_image'])) ?>" alt="">
                    <?php endif; ?>
                    <?php if (!empty($settings['signer_name'])): ?><div><strong><?= e($settings['signer_name']) ?></strong></div><?php endif; ?>
                    <?php if (!empty($settings['signer_title'])): ?><div style="font-size:12px;color:#6b7280;"><?= e($settings['signer_title']) ?></div><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($footerHtml): ?>
            <div class="doc-footer"><?= $footerHtml ?></div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

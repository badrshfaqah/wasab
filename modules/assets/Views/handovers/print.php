<?php
/** @var array $handover @var array $items @var array|null $company */
$openItems = array_filter($items, fn ($i) => empty($i['returned_at']));
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>محضر تسليم عهدة - <?= e($handover['holder_name']) ?></title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap');
*{box-sizing:border-box;}
body{margin:0;background:#e5e7eb;font-family:'Cairo','Segoe UI',Tahoma,Arial,sans-serif;color:#1f2937;}
.toolbar{position:sticky;top:0;background:#111827;color:#fff;padding:10px 20px;display:flex;justify-content:space-between;align-items:center;z-index:10;}
.toolbar button, .toolbar a{background:#2563eb;color:#fff;border:0;border-radius:6px;padding:8px 16px;font-size:14px;cursor:pointer;text-decoration:none;}
.page-wrap{display:flex;justify-content:center;padding:24px 12px;overflow-x:auto;}
.doc-page{position:relative;width:210mm;min-height:297mm;background:#fff;box-shadow:0 2px 16px rgba(0,0,0,.15);padding:26mm 20mm;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
.doc-org{text-align:center;font-size:18px;font-weight:800;margin-bottom:4px;}
.doc-title{font-size:20px;font-weight:700;margin:0 0 4px;text-align:center;}
.doc-sub{text-align:center;color:#6b7280;font-size:12.5px;margin-bottom:22px;}
.meta{display:grid;grid-template-columns:1fr 1fr;gap:8px 24px;font-size:13.5px;margin-bottom:20px;border:1px solid #e5e7eb;border-radius:8px;padding:14px 16px;}
.meta div span{color:#6b7280;}
table{width:100%;border-collapse:collapse;font-size:13px;margin-bottom:8px;}
th,td{border:1px solid #d1d5db;padding:8px 10px;text-align:start;}
th{background:#f3f4f6;font-weight:700;}
.intro{font-size:13.5px;line-height:2;margin-bottom:14px;}
.ack{margin-top:10px;padding:10px 14px;border:1px dashed #16a34a;border-radius:8px;color:#166534;font-size:13px;}
.sign{margin-top:48px;display:flex;justify-content:space-between;gap:30px;font-size:13.5px;}
.sign .box{flex:1;text-align:center;}
.sign .line{margin-top:44px;border-top:1px solid #6b7280;padding-top:6px;}
@page{size:A4;margin:0;}
@media print{
  body{background:#fff;}
  .toolbar{display:none;}
  .page-wrap{padding:0;display:block;overflow:visible;}
  .doc-page{box-shadow:none;width:210mm;min-height:297mm;margin:0;}
}
</style>
</head>
<body>
<div class="toolbar no-print">
    <span>محضر تسليم عهدة</span>
    <div style="display:flex;gap:8px;">
        <button onclick="window.print()">🖨️ طباعة / حفظ PDF</button>
        <a href="javascript:window.close()">إغلاق</a>
    </div>
</div>
<div class="page-wrap">
    <div class="doc-page">
        <?php if ($company && !empty($company['name'])): ?><div class="doc-org"><?= e($company['name']) ?></div><?php endif; ?>
        <h1 class="doc-title">محضر تسليم واستلام عهدة</h1>
        <div class="doc-sub">محضر رقم #<?= (int) $handover['id'] ?> — بتاريخ <?= format_date($handover['handover_date'], 'Y-m-d') ?></div>

        <div class="meta">
            <div><span>الحامل (المستلم): </span><strong><?= e($handover['holder_name']) ?></strong></div>
            <div><span>تاريخ التسليم: </span><strong><?= format_date($handover['handover_date'], 'Y-m-d') ?></strong></div>
            <?php if (!empty($handover['holder_contact'])): ?><div><span>بيانات التواصل: </span><?= e($handover['holder_contact']) ?></div><?php endif; ?>
            <div><span>عدد الأصول: </span><?= count($items) ?></div>
        </div>

        <p class="intro">أُقرّ أنا الموقّع أدناه (المستلم) باستلامي الأصول التالية على سبيل العهدة، وأتعهّد بالمحافظة عليها وإعادتها عند الطلب أو انتهاء الغرض من العهدة:</p>

        <table>
            <thead><tr><th style="width:36px;">#</th><th>الأصل</th><th>الرمز</th><th>الرقم التسلسلي</th></tr></thead>
            <tbody>
            <?php $n = 0; foreach ($items as $it): $n++; ?>
                <tr>
                    <td><?= $n ?></td>
                    <td><?= e($it['asset_name']) ?><?= !empty($it['returned_at']) ? ' <em style="color:#6b7280;">(أُرجع)</em>' : '' ?></td>
                    <td dir="ltr" style="text-align:start;"><?= e($it['asset_code'] ?: '—') ?></td>
                    <td dir="ltr" style="text-align:start;"><?= e($it['serial_number'] ?: '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (!empty($handover['notes'])): ?>
            <p class="intro"><span style="color:#6b7280;">ملاحظات: </span><?= nl2br(e($handover['notes'])) ?></p>
        <?php endif; ?>

        <?php if (!empty($handover['acknowledged_at'])): ?>
            <div class="ack">✅ أقرّ الحامل إلكترونياً باستلام هذه العهدة بتاريخ <?= format_date($handover['acknowledged_at'], 'Y-m-d H:i') ?>.</div>
        <?php endif; ?>

        <div class="sign">
            <div class="box"><div>المُسلِّم</div><div class="line">الاسم / التوقيع</div></div>
            <div class="box"><div>المستلم (الحامل)</div><div class="line"><?= e($handover['holder_name']) ?> / التوقيع</div></div>
        </div>
    </div>
</div>
</body>
</html>

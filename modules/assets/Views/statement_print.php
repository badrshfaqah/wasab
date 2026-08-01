<?php
/** @var string $holderName @var array $assets @var array|null $company @var array $statusLabels */
$totalValue = 0;
foreach ($assets as $a) {
    if ($a['purchase_cost'] !== null) {
        $totalValue += (float) $a['purchase_cost'];
    }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>كشف عهدة - <?= e($holderName) ?></title>
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
tfoot td{font-weight:700;background:#f9fafb;}
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
    <span>كشف عهدة الموظف</span>
    <div style="display:flex;gap:8px;">
        <button onclick="window.print()">🖨️ طباعة / حفظ PDF</button>
        <a href="javascript:window.close()">إغلاق</a>
    </div>
</div>
<div class="page-wrap">
    <div class="doc-page">
        <?php if ($company && !empty($company['name'])): ?><div class="doc-org"><?= e($company['name']) ?></div><?php endif; ?>
        <h1 class="doc-title">كشف العهد المسندة</h1>
        <div class="doc-sub">تاريخ الإصدار: <?= format_date(date('Y-m-d'), 'Y-m-d') ?></div>

        <div class="meta">
            <div><span>الحامل: </span><strong><?= e($holderName) ?></strong></div>
            <div><span>عدد الأصول بالعهدة: </span><strong><?= count($assets) ?></strong></div>
        </div>

        <table>
            <thead><tr><th style="width:36px;">#</th><th>الأصل</th><th>التصنيف</th><th>الرمز</th><th>الرقم التسلسلي</th><th>تاريخ الإسناد</th><th>القيمة</th></tr></thead>
            <tbody>
            <?php $n = 0; foreach ($assets as $a): $n++; ?>
                <tr>
                    <td><?= $n ?></td>
                    <td><?= e($a['name']) ?></td>
                    <td><?= e($a['category_name'] ?? '—') ?></td>
                    <td dir="ltr" style="text-align:start;"><?= e($a['asset_code'] ?: '—') ?></td>
                    <td dir="ltr" style="text-align:start;"><?= e($a['serial_number'] ?: '—') ?></td>
                    <td><?= $a['assigned_at'] ? format_date($a['assigned_at'], 'Y-m-d') : '—' ?></td>
                    <td><?= $a['purchase_cost'] !== null ? number_format((float) $a['purchase_cost'], 2) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if ($totalValue > 0): ?>
            <tfoot><tr><td colspan="6" style="text-align:end;">إجمالي القيمة التقديرية</td><td><?= number_format($totalValue, 2) ?></td></tr></tfoot>
            <?php endif; ?>
        </table>

        <p style="font-size:13px;line-height:2;margin-top:14px;">أُقرّ باستلامي العهد الموضّحة أعلاه ومسؤوليتي عنها حتى إعادتها.</p>

        <div class="sign">
            <div class="box"><div>الحامل</div><div class="line"><?= e($holderName) ?> / التوقيع</div></div>
            <div class="box"><div>المسؤول</div><div class="line">الاسم / التوقيع</div></div>
        </div>
    </div>
</div>
</body>
</html>

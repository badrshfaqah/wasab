<?php /** @var string $month @var array $sections @var ?array $company */ ?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>التقرير الشهري - <?= e($month) ?></title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap');
*{box-sizing:border-box;}
body{margin:0;background:#e5e7eb;font-family:'Cairo','Segoe UI',Tahoma,Arial,sans-serif;color:#1f2937;}
.toolbar{position:sticky;top:0;background:#111827;color:#fff;padding:10px 20px;display:flex;justify-content:space-between;align-items:center;z-index:10;gap:10px;flex-wrap:wrap;}
.toolbar button,.toolbar a{background:#2563eb;color:#fff;border:0;border-radius:6px;padding:8px 16px;font-size:14px;cursor:pointer;text-decoration:none;}
.toolbar form{display:flex;gap:6px;align-items:center;}
.toolbar input{border:0;border-radius:6px;padding:7px 10px;font-family:inherit;}
.page-wrap{display:flex;justify-content:center;padding:24px 12px;}
.doc-page{width:210mm;min-height:297mm;background:#fff;box-shadow:0 2px 16px rgba(0,0,0,.15);padding:24mm 20mm;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
.doc-org{text-align:center;font-size:18px;font-weight:800;margin-bottom:4px;}
.doc-title{font-size:21px;font-weight:800;margin:0 0 4px;text-align:center;}
.doc-sub{text-align:center;color:#6b7280;font-size:13px;margin-bottom:24px;}
.sec{margin-bottom:18px;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;}
.sec-title{background:#f3f4f6;padding:9px 14px;font-weight:700;font-size:14.5px;}
.sec table{width:100%;border-collapse:collapse;font-size:13.5px;}
.sec td{padding:8px 14px;border-top:1px solid #f1f5f9;}
.sec td:last-child{text-align:start;font-weight:800;font-size:16px;width:90px;}
.doc-footer{margin-top:26px;padding-top:10px;border-top:1px dashed #d1d5db;font-size:11px;color:#6b7280;text-align:center;}
@page{size:A4;margin:0;}
@media print{body{background:#fff;}.toolbar{display:none;}.page-wrap{padding:0;display:block;}.doc-page{box-shadow:none;margin:0;}}
</style>
</head>
<body>
<div class="toolbar">
    <form method="get" action="<?= route('/reports/monthly') ?>">
        <input type="month" name="month" value="<?= e($month) ?>">
        <button type="submit">عرض</button>
    </form>
    <div style="display:flex;gap:8px;">
        <button onclick="window.print()">🖨️ طباعة / حفظ PDF</button>
        <a href="<?= route('/reports') ?>">← التقارير</a>
    </div>
</div>
<div class="page-wrap">
    <div class="doc-page">
        <?php if ($company && !empty($company['name'])): ?><div class="doc-org"><?= e($company['name']) ?></div><?php endif; ?>
        <h1 class="doc-title">التقرير الشهري</h1>
        <div class="doc-sub">شهر <?= e($month) ?> — أُصدر بتاريخ <?= date('Y-m-d') ?></div>

        <?php if (!$sections): ?>
            <p style="text-align:center;color:#6b7280;">لا إضافات مفعّلة لعرض أرقامها.</p>
        <?php endif; ?>

        <?php foreach ($sections as $title => $stats): ?>
            <div class="sec">
                <div class="sec-title"><?= e($title) ?></div>
                <table>
                    <?php foreach ($stats as $label => $value): ?>
                        <tr><td><?= e($label) ?></td><td><?= (int) $value ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endforeach; ?>

        <div class="doc-footer">تقرير مولّد آلياً من نظام وصاب — <?= e($month) ?></div>
    </div>
</div>
</body>
</html>

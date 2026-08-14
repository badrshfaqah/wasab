<?php /** @var array $run @var array $items @var ?array $company */
$totalNet = array_sum(array_map(fn ($i) => (float) $i['net'], $items));
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>مسير رواتب <?= e($run['month']) ?></title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap');
*{box-sizing:border-box;}
body{margin:0;background:#e5e7eb;font-family:'Cairo','Segoe UI',Tahoma,Arial,sans-serif;color:#1f2937;}
.toolbar{position:sticky;top:0;background:#111827;color:#fff;padding:10px 20px;display:flex;justify-content:space-between;align-items:center;z-index:10;}
.toolbar button,.toolbar a{background:#2563eb;color:#fff;border:0;border-radius:6px;padding:8px 16px;font-size:14px;cursor:pointer;text-decoration:none;}
.page-wrap{display:flex;justify-content:center;padding:24px 12px;}
.doc-page{width:210mm;min-height:297mm;background:#fff;box-shadow:0 2px 16px rgba(0,0,0,.15);padding:22mm 16mm;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
.doc-org{text-align:center;font-size:18px;font-weight:800;margin-bottom:4px;}
.doc-title{font-size:20px;font-weight:800;margin:0 0 4px;text-align:center;}
.doc-sub{text-align:center;color:#6b7280;font-size:12.5px;margin-bottom:20px;}
table{width:100%;border-collapse:collapse;font-size:12.5px;}
th,td{border:1px solid #d1d5db;padding:7px 9px;text-align:start;}
th{background:#f3f4f6;font-weight:700;}
tfoot td{font-weight:800;background:#f9fafb;}
.sign{margin-top:44px;display:flex;justify-content:space-between;gap:30px;font-size:13px;}
.sign .box{flex:1;text-align:center;}
.sign .line{margin-top:40px;border-top:1px solid #6b7280;padding-top:6px;}
@page{size:A4;margin:0;}
@media print{body{background:#fff;}.toolbar{display:none;}.page-wrap{padding:0;display:block;}.doc-page{box-shadow:none;margin:0;}}
</style>
</head>
<body>
<div class="toolbar">
    <span>مسير رواتب <?= e($run['month']) ?></span>
    <div style="display:flex;gap:8px;">
        <button onclick="window.print()">🖨️ طباعة / حفظ PDF</button>
        <a href="javascript:window.close()">إغلاق</a>
    </div>
</div>
<div class="page-wrap">
    <div class="doc-page">
        <?php if ($company && !empty($company['name'])): ?><div class="doc-org"><?= e($company['name']) ?></div><?php endif; ?>
        <h1 class="doc-title">مسير رواتب شهر <?= e($run['month']) ?></h1>
        <div class="doc-sub"><?= $run['status'] === 'approved' ? 'معتمد بتاريخ ' . format_date($run['approved_at'], 'Y-m-d') : 'مسودة غير معتمدة' ?> — <?= count($items) ?> موظفاً</div>

        <table>
            <thead><tr><th style="width:30px;">#</th><th>الموظف</th><th>المسمى</th><th>الأساسي</th><th>البدلات</th><th>الخصومات</th><th>الصافي</th></tr></thead>
            <tbody>
            <?php $n = 0; foreach ($items as $i): $n++; ?>
                <tr>
                    <td><?= $n ?></td>
                    <td><?= e($i['full_name']) ?></td>
                    <td><?= e($i['job_title'] ?: '—') ?></td>
                    <td><?= number_format((float) $i['base_salary'], 2) ?></td>
                    <td><?= number_format((float) $i['allowances'], 2) ?></td>
                    <td><?= number_format((float) $i['deductions'], 2) ?><?= $i['deduction_note'] ? '<div style="font-size:10px;color:#6b7280;">' . e($i['deduction_note']) . '</div>' : '' ?></td>
                    <td><strong><?= number_format((float) $i['net'], 2) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="6" style="text-align:end;">الإجمالي</td><td><?= number_format($totalNet, 2) ?></td></tr></tfoot>
        </table>

        <div class="sign">
            <div class="box"><div>أعدّه</div><div class="line">الاسم / التوقيع</div></div>
            <div class="box"><div>اعتمده</div><div class="line">الاسم / التوقيع</div></div>
        </div>
    </div>
</div>
</body>
</html>

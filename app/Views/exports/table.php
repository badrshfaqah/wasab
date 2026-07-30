<?php /** صفحة طباعة/PDF موحّدة لأي جدول تصدير - مستقلة عن تخطيط النظام. */ ?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title><?= e($title) ?></title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap');
*{box-sizing:border-box;}
body{margin:0;background:#e5e7eb;font-family:'Cairo','Segoe UI',Tahoma,Arial,sans-serif;color:#1f2937;}
.toolbar{position:sticky;top:0;background:#111827;color:#fff;padding:10px 20px;display:flex;justify-content:space-between;align-items:center;z-index:10;}
.toolbar button,.toolbar a{background:#2563eb;color:#fff;border:0;border-radius:6px;padding:8px 16px;font-size:14px;cursor:pointer;text-decoration:none;font-family:inherit;}
.sheet{max-width:1000px;margin:20px auto;background:#fff;padding:24px;box-shadow:0 2px 16px rgba(0,0,0,.12);}
h1{font-size:20px;margin:0 0 2px;}
.subtitle{color:#6b7280;font-size:13px;margin-bottom:16px;}
.meta{color:#9ca3af;font-size:11px;margin-bottom:14px;}
table{width:100%;border-collapse:collapse;font-size:12.5px;}
th,td{border:1px solid #d1d5db;padding:7px 9px;text-align:right;vertical-align:top;}
th{background:#f3f4f6;font-weight:700;font-size:12px;}
tr:nth-child(even) td{background:#fafbfc;}
.empty{text-align:center;color:#9ca3af;padding:30px;}
@page{size:A4;margin:12mm;}
@media print{
  body{background:#fff;}
  .toolbar{display:none;}
  .sheet{box-shadow:none;margin:0;max-width:none;padding:0;}
  th{background:#f3f4f6 !important;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
}
</style>
</head>
<body>
<div class="toolbar">
    <span><?= e($title) ?></span>
    <div style="display:flex;gap:8px;">
        <button onclick="window.print()">🖨️ طباعة / حفظ PDF</button>
        <a href="javascript:window.close()">إغلاق</a>
    </div>
</div>
<div class="sheet">
    <h1><?= e($title) ?></h1>
    <?php if ($subtitle): ?><div class="subtitle"><?= e($subtitle) ?></div><?php endif; ?>
    <div class="meta">عدد السجلات: <?= count($rows) ?> · تاريخ التصدير: <?= format_date(date('Y-m-d H:i'), 'Y-m-d H:i') ?></div>
    <?php if (!$rows): ?>
        <div class="empty">لا توجد سجلات للتصدير.</div>
    <?php else: ?>
    <table>
        <thead><tr><?php foreach ($headers as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr><?php foreach ($row as $cell): ?><td><?= e((string) ($cell ?? '')) ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<script>window.addEventListener('load', function(){ /* جاهزة للطباعة يدوياً */ });</script>
</body>
</html>

<?php
/** @var array $employee @var ?array $company @var ?string $qrSvg */
$primary = $company['primary_color'] ?? '#2563eb';
$photoUrl = $employee['photo'] ? route('/media/employees/' . $employee['company_id'] . '/' . $employee['photo']) : null;
$logoUrl = !empty($company['logo']) ? route('/media/companies/' . $company['logo']) : null;
$empNo = 'EMP-' . str_pad((string) $employee['id'], 4, '0', STR_PAD_LEFT);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>البطاقة الشخصية - <?= e($employee['full_name']) ?></title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap');
*{box-sizing:border-box;}
body{margin:0;background:#e5e7eb;font-family:'Cairo','Segoe UI',Tahoma,Arial,sans-serif;color:#1f2937;}
.toolbar{position:sticky;top:0;background:#111827;color:#fff;padding:10px 20px;display:flex;justify-content:space-between;align-items:center;z-index:10;gap:8px;flex-wrap:wrap;}
.toolbar button,.toolbar a{background:#2563eb;color:#fff;border:0;border-radius:6px;padding:8px 16px;font-size:14px;cursor:pointer;text-decoration:none;font-family:inherit;}
.stage{display:flex;justify-content:center;padding:40px 12px;}
/* مقاس البطاقة الحقيقي CR80: 85.6×54مم - تُكبَّر للعرض على الشاشة فقط */
.card{
  width:85.6mm;height:54mm;border-radius:3mm;overflow:hidden;position:relative;
  background:#fff;box-shadow:0 8px 30px rgba(0,0,0,.25);
  transform:scale(2);transform-origin:top center;
  -webkit-print-color-adjust:exact;print-color-adjust:exact;
}
.band{
  height:16mm;background:linear-gradient(135deg,<?= e($primary) ?>,color-mix(in srgb,<?= e($primary) ?> 60%,#000));
  display:flex;align-items:center;gap:2.4mm;padding:0 4mm;color:#fff;
}
.band img{width:9mm;height:9mm;border-radius:2mm;object-fit:cover;background:#fff;}
.band .org{font-size:3.6mm;font-weight:800;line-height:1.3;}
.band .tag{font-size:2.3mm;opacity:.85;}
.body{display:flex;gap:3.5mm;padding:3mm 4mm 0;}
.photo{
  width:17mm;height:21mm;border-radius:1.5mm;object-fit:cover;flex-shrink:0;
  border:.5mm solid <?= e($primary) ?>;margin-top:-6mm;background:#f3f4f6;
}
.photo-ph{
  width:17mm;height:21mm;border-radius:1.5mm;flex-shrink:0;display:flex;align-items:center;justify-content:center;
  border:.5mm solid <?= e($primary) ?>;margin-top:-6mm;background:#f3f4f6;font-size:8mm;
}
.info{flex:1;min-width:0;padding-top:.5mm;}
.name{font-size:3.9mm;font-weight:800;line-height:1.25;}
.job{font-size:2.8mm;color:<?= e($primary) ?>;font-weight:700;}
.meta{margin-top:1.4mm;font-size:2.4mm;line-height:1.75;color:#374151;}
.meta b{color:#6b7280;font-weight:600;}
.qr{position:absolute;bottom:2.6mm;left:3.5mm;width:13mm;height:13mm;line-height:0;color:#111827;}
.qr svg{width:13mm !important;height:13mm !important;}
.footer{
  position:absolute;bottom:0;right:0;left:0;height:2.2mm;
  background:linear-gradient(90deg,<?= e($primary) ?>,color-mix(in srgb,<?= e($primary) ?> 55%,#000));
}
.empno{position:absolute;bottom:3.4mm;right:4mm;font-size:2.5mm;font-weight:700;color:#6b7280;letter-spacing:.3mm;direction:ltr;}
@page{size:85.6mm 54mm;margin:0;}
@media print{
  body{background:#fff;}
  .toolbar{display:none;}
  .stage{padding:0;display:block;}
  .card{transform:none;box-shadow:none;border-radius:0;margin:0;}
}
</style>
</head>
<body>
<div class="toolbar">
    <span>🪪 البطاقة الشخصية — <?= e($employee['full_name']) ?></span>
    <div style="display:flex;gap:8px;">
        <button onclick="window.print()">⬇️ حفظ PDF / طباعة</button>
        <a href="<?= route('/employees/' . $employee['id']) ?>">← الملف الوظيفي</a>
    </div>
</div>
<div class="stage">
    <div class="card">
        <div class="band">
            <?php if ($logoUrl): ?><img src="<?= e($logoUrl) ?>" alt=""><?php endif; ?>
            <div>
                <div class="org"><?= e($company['name'] ?? '') ?></div>
                <div class="tag">بطاقة تعريف موظف</div>
            </div>
        </div>
        <div class="body">
            <?php if ($photoUrl): ?>
                <img class="photo" src="<?= e($photoUrl) ?>" alt="">
            <?php else: ?>
                <div class="photo-ph">👤</div>
            <?php endif; ?>
            <div class="info">
                <div class="name"><?= e($employee['full_name']) ?></div>
                <?php if ($employee['job_title']): ?><div class="job"><?= e($employee['job_title']) ?></div><?php endif; ?>
                <div class="meta">
                    <?php if ($employee['department']): ?><div><b>القسم:</b> <?= e($employee['department']) ?></div><?php endif; ?>
                    <?php if ($employee['phone']): ?><div><b>الجوال:</b> <span dir="ltr"><?= e($employee['phone']) ?></span></div><?php endif; ?>
                    <?php if ($employee['hire_date']): ?><div><b>الالتحاق:</b> <?= format_date($employee['hire_date'], 'Y-m-d') ?></div><?php endif; ?>
                </div>
            </div>
        </div>
        <?php if ($qrSvg): ?><div class="qr" title="امسح لحفظ جهة الاتصال"><?= $qrSvg ?></div><?php endif; ?>
        <div class="empno"><?= e($empNo) ?></div>
        <div class="footer"></div>
    </div>
</div>
</body>
</html>

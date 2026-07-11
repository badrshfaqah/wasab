<?php
$coreFeatures = [
    ['name' => 'الشركات والمستخدمون', 'description' => 'دعم عدة شركات ضمن نفس النظام، كل شركة بمستخدميها وشعارها وألوانها الخاصة.'],
    ['name' => 'الأدوار والصلاحيات', 'description' => 'صلاحيات دقيقة لكل ميزة، وأدوار مخصصة لكل شركة يحدد بها مدير الشركة من يرى ماذا.'],
    ['name' => 'التقويم الموحّد', 'description' => 'يجمع كل المواعيد والمهام والملفات المنتهية من كل الإضافات المفعّلة في تقويم واحد، مع أحداث خاصة بالشركة وتذكير تلقائي قبلها.'],
    ['name' => 'الإشعارات وسجل العمليات', 'description' => 'إشعارات فورية داخل النظام لكل حدث يخص المستخدم، وسجل كامل قابل للمراجعة لكل عملية تمت.'],
];
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="وصاب: نظام إداري متكامل وخفيف لإدارة أعمال الشركات الصغيرة والمتوسطة - مهام، هاتف، مستندات، أرشيف، اجتماعات، وتقويم موحّد.">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<style>
body{background:var(--bg);}
.wasab-hero{max-width:900px;margin:0 auto;padding:60px 20px 20px;text-align:center;}
.wasab-hero h1{font-size:34px;margin:0 0 12px;color:var(--primary);}
.wasab-hero p{font-size:16px;color:var(--muted);max-width:640px;margin:0 auto;line-height:1.9;}
.wasab-wrap{max-width:900px;margin:0 auto;padding:20px;}
.wasab-section-title{font-size:20px;font-weight:700;margin:36px 0 16px;text-align:center;}
.wasab-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px;}
.wasab-feature{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:18px;}
.wasab-feature h3{margin:0 0 6px;font-size:15px;}
.wasab-feature p{margin:0;font-size:13px;color:var(--muted);line-height:1.8;}
</style>
</head>
<body>

<div class="wasab-hero">
    <h1>👋 وصاب</h1>
    <p>نظام إداري متكامل وخفيف الوزن لإدارة أعمال الشركات الصغيرة والمتوسطة، يعمل على أي استضافة PHP/MySQL عادية بدون تعقيد تقني، بواجهة عربية كاملة وهيكلة إضافات مرنة تنمو مع احتياج شركتك.</p>
</div>

<div class="wasab-wrap">
    <h2 class="wasab-section-title">ميزات النواة</h2>
    <div class="wasab-grid">
        <?php foreach ($coreFeatures as $f): ?>
            <div class="wasab-feature">
                <h3><?= e($f['name']) ?></h3>
                <p><?= e($f['description']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <h2 class="wasab-section-title">الإضافات المتوفرة</h2>
    <div class="wasab-grid">
        <?php foreach ($modules as $m): ?>
            <div class="wasab-feature">
                <h3><?= e($m['name'] ?? $m['key']) ?></h3>
                <p><?= e($m['description'] ?? '') ?></p>
            </div>
        <?php endforeach; ?>
        <?php if (!$modules): ?>
            <p class="hint" style="grid-column:1/-1;text-align:center;">لا توجد إضافات متوفرة حالياً.</p>
        <?php endif; ?>
    </div>

    <h2 class="wasab-section-title">سجل التحديثات</h2>
    <?php foreach ($changelog as $entry): ?>
        <div class="card">
            <div class="card-title">
                <span>إصدار <?= e($entry['version']) ?></span>
                <span class="hint"><?= e($entry['date']) ?></span>
            </div>
            <ul style="margin:8px 0 0;padding-inline-start:20px;line-height:1.9;">
                <?php foreach ($entry['changes'] as $change): ?>
                    <li><?= e($change) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
    <?php if (!$changelog): ?>
        <div class="empty-state"><div class="ic">📋</div>لا يوجد سجل تحديثات بعد.</div>
    <?php endif; ?>
</div>

<footer class="app-footer">
    <span>إصدار <?= e(\App\Core\Wasab::currentVersion()) ?></span>
    <span>·</span>
    <a href="https://almgrat.com" target="_blank" rel="noopener">وصاب</a>
</footer>
</body>
</html>

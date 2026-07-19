<?php
/**
 * صفحة "وصاب" التعريفية العامة - مبنية بهوية "المجرات" البصرية (ar.almgrat.com):
 * نفس الألوان (أزرق #2563EB + بنفسجي #7C3AED)، خط Cairo، خلفية شبكية خفيفة،
 * وسوم أقسام نابضة، وفوتر داكن بشعار المجرات المتدرج. الصفحة مستقلة بالكامل
 * عن app.css حتى لا تتسرب أنماط لوحة التحكم الداخلية إليها.
 */

$coreFeatures = [
    ['icon' => '🏢', 'name' => 'الشركات والمستخدمون', 'description' => 'دعم عدة شركات ضمن نفس النظام، كل شركة بمستخدميها وشعارها وألوانها الخاصة.'],
    ['icon' => '🔐', 'name' => 'الأدوار والصلاحيات', 'description' => 'صلاحيات دقيقة لكل ميزة، وأدوار مخصصة لكل شركة يحدد بها مدير الشركة من يرى ماذا.'],
    ['icon' => '📅', 'name' => 'التقويم الموحّد', 'description' => 'يجمع كل المواعيد والمهام والملفات المنتهية من كل الإضافات المفعّلة في تقويم واحد، مع أحداث خاصة بالشركة وتذكير تلقائي قبلها.'],
    ['icon' => '🔎', 'name' => 'البحث الموحّد', 'description' => 'شريط بحث واحد بأعلى كل صفحة يجمع النتائج من كل الإضافات المفعّلة دفعة واحدة، كل نتيجة حسب صلاحية المستخدم.'],
    ['icon' => '📊', 'name' => 'التقارير', 'description' => 'صفحة أرقام مجمّعة لمدير الشركة من كل إضافة مفعّلة، لمتابعة أداء الشركة من مكان واحد.'],
    ['icon' => '🔔', 'name' => 'الإشعارات وسجل العمليات', 'description' => 'إشعارات فورية داخل النظام لكل حدث يخص المستخدم، وسجل كامل قابل للمراجعة لكل عملية تمت.'],
];

$screenshots = [
    ['file' => 'dashboard.png', 'label' => 'الرئيسية'],
    ['file' => 'tasks-board.png', 'label' => 'لوحة المهام (كانبان)'],
    ['file' => 'employees.png', 'label' => 'الملف الوظيفي'],
    ['file' => 'reports.png', 'label' => 'التقارير'],
    ['file' => 'search.png', 'label' => 'البحث الموحّد'],
];

$moduleIcons = [
    'tasks' => '📋',
    'employees' => '👥',
    'meetings' => '🤝',
    'documents' => '📄',
    'archive' => '🗃️',
    'phone' => '☎️',
];

$currentVersion = \App\Core\Wasab::currentVersion();
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="وصاب: نظام إداري متكامل وخفيف لإدارة أعمال الشركات الصغيرة والمتوسطة - مهام، هاتف، مستندات، أرشيف، اجتماعات، وتقويم موحّد. من تطوير المجرات.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap">
<style>
:root {
    --primary:        #2563EB;
    --primary-light:  #3B82F6;
    --green:          #059669;
    --accent:         #7C3AED;
    --dark:           #0F172A;
    --bg:             #FFFFFF;
    --bg-2:           #F8FAFF;
    --bg-3:           #F1F5FF;
    --card-bg:        #FFFFFF;
    --border:         #E2E8F0;
    --border-hover:   #93C5FD;
    --text:           #1E293B;
    --text-muted:     #64748B;
    --text-dim:       #94A3B8;
    --gradient-1:     linear-gradient(135deg, #2563EB 0%, #7C3AED 100%);
    --shadow-md:      0 4px 16px rgba(37,99,235,0.10);
    --shadow-card:    0 2px 12px rgba(0,0,0,0.06);
    --radius-sm:      6px;
    --radius-md:      10px;
    --radius-lg:      16px;
    --transition:     all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    --font-main:      'Cairo', 'Tajawal', 'Segoe UI', Tahoma, sans-serif;
    --font-mono:      'Courier New', 'Fira Code', monospace;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
    font-family: var(--font-main);
    background: var(--bg);
    color: var(--text);
    direction: rtl;
    text-align: right;
    line-height: 1.7;
    overflow-x: hidden;
    -webkit-font-smoothing: antialiased;
}
img { max-width: 100%; height: auto; display: block; }
a { color: var(--primary); text-decoration: none; transition: var(--transition); }
a:hover { color: var(--accent); }

/* شبكة الخلفية الخفيفة - سمة المجرات المميزة */
body::before {
    content: '';
    position: fixed; inset: 0;
    background-image:
        linear-gradient(rgba(37,99,235,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(37,99,235,0.03) 1px, transparent 1px);
    background-size: 48px 48px;
    pointer-events: none;
    z-index: 0;
}

.container { width: 100%; max-width: 1100px; margin: 0 auto; padding: 0 24px; position: relative; z-index: 1; }
.section-padding { padding: 72px 0; }

/* ── الأزرار ── */
.btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 11px 26px;
    border-radius: var(--radius-sm);
    font-size: 14px; font-weight: 600; font-family: var(--font-main);
    transition: var(--transition); cursor: pointer; border: none; text-decoration: none;
}
.btn-primary { background: var(--primary); color: #fff; box-shadow: 0 4px 14px rgba(37,99,235,0.3); }
.btn-primary:hover { background: #1D4ED8; color: #fff; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(37,99,235,0.4); }
.btn-outline { background: transparent; color: var(--primary); border: 1.5px solid var(--primary); }
.btn-outline:hover { background: rgba(37,99,235,0.06); color: var(--primary); transform: translateY(-1px); }

/* ── الترويسة ── */
.site-header {
    position: sticky; top: 0; z-index: 50;
    background: rgba(255,255,255,0.85);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
}
.header-inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 0; }
.header-logo { display: flex; align-items: center; gap: 10px; }
.header-logo img { height: 40px; width: auto; }
.version-pill {
    font-size: 11px; font-weight: 700; color: var(--primary);
    background: rgba(37,99,235,0.07); border: 1px solid rgba(37,99,235,0.15);
    border-radius: 50px; padding: 2px 10px;
}
.header-actions { display: flex; align-items: center; gap: 10px; }

/* ── الهيرو ── */
.hero {
    position: relative; overflow: hidden;
    padding: 72px 0 64px;
    text-align: center;
    background: linear-gradient(160deg, #fff 0%, #EFF6FF 50%, #F5F3FF 100%);
}
.hero-glow-right {
    position: absolute; top: -150px; right: -150px;
    width: 500px; height: 500px;
    background: radial-gradient(circle, rgba(37,99,235,0.08) 0%, transparent 70%);
    pointer-events: none;
}
.hero-glow-left {
    position: absolute; bottom: -150px; left: -150px;
    width: 500px; height: 500px;
    background: radial-gradient(circle, rgba(124,58,237,0.07) 0%, transparent 70%);
    pointer-events: none;
}
.hero-badge {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 13px; font-weight: 600; color: var(--primary);
    margin-bottom: 24px; padding: 7px 16px;
    border: 1px solid rgba(37,99,235,0.2); border-radius: 50px;
    background: rgba(37,99,235,0.06);
    animation: fadeInDown 0.5s ease;
}
.hero-badge .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--green); animation: pulse 2s infinite; }
.hero-logo { max-width: 200px; margin: 0 auto 20px; animation: fadeInUp 0.5s ease both; }
.hero-title {
    font-size: clamp(28px, 4.5vw, 46px);
    font-weight: 900; line-height: 1.3; color: var(--dark);
    margin-bottom: 16px;
    animation: fadeInUp 0.5s ease 0.1s both;
}
.hero-title .accent {
    background: var(--gradient-1);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.hero-desc {
    font-size: 16px; color: var(--text-muted); max-width: 640px;
    margin: 0 auto 32px; line-height: 2;
    animation: fadeInUp 0.5s ease 0.2s both;
}
.hero-actions { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; animation: fadeInUp 0.5s ease 0.3s both; }
.hero-stats {
    display: flex; justify-content: center; gap: 40px; flex-wrap: wrap;
    margin-top: 44px;
    animation: fadeInUp 0.5s ease 0.4s both;
}
.hero-stat { text-align: center; }
.hero-stat b {
    display: block; font-size: 26px; font-weight: 800;
    background: var(--gradient-1);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.hero-stat span { font-size: 12px; color: var(--text-muted); font-weight: 600; }

/* ── وسم القسم النابض - سمة المجرات ── */
.section-header { text-align: center; margin-bottom: 44px; }
.section-tag {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 12px; font-weight: 700; letter-spacing: 1.5px;
    color: var(--primary); margin-bottom: 12px; padding: 5px 14px;
    background: rgba(37,99,235,0.07); border: 1px solid rgba(37,99,235,0.15);
    border-radius: 50px;
}
.section-tag::before {
    content: ''; width: 6px; height: 6px; border-radius: 50%;
    background: var(--primary); animation: pulse 2s infinite;
}
.section-title { font-size: clamp(22px, 3vw, 32px); font-weight: 800; color: var(--dark); margin-bottom: 8px; }
.section-subtitle { font-size: 14px; color: var(--text-muted); max-width: 560px; margin: 0 auto; }

/* ── لقطات الشاشة ── */
.shots-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
.shot-card {
    background: var(--card-bg); border: 1px solid var(--border);
    border-radius: var(--radius-lg); overflow: hidden;
    box-shadow: var(--shadow-card); transition: var(--transition);
}
.shot-card:hover { border-color: var(--border-hover); box-shadow: var(--shadow-md); transform: translateY(-3px); }
.shot-card img { width: 100%; border-bottom: 1px solid var(--border); }
.shot-card span { display: block; padding: 12px 16px; font-size: 13px; font-weight: 700; text-align: center; color: var(--dark); }

/* ── بطاقات الميزات والإضافات ── */
.features-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 18px; }
.feature-card {
    background: var(--card-bg); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 22px;
    box-shadow: var(--shadow-card); transition: var(--transition);
    position: relative; overflow: hidden;
}
.feature-card::before {
    content: ''; position: absolute; top: 0; right: 0; left: 0; height: 3px;
    background: var(--gradient-1); opacity: 0; transition: var(--transition);
}
.feature-card:hover { border-color: var(--border-hover); box-shadow: var(--shadow-md); transform: translateY(-3px); }
.feature-card:hover::before { opacity: 1; }
.feature-icon {
    width: 44px; height: 44px; display: flex; align-items: center; justify-content: center;
    font-size: 22px; border-radius: var(--radius-md);
    background: var(--bg-3); border: 1px solid var(--border);
    margin-bottom: 14px;
}
.feature-card h3 { font-size: 15px; font-weight: 700; color: var(--dark); margin-bottom: 6px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.feature-card p { font-size: 13px; color: var(--text-muted); line-height: 1.9; }
.module-version {
    font-size: 10px; font-weight: 700; color: var(--text-dim);
    font-family: var(--font-mono); direction: ltr;
    background: var(--bg-2); border: 1px solid var(--border);
    border-radius: 50px; padding: 1px 8px;
}
.empty-note { grid-column: 1 / -1; text-align: center; color: var(--text-muted); font-size: 14px; padding: 24px; }

/* ── سجل التحديثات ── */
.changelog { max-width: 760px; margin: 0 auto; position: relative; padding-right: 24px; }
.changelog::before {
    content: ''; position: absolute; top: 8px; bottom: 8px; right: 5px;
    width: 2px; background: linear-gradient(180deg, var(--primary), var(--accent));
    border-radius: 2px; opacity: 0.35;
}
.changelog-entry { position: relative; margin-bottom: 22px; }
.changelog-entry::before {
    content: ''; position: absolute; top: 22px; right: -23px;
    width: 10px; height: 10px; border-radius: 50%;
    background: var(--primary); border: 2px solid #fff;
    box-shadow: 0 0 0 2px rgba(37,99,235,0.25);
}
.changelog-entry:first-child::before { background: var(--green); box-shadow: 0 0 0 2px rgba(5,150,105,0.25); }
.changelog-card {
    background: var(--card-bg); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 18px 22px;
    box-shadow: var(--shadow-card);
}
.changelog-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; }
.changelog-version { font-size: 15px; font-weight: 800; color: var(--dark); }
.changelog-version .latest {
    font-size: 10px; font-weight: 700; color: var(--green);
    background: rgba(5,150,105,0.07); border: 1px solid rgba(5,150,105,0.2);
    border-radius: 50px; padding: 2px 10px; margin-right: 8px;
}
.changelog-date { font-size: 12px; color: var(--text-dim); font-family: var(--font-mono); direction: ltr; }
.changelog-card ul { margin: 0; padding-right: 18px; }
.changelog-card li { font-size: 13px; color: var(--text-muted); line-height: 1.9; margin-bottom: 4px; }

/* ── الفوتر الداكن - هوية المجرات ── */
.site-footer { background: var(--dark); border-top: 1px solid #1E293B; padding: 48px 0 0; position: relative; z-index: 1; margin-top: 24px; }
.footer-main { display: flex; align-items: flex-start; justify-content: space-between; gap: 32px; flex-wrap: wrap; padding-bottom: 36px; }
.footer-brand { max-width: 380px; }
.footer-logo-name-ar {
    font-size: 20px; font-weight: 800;
    background: var(--gradient-1);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.footer-logo-name-en {
    font-size: 9px; font-weight: 600; color: #64748B;
    letter-spacing: 3px; text-transform: uppercase; font-family: var(--font-mono);
    display: block; margin-top: 2px;
}
.footer-brand-desc { color: #64748B; font-size: 13px; line-height: 1.9; margin-top: 14px; }
.footer-col-title { font-size: 11px; font-weight: 700; color: #cbd5e1; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 14px; }
.footer-links { display: flex; flex-direction: column; gap: 10px; }
.footer-links a { font-size: 13px; color: #e2e8f0; }
.footer-links a:hover { color: #fff; }
.footer-bottom {
    border-top: 1px solid #1E293B; padding: 18px 0;
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;
}
.footer-copyright { font-size: 12px; color: #94a3b8; }
.footer-version { font-size: 12px; color: #94a3b8; font-family: var(--font-mono); direction: ltr; }

@keyframes fadeInUp   { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
@keyframes fadeInDown { from { opacity: 0; transform: translateY(-14px); } to { opacity: 1; transform: translateY(0); } }
@keyframes pulse      { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(0.85); } }

@media (max-width: 640px) {
    .section-padding { padding: 48px 0; }
    .hero { padding: 48px 0 40px; }
    .hero-stats { gap: 24px; }
    .header-actions .btn { padding: 9px 16px; font-size: 13px; }
    .footer-main { flex-direction: column; }
}
</style>
</head>
<body>

<header class="site-header">
    <div class="container header-inner">
        <div class="header-logo">
            <img src="<?= asset('img/wasab-logo.png') ?>" alt="شعار وصاب">
            <span class="version-pill">v<?= e($currentVersion) ?></span>
        </div>
        <div class="header-actions">
            <a class="btn btn-outline" href="https://ar.almgrat.com" target="_blank" rel="noopener">موقع المجرات</a>
            <a class="btn btn-primary" href="<?= route('login') ?>">دخول النظام</a>
        </div>
    </div>
</header>

<section class="hero">
    <div class="hero-glow-right"></div>
    <div class="hero-glow-left"></div>
    <div class="container">
        <div class="hero-badge"><span class="dot"></span> أحد منتجات المجرات · نبرمج ونبني بلا حدود</div>
        <img class="hero-logo" src="<?= asset('img/wasab-logo.png') ?>" alt="شعار وصاب">
        <h1 class="hero-title">نظام إداري متكامل <span class="accent">خفيف ومرن</span> لإدارة أعمال شركتك</h1>
        <p class="hero-desc">يعمل على أي استضافة PHP/MySQL عادية بدون تعقيد تقني، بواجهة عربية كاملة وهيكلة إضافات مرنة تنمو مع احتياج شركتك — مهام، اجتماعات، مستندات، أرشيف، ملفات وظيفية، وهاتف داخلي.</p>
        <div class="hero-actions">
            <a class="btn btn-primary" href="<?= route('login') ?>">دخول النظام</a>
            <a class="btn btn-outline" href="https://ar.almgrat.com/contact" target="_blank" rel="noopener">اطلب نسختك</a>
        </div>
        <div class="hero-stats">
            <div class="hero-stat"><b><?= count($modules) ?></b><span>إضافات جاهزة</span></div>
            <div class="hero-stat"><b><?= count($coreFeatures) ?></b><span>ميزات بالنواة</span></div>
            <div class="hero-stat"><b>100%</b><span>عربي RTL</span></div>
            <div class="hero-stat"><b>0</b><span>تعقيد تقني</span></div>
        </div>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <div class="section-header">
            <div class="section-tag">لمحة من الداخل</div>
            <h2 class="section-title">شاهد النظام على الطبيعة</h2>
            <p class="section-subtitle">صور حقيقية من واجهة النظام كما يراها المستخدم.</p>
        </div>
        <div class="shots-grid">
            <?php foreach ($screenshots as $s): ?>
                <div class="shot-card">
                    <img src="<?= asset('img/screenshots/' . $s['file']) ?>" alt="<?= e($s['label']) ?>" loading="lazy">
                    <span><?= e($s['label']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section-padding" style="background:var(--bg-2);border-top:1px solid var(--border);border-bottom:1px solid var(--border);">
    <div class="container">
        <div class="section-header">
            <div class="section-tag">النواة</div>
            <h2 class="section-title">ميزات مدمجة في قلب النظام</h2>
            <p class="section-subtitle">تعمل تلقائياً مع كل إضافة تفعّلها، بلا أي إعداد إضافي.</p>
        </div>
        <div class="features-grid">
            <?php foreach ($coreFeatures as $f): ?>
                <div class="feature-card">
                    <div class="feature-icon"><?= $f['icon'] ?></div>
                    <h3><?= e($f['name']) ?></h3>
                    <p><?= e($f['description']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <div class="section-header">
            <div class="section-tag">الإضافات</div>
            <h2 class="section-title">فعّل ما تحتاجه فقط</h2>
            <p class="section-subtitle">كل إضافة وحدة مستقلة تُثبَّت وتُفعَّل وتُعطَّل من لوحة التحكم دون المساس بباقي النظام.</p>
        </div>
        <div class="features-grid">
            <?php foreach ($modules as $m): ?>
                <div class="feature-card">
                    <div class="feature-icon"><?= $moduleIcons[$m['key'] ?? ''] ?? '🧩' ?></div>
                    <h3>
                        <?= e($m['name'] ?? $m['key']) ?>
                        <?php if (!empty($m['version'])): ?>
                            <span class="module-version">v<?= e($m['version']) ?></span>
                        <?php endif; ?>
                    </h3>
                    <p><?= e($m['description'] ?? '') ?></p>
                </div>
            <?php endforeach; ?>
            <?php if (!$modules): ?>
                <p class="empty-note">لا توجد إضافات متوفرة حالياً.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section-padding" style="background:var(--bg-2);border-top:1px solid var(--border);">
    <div class="container">
        <div class="section-header">
            <div class="section-tag">التطوير مستمر</div>
            <h2 class="section-title">سجل التحديثات</h2>
        </div>
        <?php if ($changelog): ?>
            <div class="changelog">
                <?php foreach ($changelog as $i => $entry): ?>
                    <div class="changelog-entry">
                        <div class="changelog-card">
                            <div class="changelog-head">
                                <span class="changelog-version">
                                    إصدار <?= e($entry['version']) ?>
                                    <?php if ($i === 0): ?><span class="latest">الحالي</span><?php endif; ?>
                                </span>
                                <span class="changelog-date"><?= e($entry['date']) ?></span>
                            </div>
                            <ul>
                                <?php foreach ($entry['changes'] as $change): ?>
                                    <li><?= e($change) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty-note">لا يوجد سجل تحديثات بعد.</p>
        <?php endif; ?>
    </div>
</section>

<footer class="site-footer">
    <div class="container">
        <div class="footer-main">
            <div class="footer-brand">
                <span class="footer-logo-name-ar">المجرات</span>
                <span class="footer-logo-name-en">ALMGRAT</span>
                <p class="footer-brand-desc">"وصاب" أحد منتجات شركة المجرات لتطوير البرمجيات — نبرمج ونبني بلا حدود. أنظمة ذكية مخصصة تُبنى لتناسب عملك، لا العكس.</p>
            </div>
            <div>
                <div class="footer-col-title">روابط</div>
                <div class="footer-links">
                    <a href="<?= route('login') ?>">دخول النظام</a>
                    <a href="https://ar.almgrat.com" target="_blank" rel="noopener">موقع المجرات</a>
                    <a href="https://ar.almgrat.com/products/wasab-system/" target="_blank" rel="noopener">وصاب في موقع المجرات</a>
                    <a href="https://ar.almgrat.com/contact" target="_blank" rel="noopener">تواصل معنا</a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <span class="footer-copyright">© <?= date('Y') ?> المجرات — جميع الحقوق محفوظة</span>
            <span class="footer-version">وصاب v<?= e($currentVersion) ?></span>
        </div>
    </div>
</footer>

</body>
</html>

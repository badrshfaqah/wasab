<?php
/**
 * سياسة خصوصية تطبيق وصاب للجوال - صفحة عامة مستقلة بهوية "المجرات" البصرية
 * نفسها المستخدمة في wasab/index.php (أزرق #2563EB + بنفسجي #7C3AED، خط Cairo)،
 * لكنها مبنية للقراءة الطويلة لا للتسويق. مستقلة عن app.css عمداً.
 *
 * المحتوى يطابق حرفياً ما يصرّح به wasab/PrivacyInfo.xcprivacy في مستودع
 * التطبيق - أي تعديل على أحدهما يستوجب تعديل الآخر، وإلا رفضت آبل المراجعة.
 */

// جهة التواصل المعلنة لمتجر آبل - غيّرها هنا فقط إن تغيّر البريد.
$contactEmail = 'info@devco.sa';
$lastUpdated  = '٢٨ أغسطس ٢٠٢٦';

$collected = [
    [
        'icon' => '👤',
        'title' => 'الاسم والبريد الإلكتروني',
        'why'   => 'لتعريف حسابك داخل نظام شركتك وعرض اسمك على ما تنشئه من مهام واجتماعات ومستندات.',
    ],
    [
        'icon' => '📱',
        'title' => 'رقم الجوال',
        'why'   => 'يظهر في بطاقة الموظف ودليل جهات التواصل ليتمكن زملاؤك من الوصول إليك. مصدره ملفك الوظيفي على الخادم، لا من جهات الاتصال في جهازك.',
    ],
    [
        'icon' => '📍',
        'title' => 'الموقع الجغرافي',
        'why'   => 'يُقرأ في اللحظة التي تضغط فيها «تسجيل حضور» أو «انصراف» فقط، لتوثيق مكان التسجيل. لا يعمل في الخلفية، ولا يُتتبّع مسارك، ولا يُقرأ في أي شاشة أخرى.',
    ],
    [
        'icon' => '🖼️',
        'title' => 'الصور والكاميرا',
        'why'   => 'عند اختيارك صورة أو تصوير مستند لرفعه إلى الأرشيف. لا يُقرأ ألبومك ولا يُفهرس — تصل الصورة التي تختارها أنت وحدها.',
    ],
    [
        'icon' => '📝',
        'title' => 'ما تكتبه داخل التطبيق',
        'why'   => 'المهام والاجتماعات والمستندات والملاحظات وبيانات العملاء — وهي مادة عمل شركتك التي أُنشئ التطبيق لإدارتها.',
    ],
];

$notDone = [
    'لا نتتبّعك: التطبيق لا يستخدم إطار App Tracking Transparency لأنه لا يتتبّع شيئاً أصلاً.',
    'لا إعلانات ولا مُعلنين، ولا ملفات تعريف إعلانية.',
    'لا أدوات تحليلات طرف ثالث — لا Google Analytics ولا Firebase ولا أي SDK لجمع السلوك.',
    'لا نبيع بياناتك ولا نشاركها مع أي جهة خارجية، بمقابل أو بلا مقابل.',
    'لا نصل إلى جهات اتصالك، ولا تقويمك، ولا ميكروفون جهازك، ولا رسائلك.',
];
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="سياسة خصوصية تطبيق وصاب للجوال: ما الذي يجمعه التطبيق، ولماذا، وأين يُخزَّن، وكيف تحذفه.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap">
<style>
:root {
    --primary:    #2563EB;
    --accent:     #7C3AED;
    --green:      #059669;
    --dark:       #0F172A;
    --bg:         #FFFFFF;
    --bg-2:       #F8FAFF;
    --border:     #E2E8F0;
    --text:       #1E293B;
    --text-muted: #64748B;
    --text-dim:   #94A3B8;
    --gradient-1: linear-gradient(135deg, #2563EB 0%, #7C3AED 100%);
    --shadow-card:0 2px 12px rgba(0,0,0,0.06);
    --radius-md:  10px;
    --radius-lg:  16px;
    --font-main:  'Cairo', 'Tajawal', 'Segoe UI', Tahoma, sans-serif;
    --font-mono:  'Courier New', 'Fira Code', monospace;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: var(--font-main); color: var(--text); background: var(--bg);
    line-height: 1.9; -webkit-font-smoothing: antialiased;
}
.container { max-width: 820px; margin: 0 auto; padding: 0 20px; }
a { color: var(--primary); }

/* ---------- الترويسة ---------- */
.site-header {
    border-bottom: 1px solid var(--border); background: rgba(255,255,255,0.9);
    backdrop-filter: blur(10px); position: sticky; top: 0; z-index: 10;
}
.header-inner { display: flex; align-items: center; justify-content: space-between; height: 64px; gap: 12px; }
.header-logo { display: flex; align-items: center; gap: 10px; }
.header-logo img { height: 30px; }
.version-pill {
    font-size: 11px; font-family: var(--font-mono); direction: ltr;
    color: var(--primary); background: #EFF6FF; border: 1px solid #DBEAFE;
    padding: 2px 8px; border-radius: 999px;
}
.btn {
    display: inline-block; padding: 9px 18px; border-radius: var(--radius-md);
    font-size: 14px; font-weight: 700; text-decoration: none; white-space: nowrap;
    transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
}
.btn-primary { background: var(--gradient-1); color: #fff; }
.btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }

/* ---------- العنوان ---------- */
.page-head {
    padding: 56px 0 40px; text-align: center;
    background: var(--bg-2); border-bottom: 1px solid var(--border);
}
.page-head h1 { font-size: 34px; font-weight: 900; letter-spacing: -0.5px; }
.page-head .lead { color: var(--text-muted); margin-top: 12px; font-size: 16px; }
.stamp {
    display: inline-block; margin-top: 18px; font-size: 12.5px; color: var(--text-dim);
    background: #fff; border: 1px solid var(--border); border-radius: 999px; padding: 5px 14px;
}

/* ---------- الخلاصة ---------- */
.tldr {
    margin: 40px 0; padding: 24px 26px; border-radius: var(--radius-lg);
    background: #F0FDF4; border: 1px solid #BBF7D0; border-right: 4px solid var(--green);
}
.tldr h2 { font-size: 17px; color: #065F46; margin-bottom: 8px; }
.tldr p { color: #047857; font-size: 15px; }

/* ---------- الأقسام ---------- */
section.block { padding: 8px 0 28px; }
h2.sec {
    font-size: 21px; font-weight: 800; margin: 30px 0 14px;
    padding-right: 14px; border-right: 4px solid var(--primary); line-height: 1.5;
}
p.para { color: var(--text-muted); margin-bottom: 14px; font-size: 15.5px; }
p.para strong { color: var(--text); font-weight: 700; }

ul.plain { list-style: none; margin: 10px 0 18px; }
ul.plain li {
    position: relative; padding-right: 26px; margin-bottom: 10px;
    color: var(--text-muted); font-size: 15.5px;
}
ul.plain li::before {
    content: '✕'; position: absolute; right: 0; top: 1px;
    color: #DC2626; font-weight: 800; font-size: 13px;
}
ul.plain.yes li::before { content: '✓'; color: var(--green); }

/* ---------- بطاقات ما يُجمع ---------- */
.data-card {
    display: flex; gap: 16px; align-items: flex-start;
    background: #fff; border: 1px solid var(--border); border-radius: var(--radius-lg);
    padding: 18px 20px; margin-bottom: 12px; box-shadow: var(--shadow-card);
}
.data-card .ico {
    font-size: 22px; line-height: 1; flex-shrink: 0;
    width: 44px; height: 44px; border-radius: 12px; background: var(--bg-2);
    display: flex; align-items: center; justify-content: center;
}
.data-card h3 { font-size: 16px; font-weight: 800; margin-bottom: 4px; }
.data-card p { color: var(--text-muted); font-size: 14.5px; line-height: 1.8; }

/* ---------- صندوق ملاحظة ---------- */
.note-box {
    background: var(--bg-2); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 20px 22px; margin: 18px 0;
}
.note-box p { color: var(--text-muted); font-size: 15px; }
.note-box p + p { margin-top: 10px; }

/* ---------- الفوتر ---------- */
.site-footer { background: var(--dark); color: #cbd5e1; margin-top: 60px; padding: 34px 0 22px; }
.footer-links { display: flex; gap: 18px; flex-wrap: wrap; margin-bottom: 18px; }
.footer-links a { color: #cbd5e1; text-decoration: none; font-size: 14px; }
.footer-links a:hover { color: #fff; }
.footer-bottom {
    border-top: 1px solid #1E293B; padding-top: 16px; display: flex;
    justify-content: space-between; flex-wrap: wrap; gap: 10px;
}
.footer-copyright, .footer-version { font-size: 12px; color: #94a3b8; }
.footer-version { font-family: var(--font-mono); direction: ltr; }

.ltr { direction: ltr; unicode-bidi: isolate; display: inline-block; }

@media (max-width: 640px) {
    .page-head { padding: 40px 0 30px; }
    .page-head h1 { font-size: 26px; }
    h2.sec { font-size: 19px; }
    .data-card { flex-direction: column; gap: 10px; }
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
        <a class="btn btn-primary" href="<?= route('wasab') ?>">عن وصاب</a>
    </div>
</header>

<div class="page-head">
    <div class="container">
        <h1>سياسة الخصوصية</h1>
        <p class="lead">تطبيق «وصاب» للجوال — <span class="ltr">iPhone</span> و<span class="ltr">iPad</span></p>
        <span class="stamp">آخر تحديث: <?= e($lastUpdated) ?></span>
    </div>
</div>

<div class="container">

    <div class="tldr">
        <h2>الخلاصة في سطرين</h2>
        <p>بياناتك تذهب إلى خادم شركتك أنت، لا إلى خوادمنا. لا نتتبّعك، ولا نعرض إعلانات، ولا نبيع شيئاً لأحد. وكل إذن يطلبه التطبيق اختياري وله سبب واحد واضح.</p>
    </div>

    <section class="block">
        <h2 class="sec">١. ما هو تطبيق وصاب</h2>
        <p class="para">«وصاب» نظام إداري تستضيفه كل شركة على خادمها الخاص. وهذا التطبيق ليس خدمة سحابية نشغّلها نحن، بل <strong>عميل (Client)</strong> يتصل بخادم شركتك أنت.</p>
        <p class="para">عند أول تشغيل يسألك التطبيق عن عنوان الخادم، ولا يتصل بغيره. أي بيانات تُرسلها أو تستقبلها تمرّ بينك وبين ذلك الخادم مباشرة — <strong>لا تمرّ بنا، ولا نحتفظ بنسخة منها، ولا نستطيع الاطلاع عليها.</strong></p>
        <div class="note-box">
            <p>ولهذا فإن <strong>مالك بياناتك هو شركتك</strong>، ومسؤول النظام لديها هو من يحدد من يرى ماذا، ومدة الاحتفاظ، وسياسة النسخ الاحتياطي.</p>
        </div>
    </section>

    <section class="block">
        <h2 class="sec">٢. ما الذي يجمعه التطبيق ولماذا</h2>
        <p class="para">لا يُجمع أي بيان إلا لأداء وظيفة تطلبها أنت. وهذه قائمة كاملة لا استثناء فيها:</p>
        <?php foreach ($collected as $item): ?>
            <div class="data-card">
                <div class="ico"><?= $item['icon'] ?></div>
                <div>
                    <h3><?= e($item['title']) ?></h3>
                    <p><?= e($item['why']) ?></p>
                </div>
            </div>
        <?php endforeach; ?>
        <p class="para">كل ما سبق مرتبط بحسابك داخل نظام شركتك ويُستخدم <strong>لتشغيل التطبيق فقط</strong> — لا للتسويق ولا للإعلان ولا لبناء ملف سلوكي عنك.</p>
    </section>

    <section class="block">
        <h2 class="sec">٣. ما الذي لا يفعله التطبيق</h2>
        <ul class="plain">
            <?php foreach ($notDone as $line): ?>
                <li><?= e($line) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="block">
        <h2 class="sec">٤. الأذونات وكيف تسحبها</h2>
        <p class="para">كل إذن يُطلب في سياقه، وللتطبيق أن يعمل بدونه مع تعطّل الميزة المرتبطة به وحدها:</p>
        <ul class="plain yes">
            <li><strong>الموقع</strong> — لحظة تسجيل الحضور أو الانصراف فقط. إن رفضته سُجّل حضورك بلا إحداثيات.</li>
            <li><strong>الكاميرا والصور</strong> — عند رفع مستند أو صورة إلى الأرشيف. إن رفضته بقيت بقية الشاشات تعمل.</li>
            <li><strong>Face ID / Touch ID</strong> — لقفل التطبيق إن فعّلت القفل من «ملفي». تتم المطابقة داخل جهازك بالكامل عبر نظام آبل، و<strong>بصمتك أو وجهك لا يصل إلينا ولا إلى خادم شركتك إطلاقاً</strong>.</li>
            <li><strong>الإشعارات</strong> — للتذكير بمهامك واجتماعاتك. التذكيرات تُجدول محلياً على جهازك.</li>
        </ul>
        <p class="para">يمكنك سحب أي إذن في أي وقت من: <span class="ltr">Settings → وصاب</span> في جهازك.</p>
    </section>

    <section class="block">
        <h2 class="sec">٥. كيف تُحمى بياناتك</h2>
        <ul class="plain yes">
            <li>الاتصال بالخادم مشفّر عبر <span class="ltr">HTTPS</span>.</li>
            <li>رمز دخولك يُحفظ في <span class="ltr">Keychain</span> — خزنة نظام آبل المشفّرة — لا كنص صريح.</li>
            <li>ما يُحفظ خارج الخزنة هو عنوان الخادم وتفضيل المظهر (نهاري/ليلي) فقط، ولا شيء شخصي فيهما.</li>
            <li>القفل الاختياري بـ <span class="ltr">Face ID</span> يمنع فتح التطبيق حتى لو كان جهازك مفتوحاً بيد غيرك.</li>
        </ul>
    </section>

    <section class="block">
        <h2 class="sec">٦. الاحتفاظ بالبيانات وحذفها</h2>
        <p class="para"><strong>على جهازك:</strong> حذف التطبيق يمسح رمز الدخول والتفضيلات فوراً، ولا يبقى منها شيء.</p>
        <p class="para"><strong>على خادم شركتك:</strong> بياناتك ملك شركتك، ومدة الاحتفاظ بها تحددها سياستها. لحذف حسابك أو أي بيان يخصّك، راجع مسؤول النظام في شركتك — فهو وحده من يملك صلاحية ذلك. وإن تعذّر عليك الوصول إليه، تواصل معنا وسنوجّهك.</p>
    </section>

    <section class="block">
        <h2 class="sec">٧. الأطفال</h2>
        <p class="para">التطبيق أداة عمل موجّهة لموظفي الشركات، وليس مخصصاً للأطفال، ولا نجمع بياناتهم عن قصد.</p>
    </section>

    <section class="block">
        <h2 class="sec">٨. تغييرات على هذه السياسة</h2>
        <p class="para">إن تغيّر ما يجمعه التطبيق، حُدّثت هذه الصفحة وتاريخ «آخر تحديث» أعلاها قبل نزول التغيير في المتجر.</p>
    </section>

    <section class="block">
        <h2 class="sec">٩. التواصل</h2>
        <p class="para">لأي سؤال أو طلب يخص خصوصيتك:</p>
        <ul class="plain yes">
            <li>بريد: <a class="ltr" href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a></li>
            <li>الموقع: <a href="https://ar.almgrat.com/contact" target="_blank" rel="noopener">ar.almgrat.com/contact</a></li>
        </ul>
    </section>

</div>

<footer class="site-footer">
    <div class="container">
        <div class="footer-links">
            <a href="<?= route('wasab') ?>">عن وصاب</a>
            <a href="<?= route('login') ?>">دخول النظام</a>
            <a href="https://ar.almgrat.com" target="_blank" rel="noopener">موقع المجرات</a>
            <a href="https://ar.almgrat.com/contact" target="_blank" rel="noopener">تواصل معنا</a>
        </div>
        <div class="footer-bottom">
            <span class="footer-copyright">© <?= date('Y') ?> المجرات — جميع الحقوق محفوظة</span>
            <span class="footer-version">وصاب v<?= e($currentVersion) ?></span>
        </div>
    </div>
</footer>

</body>
</html>

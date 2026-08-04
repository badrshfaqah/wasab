<?php /** @var string $appName @var string $appIcon */ ?>
<div class="page-head"><div><h1>تثبيت التطبيق على الجوال</h1><p>أضِف «<?= e($appName) ?>» إلى شاشة جوالك ليعمل كتطبيق مستقل مع إشعارات فورية.</p></div></div>

<style>
.inst-wrap{max-width:640px;}
.inst-hero{display:flex;align-items:center;gap:14px;margin-bottom:4px;}
.inst-hero img{width:64px;height:64px;border-radius:16px;box-shadow:0 2px 10px rgba(0,0,0,.12);}
.inst-benefits{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0 4px;}
.inst-benefits span{font-size:12.5px;background:var(--surface-2,#eef2f7);border:1px solid var(--border,#e5e7eb);border-radius:999px;padding:5px 11px;}
.inst-tabs{display:flex;gap:8px;margin:18px 0 16px;}
.inst-tab{flex:1;display:flex;align-items:center;justify-content:center;gap:8px;padding:12px;border:1px solid var(--border,#e5e7eb);border-radius:12px;background:transparent;cursor:pointer;font-weight:600;font-size:15px;color:inherit;font-family:inherit;}
.inst-tab.active{border-color:#2563eb;background:rgba(37,99,235,.08);color:#2563eb;}
.inst-panel{display:none;}
.inst-panel.active{display:block;}
.inst-step{display:flex;gap:14px;padding:14px 0;border-bottom:1px solid var(--border,#e5e7eb);}
.inst-step:last-child{border-bottom:0;}
.inst-num{flex:0 0 30px;width:30px;height:30px;border-radius:50%;background:#2563eb;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;}
.inst-step .body{flex:1;padding-top:3px;line-height:1.7;}
.inst-ic{display:inline-flex;vertical-align:middle;margin:0 3px;color:#2563eb;}
.inst-note{margin-top:6px;padding:12px 14px;border-radius:10px;border-inline-start:4px solid var(--warning,#f59e0b);background:rgba(245,158,11,.08);font-size:13px;line-height:1.7;}
.inst-ok{display:none;padding:14px 16px;border-radius:12px;border-inline-start:4px solid var(--success,#16a34a);background:rgba(22,163,74,.09);font-weight:600;margin-bottom:16px;}
</style>

<div class="card inst-wrap">
    <div class="inst-hero">
        <img src="<?= e($appIcon) ?>" alt="" onerror="this.style.display='none'">
        <div>
            <div style="font-size:18px;font-weight:700;"><?= e($appName) ?></div>
            <div class="hint">تطبيق ويب تقدّمي (PWA) — لا يحتاج متجر تطبيقات.</div>
        </div>
    </div>

    <div class="inst-benefits">
        <span>🚀 فتح أسرع من الشاشة الرئيسية</span>
        <span>🔔 إشعارات فورية للمهام والرسائل والاجتماعات</span>
        <span>🖥️ يعمل بملء الشاشة كتطبيق مستقل</span>
    </div>

    <div class="inst-ok" id="inst-installed">✅ يبدو أنك تفتح النظام كتطبيق مثبّت بالفعل — لا حاجة لخطوات إضافية. لتفعيل الإشعارات اذهب إلى «الملف الشخصي».</div>

    <div class="inst-tabs" id="inst-tabs">
        <button type="button" class="inst-tab" data-os="ios">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.4 12.7c0-2.3 1.9-3.4 2-3.5-1.1-1.6-2.8-1.8-3.4-1.8-1.4-.1-2.8.9-3.5.9s-1.8-.9-3-.9c-1.5 0-2.9.9-3.7 2.3-1.6 2.7-.4 6.8 1.1 9 .7 1.1 1.6 2.3 2.7 2.2 1.1 0 1.5-.7 2.8-.7s1.7.7 2.8.7 1.9-1.1 2.6-2.1c.8-1.2 1.2-2.3 1.2-2.4-.1 0-2.3-.9-2.4-3.5zM14.3 5.9c.6-.7 1-1.7.9-2.7-.9 0-1.9.6-2.5 1.3-.6.6-1 1.6-.9 2.6 1 .1 2-.5 2.5-1.2z"/></svg>
            آيفون (Safari)
        </button>
        <button type="button" class="inst-tab" data-os="android">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 9v9a1 1 0 0 0 1 1h1v3a1 1 0 0 0 2 0v-3h4v3a1 1 0 0 0 2 0v-3h1a1 1 0 0 0 1-1V9H6zM3.5 9A1.5 1.5 0 0 0 2 10.5v5a1.5 1.5 0 0 0 3 0v-5A1.5 1.5 0 0 0 3.5 9zm17 0a1.5 1.5 0 0 0-1.5 1.5v5a1.5 1.5 0 0 0 3 0v-5A1.5 1.5 0 0 0 20.5 9zM15.8 3l1-1.6a.3.3 0 0 0-.5-.3l-1 1.7A6.3 6.3 0 0 0 12 2c-1.2 0-2.3.3-3.3.8l-1-1.7a.3.3 0 1 0-.5.3l1 1.6A5.7 5.7 0 0 0 6 8h12a5.7 5.7 0 0 0-2.2-5zM9.5 6a.8.8 0 1 1 0-1.6.8.8 0 0 1 0 1.6zm5 0a.8.8 0 1 1 0-1.6.8.8 0 0 1 0 1.6z"/></svg>
            أندرويد (Chrome)
        </button>
    </div>

    <!-- iOS -->
    <div class="inst-panel" data-os="ios">
        <div class="inst-step"><div class="inst-num">١</div><div class="body">افتح النظام داخل متصفح <strong>Safari</strong> (لا يعمل من داخل واتساب أو تطبيق آخر — لو فتحته من رابط، اضغط «فتح في Safari»).</div></div>
        <div class="inst-step"><div class="inst-num">٢</div><div class="body">اضغط زر <strong>المشاركة</strong>
            <span class="inst-ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4M12 4l-4 4M12 4l4 4"/><path d="M6 12H5a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5a2 2 0 0 0-2-2h-1"/></svg></span>
            (مربّع بسهم للأعلى) في شريط الأسفل.</div></div>
        <div class="inst-step"><div class="inst-num">٣</div><div class="body">مرّر لأسفل واختر <strong>«إضافة إلى الشاشة الرئيسية»</strong> (Add to Home Screen).</div></div>
        <div class="inst-step"><div class="inst-num">٤</div><div class="body">اضغط <strong>«إضافة»</strong> أعلى اليمين — وستظهر أيقونة «<?= e($appName) ?>» على شاشتك.</div></div>
        <div class="inst-step"><div class="inst-num">٥</div><div class="body">افتح التطبيق من أيقونته الجديدة، ثم فعّل الإشعارات من <a href="<?= route('/profile') ?>">الملف الشخصي</a>.</div></div>
        <div class="inst-note">📌 على الآيفون تعمل الإشعارات فقط بعد إضافة النظام للشاشة الرئيسية وفتحه منها (يتطلب iOS 16.4 أو أحدث).</div>
    </div>

    <!-- Android -->
    <div class="inst-panel" data-os="android">
        <div class="inst-step"><div class="inst-num">١</div><div class="body">افتح النظام داخل متصفح <strong>Chrome</strong>.</div></div>
        <div class="inst-step"><div class="inst-num">٢</div><div class="body">اضغط زر <strong>القائمة</strong>
            <span class="inst-ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg></span>
            (ثلاث نقاط) أعلى اليمين.</div></div>
        <div class="inst-step"><div class="inst-num">٣</div><div class="body">اختر <strong>«تثبيت التطبيق»</strong> أو <strong>«إضافة إلى الشاشة الرئيسية»</strong> (قد يظهر شريط تثبيت أسفل الشاشة مباشرةً).</div></div>
        <div class="inst-step"><div class="inst-num">٤</div><div class="body">أكّد بالضغط على <strong>«تثبيت»</strong>.</div></div>
        <div class="inst-step"><div class="inst-num">٥</div><div class="body">افتح التطبيق من أيقونته، ثم فعّل الإشعارات من <a href="<?= route('/profile') ?>">الملف الشخصي</a>.</div></div>
        <div class="inst-note">📌 إن لم يظهر خيار التثبيت، حدّث صفحة النظام مرة واحدة ثم أعد فتح القائمة — يحتاج المتصفح لحظة ليتعرّف على التطبيق.</div>
    </div>
</div>

<script>
(function () {
    var ua = navigator.userAgent || '';
    var isIOS = /iphone|ipad|ipod/i.test(ua) || (/Macintosh/.test(ua) && 'ontouchend' in document);
    var def = isIOS ? 'ios' : 'android';

    function select(os) {
        document.querySelectorAll('.inst-tab').forEach(function (t) { t.classList.toggle('active', t.getAttribute('data-os') === os); });
        document.querySelectorAll('.inst-panel').forEach(function (p) { p.classList.toggle('active', p.getAttribute('data-os') === os); });
    }
    document.querySelectorAll('.inst-tab').forEach(function (t) {
        t.addEventListener('click', function () { select(t.getAttribute('data-os')); });
    });
    select(def);

    // لو النظام مفتوح أصلاً كتطبيق مثبّت، أظهر رسالة النجاح وأخفِ الخطوات.
    var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    if (standalone) {
        document.getElementById('inst-installed').style.display = 'block';
        document.getElementById('inst-tabs').style.display = 'none';
        document.querySelectorAll('.inst-panel').forEach(function (p) { p.style.display = 'none'; });
    }
})();
</script>

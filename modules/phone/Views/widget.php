<?php
/**
 * ودجت الهاتف العائم. يُعرض في كل صفحة عبر hook الإضافات العام (global.php).
 * تُستخدم نفس بنية الزر+اللوحة في كل الحالات حتى تكون الودجت واضحة ومكتشَفة دائماً،
 * ويختلف محتوى اللوحة فقط حسب اكتمال الإعداد.
 */
$ready = $state === 'ready';
$config = $ready ? e(json_encode([
    'apiKey' => $apiKey,
    'extension' => $extension,
    'secret' => $secret,
    'jsUrl' => asset('vendor/innocalls/webrtc.js'),
    'cssUrl' => asset('vendor/innocalls/webrtc.css'),
], JSON_UNESCAPED_UNICODE)) : '';
?>
<div id="phone-widget" class="phone-widget" <?= $ready ? 'data-config="' . $config . '"' : '' ?>>
    <button type="button" id="phone-widget-toggle" class="phone-widget-toggle">
        <span id="phone-status-dot" class="phone-status-dot <?= $ready ? 'offline' : 'warning' ?>"></span>
        📞 <span>الهاتف</span>
    </button>
    <div id="phone-widget-panel" class="phone-widget-panel" hidden>
        <?php if ($state === 'missing_company_key'): ?>
            <div class="phone-widget-header"><strong>الهاتف</strong></div>
            <p class="hint" style="margin:0;">لم يُفعّل مدير النظام خدمة الهاتف لشركتك بعد. تواصل معه لإتمام الإعداد من صفحة "إعدادات الهاتف (الشركات)".</p>
        <?php elseif ($state === 'missing_user_config'): ?>
            <div class="phone-widget-header"><strong>الهاتف</strong></div>
            <p class="hint" style="margin:0 0 10px;">لم تُكمل إعداد تحويلتك الشخصية بعد.</p>
            <a class="btn btn-sm" href="<?= route('/phone/settings') ?>">إكمال الإعداد</a>
        <?php else: ?>
            <div class="phone-widget-header">
                <strong>الهاتف</strong>
                <span id="phone-status-text">جاري الاتصال بالسنترال...</span>
            </div>
            <div class="phone-widget-dial">
                <input type="text" id="phone-dial-input" placeholder="أدخل الرقم" inputmode="tel">
                <button type="button" id="phone-dial-btn" class="btn btn-sm">اتصال</button>
            </div>
            <div id="innocalls-widget-native"></div>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
    var root = document.getElementById('phone-widget');
    if (!root) return;
    var toggle = document.getElementById('phone-widget-toggle');
    var panel = document.getElementById('phone-widget-panel');
    toggle.addEventListener('click', function () { panel.hidden = !panel.hidden; });

    <?php if ($ready): ?>
    var config = JSON.parse(root.dataset.config);
    var statusDot = document.getElementById('phone-status-dot');
    var statusText = document.getElementById('phone-status-text');
    var dialInput = document.getElementById('phone-dial-input');
    var dialBtn = document.getElementById('phone-dial-btn');
    var instance = null;
    var outgoing = false;

    function loadCss(href, id) {
        if (document.getElementById(id)) return;
        var link = document.createElement('link');
        link.id = id; link.rel = 'stylesheet'; link.href = href;
        document.head.appendChild(link);
    }

    function loadScript(src, id, onload, onerror) {
        if (document.getElementById(id)) { onload(); return; }
        var s = document.createElement('script');
        s.id = id; s.src = src; s.onload = onload; s.onerror = onerror;
        document.head.appendChild(s);
    }

    loadCss(config.cssUrl, 'innocalls-css');
    loadScript(config.jsUrl, 'innocalls-script', function () {
        var attempts = 0;
        var timer = setInterval(function () {
            attempts++;
            if (window.InnocallsRTC) {
                clearInterval(timer);
                try {
                    instance = new window.InnocallsRTC({
                        apiKey: config.apiKey,
                        extension: config.extension,
                        webrtcSecret: config.secret,
                        config: { baseColor: '#2563eb' },
                        terminateOnRefresh: false
                    });
                    instance.mount('#innocalls-widget-native');
                    instance.on('registered', function () { statusDot.className = 'phone-status-dot online'; statusText.textContent = 'متصل'; });
                    instance.on('unregistered', function () { statusDot.className = 'phone-status-dot offline'; statusText.textContent = 'غير متصل'; });
                    instance.on('registrationFailed', function () { statusDot.className = 'phone-status-dot offline'; statusText.textContent = 'فشل تسجيل الدخول للسنترال'; });
                    instance.on('callRinging', function () {
                        if (!outgoing) { statusText.textContent = 'مكالمة واردة'; panel.hidden = false; }
                        else { statusText.textContent = 'جاري الاتصال...'; }
                    });
                    instance.on('callStarted', function () { statusText.textContent = 'مكالمة جارية'; });
                    instance.on('callAnswered', function () { statusText.textContent = 'مكالمة جارية'; });
                    instance.on('callEnded', function () { statusText.textContent = 'متصل'; outgoing = false; });
                    instance.on('callFailed', function () { statusText.textContent = 'فشلت المكالمة'; outgoing = false; });
                    instance.on('callRejected', function () { statusText.textContent = 'تم رفض المكالمة'; outgoing = false; });
                    instance.on('callMissed', function () { statusText.textContent = 'مكالمة فائتة'; outgoing = false; });
                } catch (e) {
                    statusText.textContent = 'تعذر تهيئة الهاتف';
                }
            } else if (attempts > 25) {
                clearInterval(timer);
                statusText.textContent = 'تعذر تحميل مكتبة الاتصال (تأكد من رفع ملفات SDK في assets/vendor/innocalls)';
            }
        }, 200);
    }, function () {
        statusText.textContent = 'تعذر تحميل مكتبة الاتصال (تأكد من رفع ملفات SDK في assets/vendor/innocalls)';
    });

    dialBtn.addEventListener('click', function () {
        var number = dialInput.value.trim();
        if (!number || !instance) return;
        outgoing = true;
        instance.startCall(number);
    });
    dialInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') dialBtn.click();
    });
    <?php endif; ?>
})();
</script>

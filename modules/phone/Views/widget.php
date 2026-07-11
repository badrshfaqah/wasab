<?php
/**
 * ودجت الهاتف العائم. يُعرض في كل صفحة عبر hook الإضافات العام (global.php).
 * منطق الربط مع مكتبة Innocalls (الأحداث، showWebrtc للرد على مكالمة واردة، إخفاء
 * الواجهة الأصلية للمكتبة تماماً واستبدالها بواجهتنا) منقول حرفياً من تكامل فعلي
 * مُثبت العمل في الإنتاج لنفس المزوّد.
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
<?php if ($ready): ?>
    <div id="innocalls-root" class="phone-native-mount"></div>
<?php endif; ?>

<div id="phone-widget" class="phone-widget" <?= $ready ? 'data-config="' . $config . '"' : '' ?>>
    <div id="phone-incoming-toast" class="phone-incoming-toast" hidden>
        <span class="phone-status-dot online" style="position:static;border:0;"></span>
        <div>
            <div style="font-weight:700;">مكالمة واردة</div>
            <div class="hint" style="margin:0;">اضغط للرد</div>
        </div>
    </div>

    <button type="button" id="phone-widget-toggle" class="phone-widget-toggle">
        <span id="phone-status-dot" class="phone-status-dot <?= $ready ? 'offline' : 'warning' ?>"></span>
        <span id="phone-toggle-icon">📞</span>
        <span id="phone-toggle-label">الهاتف</span>
    </button>

    <div id="phone-widget-panel" class="phone-widget-panel" hidden>
        <?php if ($state === 'missing_company_key'): ?>
            <div class="phone-widget-header"><strong>الهاتف</strong></div>
            <p class="hint" style="margin:0;">لم يُفعّل مدير النظام خدمة الهاتف لشركتك بعد. تواصل معه لإتمام الإعداد من صفحة "إعدادات الهاتف (الشركات)".</p>

        <?php elseif ($state === 'missing_user_config'): ?>
            <div class="phone-widget-header"><strong>الهاتف</strong></div>
            <p class="hint" style="margin:0 0 10px;">لم تُكمل إعداد تحويلتك الشخصية بعد.</p>
            <a class="btn btn-sm" href="<?= route('/phone/settings') ?>">إكمال الإعداد</a>

        <?php elseif ($state === 'disabled'): ?>
            <div class="phone-widget-header"><strong>الهاتف</strong></div>
            <p class="hint" style="margin:0 0 10px;">الهاتف موقوف مؤقتاً على حسابك.</p>
            <button type="button" class="btn btn-sm" id="phone-enable-btn">تشغيل الهاتف</button>

        <?php else: ?>
            <div class="phone-widget-header">
                <div style="display:flex;align-items:center;gap:6px;">
                    <span id="phone-reg-dot" class="phone-status-dot"></span>
                    <span id="phone-header-text">جاري الاتصال بالسنترال...</span>
                </div>
                <button type="button" id="phone-toggle-enabled" class="btn btn-outline btn-sm" style="padding:3px 10px;">إيقاف</button>
            </div>

            <div id="phone-section-ringing" class="phone-call-section" hidden>
                <p style="text-align:center;font-weight:700;margin:6px 0 14px;">📲 مكالمة واردة</p>
                <div style="display:flex;gap:10px;">
                    <button type="button" id="phone-reject-btn" class="btn btn-danger" style="flex:1;justify-content:center;">رفض</button>
                    <button type="button" id="phone-answer-btn" class="btn" style="flex:1;justify-content:center;background:var(--success);">رد</button>
                </div>
            </div>

            <div id="phone-section-active" class="phone-call-section" hidden>
                <p style="text-align:center;font-weight:700;margin:6px 0 14px;">☎️ مكالمة جارية</p>
                <button type="button" id="phone-hangup-btn" class="btn btn-danger" style="width:100%;justify-content:center;">إنهاء المكالمة</button>
            </div>

            <div id="phone-section-dial" class="phone-call-section">
                <div class="phone-widget-dial">
                    <input type="text" id="phone-dial-input" placeholder="أدخل الرقم" inputmode="tel" dir="ltr">
                    <button type="button" id="phone-backspace-btn" class="btn btn-outline btn-sm" title="حذف">⌫</button>
                </div>
                <div class="phone-dialpad">
                    <?php foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9', '*', '0', '#'] as $digit): ?>
                        <button type="button" class="phone-dialpad-key" data-digit="<?= $digit ?>"><?= $digit ?></button>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="phone-dial-btn" class="btn" style="width:100%;justify-content:center;margin-top:8px;background:var(--success);">📞 اتصال</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var root = document.getElementById('phone-widget');
    if (!root) return;

    var toggle = document.getElementById('phone-widget-toggle');
    var panel = document.getElementById('phone-widget-panel');
    var toast = document.getElementById('phone-incoming-toast');

    function openPanel() { panel.hidden = false; toast.hidden = true; }
    toggle.addEventListener('click', function () { panel.hidden = !panel.hidden; toast.hidden = true; });
    toast.addEventListener('click', openPanel);

    <?php if ($state === 'disabled'): ?>
    var enableBtn = document.getElementById('phone-enable-btn');
    if (enableBtn) {
        enableBtn.addEventListener('click', function (e) {
            e.preventDefault();
            fetch('<?= route('/phone/toggle') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_csrf=' + encodeURIComponent('<?= \App\Core\Csrf::token() ?>')
            }).then(function () { window.location.reload(); });
        });
    }
    <?php endif; ?>

    <?php if ($ready): ?>
    var config = JSON.parse(root.dataset.config);
    var statusDot = document.getElementById('phone-status-dot');
    var toggleIcon = document.getElementById('phone-toggle-icon');
    var toggleLabel = document.getElementById('phone-toggle-label');
    var regDot = document.getElementById('phone-reg-dot');
    var headerText = document.getElementById('phone-header-text');
    var dialInput = document.getElementById('phone-dial-input');
    var dialBtn = document.getElementById('phone-dial-btn');
    var backspaceBtn = document.getElementById('phone-backspace-btn');
    var answerBtn = document.getElementById('phone-answer-btn');
    var rejectBtn = document.getElementById('phone-reject-btn');
    var hangupBtn = document.getElementById('phone-hangup-btn');
    var toggleEnabledBtn = document.getElementById('phone-toggle-enabled');
    var sectionRinging = document.getElementById('phone-section-ringing');
    var sectionActive = document.getElementById('phone-section-active');
    var sectionDial = document.getElementById('phone-section-dial');

    var instance = null;
    var callState = 'idle'; // idle | ringing | active
    var registered = 'unknown'; // unknown | online | offline

    function render() {
        sectionRinging.hidden = callState !== 'ringing';
        sectionActive.hidden = callState !== 'active';
        sectionDial.hidden = callState !== 'idle';

        if (callState === 'ringing') {
            toggleIcon.textContent = '📲'; toggleLabel.textContent = 'مكالمة واردة';
            toast.hidden = !panel.hidden;
        } else if (callState === 'active') {
            toggleIcon.textContent = '☎️'; toggleLabel.textContent = 'مكالمة جارية';
            toast.hidden = true;
        } else {
            toggleIcon.textContent = '📞'; toggleLabel.textContent = 'الهاتف';
            toast.hidden = true;
        }

        statusDot.className = 'phone-status-dot ' + (callState === 'ringing' ? 'online' : (registered === 'online' ? 'online' : (registered === 'offline' ? 'offline' : 'warning')));
        regDot.className = 'phone-status-dot ' + (registered === 'online' ? 'online' : (registered === 'offline' ? 'offline' : 'warning'));
        headerText.textContent = 'التحويلة ' + config.extension + (registered === 'online' ? ' · متصلة' : registered === 'offline' ? ' · منقطعة' : ' · جاري الاتصال...');
        toggle.classList.toggle('phone-ringing', callState === 'ringing');
    }

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
                    instance.mount('#innocalls-root');

                    instance.on('registered', function () { registered = 'online'; render(); });
                    instance.on('unregistered', function () { registered = 'offline'; render(); });
                    instance.on('registrationFailed', function () { registered = 'offline'; render(); });
                    instance.on('callRinging', function () { callState = 'ringing'; render(); openPanel(); });
                    instance.on('callStarted', function () { callState = 'active'; render(); });
                    instance.on('callAnswered', function () { callState = 'active'; render(); });
                    instance.on('callEnded', function () { callState = 'idle'; render(); });
                    instance.on('callFailed', function () { callState = 'idle'; render(); });
                    instance.on('callRejected', function () { callState = 'idle'; render(); });
                    instance.on('callMissed', function () { callState = 'idle'; render(); });
                    render();
                } catch (e) {
                    headerText.textContent = 'تعذر تهيئة الهاتف';
                }
            } else if (attempts > 25) {
                clearInterval(timer);
                headerText.textContent = 'تعذر تحميل مكتبة الاتصال (تأكد من رفع ملفات SDK في assets/vendor/innocalls)';
            }
        }, 200);
    }, function () {
        headerText.textContent = 'تعذر تحميل مكتبة الاتصال (تأكد من رفع ملفات SDK في assets/vendor/innocalls)';
    });

    function dial(number) {
        var n = (number || '').trim();
        if (!n || !instance) return;
        instance.startCall(n);
        callState = 'ringing';
        render();
        openPanel();
    }

    // يتيح لصفحات أخرى (دليل جهات الاتصال مثلاً) تشغيل مكالمة عبر هذا الودجت
    // دون معرفة تفاصيل تهيئته - يفشل بصمت إن كان الهاتف غير جاهز بعد.
    window.__phoneDial = function (number) { dial(number); };

    dialBtn.addEventListener('click', function () { dial(dialInput.value); });
    dialInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') dial(dialInput.value); });
    backspaceBtn.addEventListener('click', function () { dialInput.value = dialInput.value.slice(0, -1); });
    document.querySelectorAll('.phone-dialpad-key').forEach(function (key) {
        key.addEventListener('click', function () { dialInput.value += key.dataset.digit; });
    });

    answerBtn.addEventListener('click', function () {
        if (instance && instance.showWebrtc) instance.showWebrtc();
        callState = 'active';
        render();
    });
    rejectBtn.addEventListener('click', function () {
        instance && instance.hangup();
        callState = 'idle';
        render();
    });
    hangupBtn.addEventListener('click', function () {
        instance && instance.hangup();
        callState = 'idle';
        render();
    });

    toggleEnabledBtn.addEventListener('click', function () {
        toggleEnabledBtn.disabled = true;
        fetch('<?= route('/phone/toggle') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: '_csrf=' + encodeURIComponent('<?= \App\Core\Csrf::token() ?>')
        }).then(function () { window.location.reload(); });
    });
    <?php endif; ?>
})();
</script>

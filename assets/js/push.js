/**
 * تفعيل إشعارات الدفع (Web Push) لجهاز المستخدم الحالي. يعتمد على إعدادات يحقنها
 * التخطيط في window.WASAB_PUSH = { swUrl, vapidKey, subscribeUrl, unsubscribeUrl, csrf }.
 *
 * السلوك:
 * - عند كل تحميل صفحة: تسجيل عامل الخدمة، وإن كان الإذن ممنوحاً مسبقاً تُجدَّد
 *   بيانات الاشتراك بصمت (المتصفح قد يغيّر endpoint).
 * - بانر "فعّل التنبيهات" (id=push-banner) يظهر لمن لم يفعّل بعد ولم يرفض الإذن،
 *   بزر تفعيل بنقرة واحدة وزر إغلاق يخفيه ١٤ يوماً.
 * - زر التفعيل/الإيقاف (id=push-toggle) في الملف الشخصي كما هو.
 */
(function () {
  var cfg = window.WASAB_PUSH;
  if (!cfg || !('serviceWorker' in navigator)) {
    return;
  }

  var supported = 'PushManager' in window && 'Notification' in window;
  var DISMISS_KEY = 'wasab_push_banner_dismissed_at';
  var ENABLED_KEY = 'wasab_push_enabled';
  var DISMISS_DAYS = 14;

  function hideBannerForGood() {
    localStorage.setItem(ENABLED_KEY, '1');
    var b = document.getElementById('push-banner');
    if (b) b.hidden = true;
  }

  function urlBase64ToUint8Array(base64String) {
    var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    var rawData = atob(base64);
    var outputArray = new Uint8Array(rawData.length);
    for (var i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }

  function postJson(url, body) {
    body._csrf = cfg.csrf;
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body)
    });
  }

  function subscribe(registration) {
    return registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(cfg.vapidKey)
    }).then(function (subscription) {
      var json = subscription.toJSON();
      return postJson(cfg.subscribeUrl, { endpoint: json.endpoint, keys: json.keys }).then(function () {
        return subscription;
      });
    });
  }

  /**
   * طلب إذن الإشعارات بشكل آمن: بعض المتصفحات القديمة تستخدم صيغة callback بدل
   * Promise، وأي استثناء غير متوقع يُعامل كرفض بدل أن يكسر السلسلة بصمت.
   */
  function requestPermissionSafe() {
    return new Promise(function (resolve) {
      try {
        var maybe = Notification.requestPermission(function (result) { resolve(result); });
        if (maybe && typeof maybe.then === 'function') {
          maybe.then(resolve, function () { resolve('denied'); });
        }
      } catch (e) {
        resolve('denied');
      }
    });
  }

  /**
   * الاشتراك بعد منح الإذن. تنبيه مهم: طلب الإذن نفسه يجب أن يُستدعى مباشرة
   * داخل معالج النقرة (متزامناً) وليس هنا - iOS يتجاهل الطلب بصمت إن جاء داخل
   * سلسلة وعود متأخرة عن إيماءة المستخدم.
   */
  function subscribeAfterGrant() {
    return ready.then(function (registration) {
      if (!registration) {
        return null;
      }
      return subscribe(registration);
    });
  }

  var ready = navigator.serviceWorker.register(cfg.swUrl).catch(function () { return null; });

  // تجديد صامت للاشتراك إن كان الإذن ممنوحاً أصلاً
  if (supported && Notification.permission === 'granted') {
    ready.then(function (registration) {
      if (!registration) return;
      registration.pushManager.getSubscription().then(function (existing) {
        if (existing) {
          localStorage.setItem(ENABLED_KEY, '1');
          var json = existing.toJSON();
          postJson(cfg.subscribeUrl, { endpoint: json.endpoint, keys: json.keys }).catch(function () {});
        }
      });
    });
  }

  // ------------------------------------------------------------------
  // بانر التفعيل بنقرة واحدة
  // ------------------------------------------------------------------
  var banner = document.getElementById('push-banner');
  if (banner && supported && Notification.permission === 'default' && !localStorage.getItem(ENABLED_KEY)) {
    var dismissedAt = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);
    var dismissValid = dismissedAt && (Date.now() - dismissedAt) < DISMISS_DAYS * 86400000;

    if (!dismissValid) {
      ready.then(function (registration) {
        if (!registration) return;
        registration.pushManager.getSubscription().then(function (existing) {
          if (!existing) {
            banner.hidden = false;
          }
        });
      });
    }

    var enableBtn = document.getElementById('push-banner-enable');
    var dismissBtn = document.getElementById('push-banner-dismiss');

    if (enableBtn) {
      enableBtn.addEventListener('click', function () {
        // البانر يختفي فوراً بمجرد الضغط - مهما كانت نتيجة ما بعده.
        banner.hidden = true;
        localStorage.setItem(DISMISS_KEY, String(Date.now()));

        // طلب الإذن أول شيء وبشكل متزامن داخل النقرة (شرط iOS)
        requestPermissionSafe().then(function (permission) {
          if (permission !== 'granted') return;
          subscribeAfterGrant().then(function (subscription) {
            if (subscription) {
              hideBannerForGood();
            }
          }).catch(function () {});
        });
      });
    }
    if (dismissBtn) {
      dismissBtn.addEventListener('click', function () {
        localStorage.setItem(DISMISS_KEY, String(Date.now()));
        banner.hidden = true;
      });
    }
  }

  // ------------------------------------------------------------------
  // زر التفعيل بالملف الشخصي
  // ------------------------------------------------------------------
  var toggle = document.getElementById('push-toggle');
  if (!toggle) {
    return;
  }
  var statusEl = document.getElementById('push-status');
  // حالة الاشتراك الحالية تُتتبع مسبقاً حتى يقرر معالج النقرة فوراً (اشتراك أم
  // إلغاء) دون استعلام متأخر يُفقد iOS إيماءة المستخدم.
  var currentSub = null;

  function setStatus(text) {
    if (statusEl) statusEl.textContent = text;
  }

  function refreshButton() {
    if (!supported) {
      toggle.disabled = true;
      setStatus('متصفحك لا يدعم إشعارات الدفع. على آيفون: أضف النظام للشاشة الرئيسية أولاً (مشاركة ← إضافة إلى الشاشة الرئيسية) ثم فعّل من داخل التطبيق.');
      return;
    }
    if (Notification.permission === 'denied') {
      toggle.disabled = true;
      setStatus('الإذن مرفوض من إعدادات المتصفح - فعّل الإشعارات للموقع من إعدادات المتصفح ثم أعد المحاولة.');
      return;
    }
    ready.then(function (registration) {
      if (!registration) return;
      registration.pushManager.getSubscription().then(function (existing) {
        currentSub = existing || null;
        if (existing) {
          toggle.textContent = 'إيقاف التنبيهات على هذا الجهاز';
          toggle.classList.add('btn-outline');
          setStatus('التنبيهات مفعّلة على هذا الجهاز ✓ ستصلك إشعارات الرسائل والمهام والاجتماعات فور حدوثها.');
        } else {
          toggle.textContent = 'تفعيل التنبيهات على هذا الجهاز';
          toggle.classList.remove('btn-outline');
          setStatus('عند التفعيل ستصلك إشعارات النظام (رسائل مركز المراسلات، المهام، الاجتماعات...) على هذا الجهاز حتى والمتصفح مغلق.');
        }
      });
    });
  }

  toggle.addEventListener('click', function () {
    if (!supported) return;
    toggle.disabled = true;

    if (currentSub) {
      // إيقاف: لا يحتاج إذناً ولا إيماءة
      var json = currentSub.toJSON();
      currentSub.unsubscribe().then(function () {
        postJson(cfg.unsubscribeUrl, { endpoint: json.endpoint }).catch(function () {});
        currentSub = null;
        toggle.disabled = false;
        refreshButton();
      });
      return;
    }

    // تفعيل: طلب الإذن أول شيء وبشكل متزامن داخل النقرة (شرط iOS)
    requestPermissionSafe().then(function (permission) {
      if (permission !== 'granted') {
        toggle.disabled = false;
        refreshButton();
        return;
      }
      subscribeAfterGrant().then(function (subscription) {
        if (subscription) {
          hideBannerForGood();
        }
        toggle.disabled = false;
        refreshButton();
      }).catch(function () {
        toggle.disabled = false;
        setStatus('تعذر إتمام الاشتراك - أعد المحاولة.');
      });
    });
  });

  refreshButton();
})();

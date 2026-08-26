document.addEventListener('DOMContentLoaded', function () {
  var menuToggle = document.querySelector('.menu-toggle');
  var sidebar = document.querySelector('.sidebar');
  var backdrop = document.querySelector('.sidebar-backdrop');

  function setSidebar(open) {
    if (!sidebar) return;
    sidebar.classList.toggle('open', open);
    if (backdrop) {
      if (open) {
        backdrop.hidden = false;
        requestAnimationFrame(function () { backdrop.classList.add('visible'); });
      } else {
        backdrop.classList.remove('visible');
        setTimeout(function () { backdrop.hidden = true; }, 200);
      }
    }
  }

  if (menuToggle && sidebar) {
    menuToggle.addEventListener('click', function () {
      setSidebar(!sidebar.classList.contains('open'));
    });
  }
  // زر "القائمة" بالشريط السفلي للجوال يفتح نفس القائمة الجانبية
  document.querySelectorAll('[data-sidebar-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      setSidebar(!sidebar.classList.contains('open'));
    });
  });

  // الفلاتر القابلة للطي (details/summary أصلية): مقفلة افتراضياً وتفتح بالنقر،
  // والخادم يفتحها مسبقاً فقط عند وجود فلاتر نشطة - لا حاجة لأي منطق JS هنا.
  if (backdrop) {
    backdrop.addEventListener('click', function () { setSidebar(false); });
  }
  // إغلاق القائمة تلقائياً عند اختيار رابط منها على الجوال
  if (sidebar) {
    sidebar.addEventListener('click', function (e) {
      if (e.target.closest('a') && window.matchMedia('(max-width: 900px)').matches) {
        setSidebar(false);
      }
    });
  }

  document.querySelectorAll('[data-dropdown-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var target = document.getElementById(btn.getAttribute('data-dropdown-toggle'));
      document.querySelectorAll('.dropdown.open').forEach(function (d) {
        if (d !== target) d.classList.remove('open');
      });
      if (target) target.classList.toggle('open');
    });
  });
  document.addEventListener('click', function () {
    document.querySelectorAll('.dropdown.open').forEach(function (d) { d.classList.remove('open'); });
  });

  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(el.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });

  // إغلاق تلقائي لرسائل النجاح بعد لحظات (رسائل الخطأ تبقى حتى يقرأها المستخدم)
  document.querySelectorAll('.alert-success').forEach(function (al) {
    setTimeout(function () {
      al.classList.add('fade-out');
      setTimeout(function () { al.remove(); }, 450);
    }, 4500);
  });

  // مؤشر تحميل على زر الإرسال عند إرسال أي نموذج - تأكيد بصري أن الطلب جارٍ ومنع
  // النقر المزدوج. لا نعطّل الزر (حتى لا تُفقد قيمته عند الإرسال) بل نضيف صنفاً بصرياً.
  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function () {
      var btn = form.querySelector('button[type="submit"], button:not([type])');
      if (btn && !btn.classList.contains('is-loading')) {
        btn.classList.add('is-loading');
        setTimeout(function () { btn.classList.remove('is-loading'); }, 8000);
      }
    });
  });

  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var text = btn.getAttribute('data-copy');
      var done = function () {
        var original = btn.textContent;
        btn.textContent = '✓ تم النسخ';
        setTimeout(function () { btn.textContent = original; }, 1500);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done).catch(function () { fallbackCopy(text, done); });
      } else {
        fallbackCopy(text, done);
      }
    });
  });

  function fallbackCopy(text, done) {
    var input = document.createElement('textarea');
    input.value = text;
    input.style.position = 'fixed';
    input.style.opacity = '0';
    document.body.appendChild(input);
    input.focus();
    input.select();
    try { document.execCommand('copy'); done(); } catch (e) {}
    document.body.removeChild(input);
  }

  document.querySelectorAll('.js-mark-read').forEach(function (el) {
    el.addEventListener('click', function () {
      var id = el.getAttribute('data-id');
      fetch(el.getAttribute('data-url'), {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id) + '&_csrf=' + encodeURIComponent(el.getAttribute('data-csrf'))
      });
    });
  });

  setTimeout(function () {
    document.querySelectorAll('.alert[data-autohide]').forEach(function (el) {
      el.style.transition = 'opacity .4s';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 400);
    });
  }, 4000);

  // محرر نصوص غني بسيط بدون مكتبات خارجية: مربع contenteditable + شريط أدوات
  // execCommand، تتم مزامنته مع textarea مخفي عند كل تغيير وقبل الإرسال، حتى
  // يصل المحتوى كنص عادي داخل POST بدون جافاسكربت إضافي في القالب نفسه.
  document.querySelectorAll('.rte').forEach(function (rte) {
    var field = document.getElementById(rte.getAttribute('data-target'));
    var editable = rte.querySelector('.rte-content');
    if (!field || !editable) return;

    editable.innerHTML = field.value || '';
    var sync = function () { field.value = editable.innerHTML; };

    rte.querySelectorAll('[data-cmd]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        editable.focus();
        var cmd = btn.getAttribute('data-cmd');
        var value = btn.getAttribute('data-value') || null;
        document.execCommand(cmd, false, value);
        sync();
      });
    });

    editable.addEventListener('input', sync);
    editable.addEventListener('blur', sync);
    var form = rte.closest('form');
    if (form) form.addEventListener('submit', sync);
  });
});

/* ---- القائمة الجانبية: طيّ المجموعات وتصفيتها ----
   حالة الطيّ تُحفظ محلياً لكل مجموعة، فيبقى ترتيب المستخدم كما تركه بين
   الصفحات. والتصفية تُخفي ما لا يطابق وتفتح ما بقي، فالكتابة أسرع من التمرير. */
document.addEventListener('DOMContentLoaded', function () {
  var nav = document.getElementById('sidebar-nav');
  if (!nav) { return; }

  var STORE = 'wasab.nav.collapsed';
  var collapsed = {};
  try { collapsed = JSON.parse(localStorage.getItem(STORE) || '{}') || {}; } catch (e) { collapsed = {}; }

  nav.querySelectorAll('.nav-group').forEach(function (group) {
    var key = group.getAttribute('data-group');
    var title = group.querySelector('.nav-group-title');
    if (!title) { return; }
    // المجموعة التي تحوي الصفحة الحالية تبقى مفتوحة مهما كانت الحالة المحفوظة
    var hasActive = !!group.querySelector('.nav-link.active');
    if (!hasActive && Object.prototype.hasOwnProperty.call(collapsed, key)) {
      group.classList.toggle('collapsed', !!collapsed[key]);
      title.setAttribute('aria-expanded', collapsed[key] ? 'false' : 'true');
    }
    // نحفظ الحالة التي استقرت عليها المجموعة لنعيدها بعد انتهاء البحث
    group.dataset.restCollapsed = group.classList.contains('collapsed') ? '1' : '0';
    title.addEventListener('click', function () {
      var isCollapsed = group.classList.toggle('collapsed');
      title.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
      collapsed[key] = isCollapsed;
      group.dataset.restCollapsed = isCollapsed ? '1' : '0';
      try { localStorage.setItem(STORE, JSON.stringify(collapsed)); } catch (e) {}
    });
  });

  var filter = document.getElementById('nav-filter');
  if (!filter) { return; }
  var empty = nav.querySelector('.nav-empty');

  filter.addEventListener('input', function () {
    var q = filter.value.trim().toLowerCase();
    var anyVisible = false;

    nav.querySelectorAll('.nav-group').forEach(function (group) {
      var matches = 0;
      group.querySelectorAll('.nav-link').forEach(function (link) {
        var text = (link.querySelector('.nav-text') || link).textContent.toLowerCase();
        var hit = q === '' || text.indexOf(q) !== -1;
        link.hidden = !hit;
        if (hit) { matches++; }
      });
      group.hidden = matches === 0;
      if (matches) { anyVisible = true; }
      // أثناء البحث تُفتح المجموعات المطابقة، وعند مسحه تعود لحالتها
      if (q !== '') {
        // أثناء البحث تُفتح كل مجموعة فيها مطابقة حتى تظهر نتيجتها
        group.classList.remove('collapsed');
      } else {
        // وبعده تعود كل مجموعة إلى الحالة التي كانت عليها قبل الكتابة
        group.classList.toggle('collapsed', group.dataset.restCollapsed === '1');
      }
    });
    if (empty) { empty.hidden = anyVisible || q === ''; }
  });

  // الاختصار "/" يقفز للتصفية ما لم يكن المستخدم يكتب في حقل آخر
  document.addEventListener('keydown', function (e) {
    if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) { return; }
    var tag = (e.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || e.target.isContentEditable) { return; }
    e.preventDefault();
    filter.focus();
    filter.select();
  });
});

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

    // لصق صورة (لقطة شاشة أو صورة من الحافظة): نصغّرها قبل إدراجها بدل رفع
    // ميغابايتات كـ base64 داخل الطلب - فتجاوز حد الرفع في PHP يعني ضياع
    // الحفظ كاملاً. العرض الأقصى 1400 بكسل وهو أوسع من ورقة A4 عند الطباعة.
    editable.addEventListener('paste', function (e) {
      var items = (e.clipboardData || {}).items || [];
      var file = null;
      for (var i = 0; i < items.length; i++) {
        if (items[i].kind === 'file' && items[i].type.indexOf('image/') === 0) {
          file = items[i].getAsFile();
          break;
        }
      }
      if (!file) return;
      e.preventDefault();

      var reader = new FileReader();
      reader.onload = function () {
        var img = new Image();
        img.onload = function () {
          var max = 1400;
          var w = img.width;
          var h = img.height;
          if (w > max) { h = Math.round(h * max / w); w = max; }
          var canvas = document.createElement('canvas');
          canvas.width = w;
          canvas.height = h;
          canvas.getContext('2d').drawImage(img, 0, 0, w, h);
          var type = file.type === 'image/png' ? 'image/png' : 'image/jpeg';
          var data = canvas.toDataURL(type, 0.85);
          editable.focus();
          document.execCommand('insertHTML', false, '<img src="' + data + '" style="max-width:100%">');
          sync();
        };
        img.src = reader.result;
      };
      reader.readAsDataURL(file);
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

/* ---- الوضع المضغوط: القائمة شريط أيقونات ----
   على الشاشات الواسعة يكسب المستخدم مساحة عمل دون أن يفقد التنقل: الأيقونات
   تبقى، والاسم يظهر عند المرور. الاختيار يُحفظ محلياً. */
document.addEventListener('DOMContentLoaded', function () {
  var toggle = document.getElementById('rail-toggle');
  if (!toggle) { return; }
  var KEY = 'wasab.nav.rail';
  var apply = function (on) {
    document.body.classList.toggle('nav-rail', on);
    toggle.setAttribute('title', on ? 'توسيع القائمة' : 'طيّ القائمة');
    toggle.setAttribute('aria-label', toggle.getAttribute('title'));
  };
  try { apply(localStorage.getItem(KEY) === '1'); } catch (e) {}
  toggle.addEventListener('click', function () {
    var on = !document.body.classList.contains('nav-rail');
    apply(on);
    try { localStorage.setItem(KEY, on ? '1' : '0'); } catch (e) {}
  });
});

/* ---- لوحة الأوامر ----
   نافذة واحدة تصل بها إلى أي شاشة أو سجل أو أمر إنشاء. تُفتح بـ Ctrl/⌘+K،
   وتُدار بالكامل من لوحة المفاتيح، ولا تستدعي الخادم إلا بعد سكون الكتابة. */
document.addEventListener('DOMContentLoaded', function () {
  var palette = document.getElementById('palette');
  if (!palette) { return; }
  var input = document.getElementById('palette-q');
  var list = document.getElementById('palette-results');
  var opener = document.getElementById('open-palette');
  var items = [];
  var index = 0;
  var timer = null;
  var lastQuery = null;

  function open() {
    palette.hidden = false;
    palette.setAttribute('aria-hidden', 'false');
    document.body.classList.add('palette-open');
    input.value = '';
    input.focus();
    lastQuery = null;
    fetchResults('');
  }
  function close() {
    palette.hidden = true;
    palette.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('palette-open');
  }
  function render(groups) {
    list.innerHTML = '';
    items = [];
    if (!groups.length) {
      list.innerHTML = '<p class="palette-empty">لا نتائج — جرّب كلمة أخرى.</p>';
      return;
    }
    groups.forEach(function (group) {
      var head = document.createElement('div');
      head.className = 'palette-group';
      head.textContent = group.title;
      list.appendChild(head);
      group.items.forEach(function (item) {
        var a = document.createElement('a');
        a.className = 'palette-item';
        a.href = item.url;
        a.innerHTML = '<span class="palette-label"></span>' + (item.hint ? '<span class="palette-hint"></span>' : '');
        a.querySelector('.palette-label').textContent = item.label;
        if (item.hint) { a.querySelector('.palette-hint').textContent = item.hint; }
        list.appendChild(a);
        items.push(a);
      });
    });
    index = 0;
    highlight();
  }
  function highlight() {
    items.forEach(function (el, i) {
      el.classList.toggle('is-active', i === index);
      if (i === index && el.scrollIntoView) { el.scrollIntoView({block: 'nearest'}); }
    });
  }
  function fetchResults(q) {
    if (q === lastQuery) { return; }
    lastQuery = q;
    fetch('/palette/search?q=' + encodeURIComponent(q), {credentials: 'same-origin'})
      .then(function (r) { return r.ok ? r.json() : {groups: []}; })
      .then(function (data) { render(data.groups || []); })
      .catch(function () { render([]); });
  }

  if (opener) { opener.addEventListener('click', open); }
  palette.querySelectorAll('[data-palette-close]').forEach(function (el) {
    el.addEventListener('click', close);
  });

  input.addEventListener('input', function () {
    clearTimeout(timer);
    var q = input.value.trim();
    timer = setTimeout(function () { fetchResults(q); }, 140);
  });

  input.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowDown') { e.preventDefault(); index = Math.min(index + 1, items.length - 1); highlight(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); index = Math.max(index - 1, 0); highlight(); }
    else if (e.key === 'Enter') { if (items[index]) { e.preventDefault(); window.location.href = items[index].href; } }
    else if (e.key === 'Escape') { close(); }
  });

  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
      e.preventDefault();
      palette.hidden ? open() : close();
    } else if (e.key === 'Escape' && !palette.hidden) {
      close();
    }
  });
});

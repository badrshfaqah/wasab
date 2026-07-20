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

document.addEventListener('DOMContentLoaded', function () {
  var menuToggle = document.querySelector('.menu-toggle');
  var sidebar = document.querySelector('.sidebar');
  if (menuToggle && sidebar) {
    menuToggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
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

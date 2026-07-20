/**
 * عامل الخدمة (Service Worker) لوصاب: استقبال إشعارات الدفع وعرضها، وفتح الرابط
 * المناسب عند الضغط عليها. لا يقوم بأي تخزين مؤقت للصفحات عمداً - النظام لوحة
 * تحكم ببيانات حية، والتخزين المؤقت قد يعرض بيانات قديمة مضللة.
 */
self.addEventListener('install', function () {
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (event) {
  var data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch (e) {
    data = { title: 'وصاب', body: event.data ? event.data.text() : '' };
  }

  event.waitUntil(self.registration.showNotification(data.title || 'وصاب', {
    body: data.body || '',
    dir: 'rtl',
    lang: 'ar',
    icon: 'assets/img/icon-192.png',
    badge: 'assets/img/icon-192.png',
    data: { url: data.url || './' }
  }));
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  var url = (event.notification.data && event.notification.data.url) || './';

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windowClients) {
      // إن كانت هناك نافذة مفتوحة للنظام: ركّز عليها وانتقل للرابط بدل فتح نافذة جديدة.
      for (var i = 0; i < windowClients.length; i++) {
        var client = windowClients[i];
        if ('focus' in client) {
          client.focus();
          if ('navigate' in client && url) {
            return client.navigate(url);
          }
          return;
        }
      }
      return self.clients.openWindow(url);
    })
  );
});

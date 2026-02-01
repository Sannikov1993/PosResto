// Firebase Cloud Messaging Service Worker for MenuLab Courier
// Этот файл должен быть в корне public для работы FCM

importScripts('https://www.gstatic.com/firebasejs/10.7.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.7.1/firebase-messaging-compat.js');

// Firebase конфигурация (замените на свои значения из Firebase Console)
const firebaseConfig = {
  apiKey: "YOUR_API_KEY",
  authDomain: "YOUR_PROJECT_ID.firebaseapp.com",
  projectId: "YOUR_PROJECT_ID",
  storageBucket: "YOUR_PROJECT_ID.appspot.com",
  messagingSenderId: "YOUR_SENDER_ID",
  appId: "YOUR_APP_ID"
};

// Инициализация Firebase
firebase.initializeApp(firebaseConfig);

// Получение экземпляра Messaging
const messaging = firebase.messaging();

// Обработка фоновых сообщений
messaging.onBackgroundMessage((payload) => {
  console.log('[FCM SW] Background message received:', payload);

  const notificationTitle = payload.notification?.title || 'MenuLab Курьер';
  const notificationOptions = {
    body: payload.notification?.body || 'Новое уведомление',
    icon: payload.notification?.icon || '/icons/courier-icon-192.png',
    badge: '/icons/courier-badge-72.png',
    tag: payload.data?.tag || 'menulab-courier-fcm',
    vibrate: [200, 100, 200],
    data: payload.data || {},
    actions: getNotificationActions(payload.data?.type),
    requireInteraction: true
  };

  return self.registration.showNotification(notificationTitle, notificationOptions);
});

// Получение действий в зависимости от типа уведомления
function getNotificationActions(type) {
  switch (type) {
    case 'new_order':
      return [
        { action: 'accept', title: 'Принять' },
        { action: 'dismiss', title: 'Закрыть' }
      ];
    case 'order_ready':
      return [
        { action: 'pickup', title: 'Забрать' },
        { action: 'dismiss', title: 'Закрыть' }
      ];
    default:
      return [
        { action: 'open', title: 'Открыть' },
        { action: 'dismiss', title: 'Закрыть' }
      ];
  }
}

// Обработка клика по уведомлению
self.addEventListener('notificationclick', (event) => {
  console.log('[FCM SW] Notification clicked:', event.action, event.notification.data);

  event.notification.close();

  if (event.action === 'dismiss') {
    return;
  }

  let urlToOpen = '/menulab-courier.html';

  // Определяем URL в зависимости от действия и типа
  const data = event.notification.data || {};

  if (event.action === 'accept' && data.orderId) {
    urlToOpen = `/menulab-courier.html#/order/${data.orderId}?action=accept`;
  } else if (event.action === 'pickup' && data.orderId) {
    urlToOpen = `/menulab-courier.html#/order/${data.orderId}?action=pickup`;
  } else if (data.orderId) {
    urlToOpen = `/menulab-courier.html#/order/${data.orderId}`;
  }

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then((clientList) => {
        // Ищем открытое окно приложения
        for (const client of clientList) {
          if (client.url.includes('menulab-courier') && 'focus' in client) {
            // Отправляем сообщение в приложение
            client.postMessage({
              type: 'fcm-notification-click',
              action: event.action,
              data: data
            });
            return client.focus();
          }
        }

        // Открываем новое окно
        if (clients.openWindow) {
          return clients.openWindow(urlToOpen);
        }
      })
  );
});

// Обработка закрытия уведомления
self.addEventListener('notificationclose', (event) => {
  console.log('[FCM SW] Notification closed:', event.notification.tag);
});

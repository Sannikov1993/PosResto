// MenuLab Courier Service Worker
const CACHE_NAME = 'menulab-courier-v1';
const STATIC_CACHE = 'menulab-courier-static-v1';
const DATA_CACHE = 'menulab-courier-data-v1';

// Статические ресурсы для кеширования
const STATIC_ASSETS = [
  '/menulab-courier.html',
  '/manifest-courier.json',
  'https://unpkg.com/vue@3/dist/vue.global.prod.js',
  'https://cdn.tailwindcss.com'
];

// API эндпоинты для кеширования (network-first)
const API_ROUTES = [
  '/api/delivery/orders',
  '/api/delivery/couriers'
];

// Установка Service Worker
self.addEventListener('install', (event) => {
  console.log('[SW] Installing...');
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => {
        console.log('[SW] Caching static assets');
        return cache.addAll(STATIC_ASSETS);
      })
      .then(() => self.skipWaiting())
  );
});

// Активация Service Worker
self.addEventListener('activate', (event) => {
  console.log('[SW] Activating...');
  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => {
            if (cacheName !== STATIC_CACHE && cacheName !== DATA_CACHE) {
              console.log('[SW] Deleting old cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => self.clients.claim())
  );
});

// Обработка fetch запросов
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Пропускаем non-GET запросы
  if (request.method !== 'GET') {
    return;
  }

  // API запросы - network-first с fallback на кеш
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(networkFirstStrategy(request));
    return;
  }

  // Статические ресурсы - cache-first
  event.respondWith(cacheFirstStrategy(request));
});

// Стратегия Network First
async function networkFirstStrategy(request) {
  try {
    const networkResponse = await fetch(request);

    // Кешируем успешные GET запросы к API
    if (networkResponse.ok) {
      const cache = await caches.open(DATA_CACHE);
      cache.put(request, networkResponse.clone());
    }

    return networkResponse;
  } catch (error) {
    console.log('[SW] Network failed, trying cache:', request.url);
    const cachedResponse = await caches.match(request);

    if (cachedResponse) {
      return cachedResponse;
    }

    // Возвращаем offline ответ для API
    return new Response(
      JSON.stringify({
        success: false,
        message: 'Нет подключения к сети',
        offline: true
      }),
      {
        status: 503,
        headers: { 'Content-Type': 'application/json' }
      }
    );
  }
}

// Стратегия Cache First
async function cacheFirstStrategy(request) {
  const cachedResponse = await caches.match(request);

  if (cachedResponse) {
    return cachedResponse;
  }

  try {
    const networkResponse = await fetch(request);

    if (networkResponse.ok) {
      const cache = await caches.open(STATIC_CACHE);
      cache.put(request, networkResponse.clone());
    }

    return networkResponse;
  } catch (error) {
    console.log('[SW] Fetch failed:', request.url);

    // Возвращаем offline страницу для HTML
    if (request.headers.get('Accept')?.includes('text/html')) {
      return caches.match('/menulab-courier.html');
    }

    return new Response('Offline', { status: 503 });
  }
}

// Обработка push уведомлений
self.addEventListener('push', (event) => {
  console.log('[SW] Push received:', event);

  let data = {
    title: 'MenuLab Курьер',
    body: 'Новое уведомление',
    icon: '/icons/courier-icon-192.png',
    badge: '/icons/courier-badge-72.png',
    tag: 'menulab-courier',
    data: {}
  };

  if (event.data) {
    try {
      const payload = event.data.json();
      data = { ...data, ...payload };
    } catch (e) {
      data.body = event.data.text();
    }
  }

  const options = {
    body: data.body,
    icon: data.icon || '/icons/courier-icon-192.png',
    badge: data.badge || '/icons/courier-badge-72.png',
    tag: data.tag || 'menulab-courier',
    vibrate: [200, 100, 200],
    data: data.data,
    actions: data.actions || [
      { action: 'open', title: 'Открыть' },
      { action: 'dismiss', title: 'Закрыть' }
    ],
    requireInteraction: true
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Обработка клика по уведомлению
self.addEventListener('notificationclick', (event) => {
  console.log('[SW] Notification clicked:', event.action);

  event.notification.close();

  if (event.action === 'dismiss') {
    return;
  }

  // Открываем приложение
  const urlToOpen = event.notification.data?.url || '/menulab-courier.html';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then((clientList) => {
        // Ищем уже открытое окно
        for (const client of clientList) {
          if (client.url.includes('menulab-courier') && 'focus' in client) {
            client.postMessage({
              type: 'notification-click',
              data: event.notification.data
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

// Обработка сообщений от клиента
self.addEventListener('message', (event) => {
  console.log('[SW] Message received:', event.data);

  if (event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }

  if (event.data.type === 'CLEAR_CACHE') {
    event.waitUntil(
      caches.keys().then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => caches.delete(cacheName))
        );
      })
    );
  }
});

// Синхронизация в фоне (если поддерживается)
self.addEventListener('sync', (event) => {
  console.log('[SW] Background sync:', event.tag);

  if (event.tag === 'sync-location') {
    event.waitUntil(syncLocation());
  }
});

// Синхронизация геолокации
async function syncLocation() {
  try {
    const cache = await caches.open(DATA_CACHE);
    const pendingLocations = await cache.match('pending-locations');

    if (pendingLocations) {
      const locations = await pendingLocations.json();

      for (const loc of locations) {
        await fetch('/api/delivery/couriers/' + loc.courierId + '/status', {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + loc.token
          },
          body: JSON.stringify({
            location: { lat: loc.lat, lng: loc.lng }
          })
        });
      }

      await cache.delete('pending-locations');
    }
  } catch (error) {
    console.log('[SW] Sync location failed:', error);
  }
}

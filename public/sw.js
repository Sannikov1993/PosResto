const CACHE_NAME = 'menulab-waiter-v2';
const OFFLINE_URL = '/menulab-waiter.html';

// Ресурсы для кэширования (только локальные, без CDN!)
const PRECACHE_URLS = [
    '/',
    '/menulab-waiter.html',
    '/menulab-realtime.js',
];

// Установка Service Worker
self.addEventListener('install', (event) => {
    console.log('[SW] Installing...');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[SW] Caching app shell');
                return cache.addAll(PRECACHE_URLS);
            })
            .then(() => self.skipWaiting())
    );
});

// Активация - очистка старых кэшей
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating...');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((name) => name !== CACHE_NAME)
                    .map((name) => {
                        console.log('[SW] Deleting old cache:', name);
                        return caches.delete(name);
                    })
            );
        }).then(() => self.clients.claim())
    );
});

// Стратегия: Network First, fallback to Cache
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Пропускаем не-GET запросы
    if (request.method !== 'GET') {
        return;
    }

    // Пропускаем внешние CDN запросы (Vue, Tailwind и т.д.) - пусть браузер обрабатывает
    if (url.hostname !== self.location.hostname) {
        return;
    }

    // API запросы - только сеть
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    // Кэшируем успешные GET запросы меню
                    if (url.pathname === '/api/menu' && response.ok) {
                        const cloned = response.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, cloned);
                        });
                    }
                    return response;
                })
                .catch(() => {
                    // Для меню возвращаем из кэша при оффлайне
                    if (url.pathname === '/api/menu') {
                        return caches.match(request);
                    }
                    return new Response(JSON.stringify({ 
                        success: false, 
                        message: 'Нет подключения к сети' 
                    }), {
                        headers: { 'Content-Type': 'application/json' }
                    });
                })
        );
        return;
    }

    // SSE/realtime - только сеть
    if (url.pathname.includes('/realtime/')) {
        return;
    }

    // Статические ресурсы - Cache First
    event.respondWith(
        caches.match(request)
            .then((cachedResponse) => {
                if (cachedResponse) {
                    // Обновляем кэш в фоне
                    fetch(request).then((response) => {
                        if (response.ok) {
                            caches.open(CACHE_NAME).then((cache) => {
                                cache.put(request, response);
                            });
                        }
                    }).catch(() => {});
                    return cachedResponse;
                }

                return fetch(request)
                    .then((response) => {
                        if (response.ok) {
                            const cloned = response.clone();
                            caches.open(CACHE_NAME).then((cache) => {
                                cache.put(request, cloned);
                            });
                        }
                        return response;
                    })
                    .catch(() => {
                        // Возвращаем оффлайн страницу для навигации
                        if (request.mode === 'navigate') {
                            return caches.match(OFFLINE_URL);
                        }
                        return new Response('Offline', { status: 503 });
                    });
            })
    );
});

// Обработка push-уведомлений
self.addEventListener('push', (event) => {
    console.log('[SW] Push received:', event);
    
    let data = { title: 'MenuLab', body: 'Новое уведомление' };
    
    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data.body = event.data.text();
        }
    }

    const options = {
        body: data.body || data.message,
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-72.png',
        vibrate: [200, 100, 200],
        tag: data.tag || 'menulab-notification',
        data: data,
        actions: [
            { action: 'open', title: 'Открыть' },
            { action: 'close', title: 'Закрыть' }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// Клик по уведомлению
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification clicked:', event.action);
    event.notification.close();

    if (event.action === 'close') {
        return;
    }

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                // Если приложение уже открыто - фокус на него
                for (const client of clientList) {
                    if (client.url.includes('/menulab-waiter') && 'focus' in client) {
                        return client.focus();
                    }
                }
                // Иначе открываем новое окно
                if (clients.openWindow) {
                    return clients.openWindow('/menulab-waiter.html');
                }
            })
    );
});

// Синхронизация в фоне (для отложенных заказов)
self.addEventListener('sync', (event) => {
    console.log('[SW] Sync event:', event.tag);
    
    if (event.tag === 'sync-orders') {
        event.waitUntil(syncPendingOrders());
    }
});

// Синхронизация отложенных заказов
async function syncPendingOrders() {
    try {
        const cache = await caches.open(CACHE_NAME);
        const pendingOrders = await cache.match('/pending-orders');
        
        if (!pendingOrders) return;
        
        const orders = await pendingOrders.json();
        
        for (const order of orders) {
            try {
                await fetch('/api/orders', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(order)
                });
            } catch (e) {
                console.error('[SW] Failed to sync order:', e);
            }
        }
        
        // Очищаем отложенные заказы
        await cache.delete('/pending-orders');
    } catch (e) {
        console.error('[SW] Sync failed:', e);
    }
}

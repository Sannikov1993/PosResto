/**
 * PosResto Cabinet Service Worker
 * Push notifications & offline support for staff cabinet
 */

const CACHE_NAME = 'posresto-cabinet-v1';
const OFFLINE_URL = '/cabinet';

// Resources to cache
const PRECACHE_URLS = [
    '/cabinet',
    '/images/logo/posresto_icon_192.png',
    '/images/logo/posresto_icon_72.png',
];

// Install
self.addEventListener('install', (event) => {
    console.log('[Cabinet SW] Installing...');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

// Activate
self.addEventListener('activate', (event) => {
    console.log('[Cabinet SW] Activating...');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((name) => name.startsWith('posresto-cabinet-') && name !== CACHE_NAME)
                    .map((name) => caches.delete(name))
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch - Network first for API, Cache first for static
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET') return;
    if (url.hostname !== self.location.hostname) return;

    // API requests - network only
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(
            fetch(request).catch(() => {
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

    // Static resources - cache first
    event.respondWith(
        caches.match(request).then((cached) => {
            return cached || fetch(request).then((response) => {
                if (response.ok) {
                    const cloned = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, cloned));
                }
                return response;
            }).catch(() => {
                if (request.mode === 'navigate') {
                    return caches.match(OFFLINE_URL);
                }
            });
        })
    );
});

// Push notifications
self.addEventListener('push', (event) => {
    console.log('[Cabinet SW] Push received');

    let data = {
        title: 'PosResto',
        body: 'Новое уведомление',
        icon: '/images/logo/posresto_icon_192.png',
        badge: '/images/logo/posresto_icon_72.png',
    };

    if (event.data) {
        try {
            const pushData = event.data.json();
            data = { ...data, ...pushData };
        } catch (e) {
            data.body = event.data.text();
        }
    }

    const options = {
        body: data.body || data.message,
        icon: data.icon || '/images/logo/posresto_icon_192.png',
        badge: data.badge || '/images/logo/posresto_icon_72.png',
        vibrate: data.vibrate || [200, 100, 200],
        tag: data.tag || 'cabinet-' + Date.now(),
        renotify: true,
        requireInteraction: data.requireInteraction || false,
        data: {
            url: data.data?.url || '/cabinet',
            type: data.data?.type || 'general',
            ...data.data,
        },
        actions: getActionsForType(data.data?.type),
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// Get notification actions based on type
function getActionsForType(type) {
    switch (type) {
        case 'shift_reminder':
            return [
                { action: 'view_schedule', title: 'Расписание' },
                { action: 'dismiss', title: 'ОК' },
            ];
        case 'salary_paid':
        case 'bonus':
        case 'penalty':
            return [
                { action: 'view_salary', title: 'Зарплата' },
                { action: 'dismiss', title: 'ОК' },
            ];
        case 'schedule_published':
            return [
                { action: 'view_schedule', title: 'Смотреть' },
                { action: 'dismiss', title: 'Позже' },
            ];
        default:
            return [
                { action: 'open', title: 'Открыть' },
                { action: 'dismiss', title: 'Закрыть' },
            ];
    }
}

// Notification click
self.addEventListener('notificationclick', (event) => {
    console.log('[Cabinet SW] Notification clicked:', event.action);
    event.notification.close();

    if (event.action === 'dismiss') {
        return;
    }

    let targetUrl = '/cabinet';
    const data = event.notification.data;

    // Determine URL based on action or type
    if (data?.url) {
        targetUrl = data.url;
    } else {
        switch (event.action) {
            case 'view_schedule':
                targetUrl = '/cabinet#schedule';
                break;
            case 'view_salary':
                targetUrl = '/cabinet#salary';
                break;
        }
    }

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                // Focus existing window if open
                for (const client of clientList) {
                    if (client.url.includes('/cabinet') && 'focus' in client) {
                        // Navigate to specific tab if needed
                        if (targetUrl.includes('#')) {
                            client.postMessage({
                                type: 'NAVIGATE',
                                url: targetUrl,
                            });
                        }
                        return client.focus();
                    }
                }
                // Open new window
                if (clients.openWindow) {
                    return clients.openWindow(targetUrl);
                }
            })
    );
});

// Notification close
self.addEventListener('notificationclose', (event) => {
    console.log('[Cabinet SW] Notification closed');
});

// Message from client
self.addEventListener('message', (event) => {
    console.log('[Cabinet SW] Message received:', event.data);

    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

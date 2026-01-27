<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="PosResto Waiter">
    <meta name="theme-color" content="#0a0a0a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'PosResto Waiter')</title>

    <!-- PWA -->
    <link rel="manifest" href="/manifest-waiter.json">
    <link rel="apple-touch-icon" href="/icons/waiter-192.svg">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        dark: {
                            900: '#0a0a0a',
                            800: '#171717',
                            700: '#262626',
                            600: '#404040',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <style>
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
        }

        html, body {
            height: 100%;
            overflow: hidden;
            overscroll-behavior: none;
            background: #0a0a0a;
            color: #ffffff;
        }

        /* Safe areas для iPhone X+ */
        .safe-top { padding-top: env(safe-area-inset-top); }
        .safe-bottom { padding-bottom: env(safe-area-inset-bottom); }

        /* Скролл */
        .scroll-y {
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }
        .scroll-y::-webkit-scrollbar { display: none; }

        /* Анимации */
        .slide-right { animation: slideRight 0.25s ease-out; }
        @keyframes slideRight {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }

        .slide-up { animation: slideUp 0.25s ease-out; }
        @keyframes slideUp {
            from { transform: translateY(100%); opacity: 0.5; }
            to { transform: translateY(0); opacity: 1; }
        }

        .fade-in { animation: fadeIn 0.2s ease; }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Touch feedback */
        .touch-active:active {
            transform: scale(0.97);
            opacity: 0.9;
        }

        /* Статус-полоски слева */
        .status-bar {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            border-radius: 0 4px 4px 0;
        }
        .status-bar.ready { background: #22c55e; }
        .status-bar.cooking { background: #f59e0b; }
        .status-bar.pending { background: #3b82f6; }
        .status-bar.new { background: #8b5cf6; }

        /* Гостевые бейджи */
        .guest-badge {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            color: white;
        }

        /* Боковое меню */
        .side-menu {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            width: 85%;
            max-width: 400px;
            background: #171717;
            z-index: 100;
            transform: translateX(100%);
            transition: transform 0.25s ease-out;
        }
        .side-menu.open {
            transform: translateX(0);
        }
        .side-menu-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 99;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s;
        }
        .side-menu-overlay.open {
            opacity: 1;
            pointer-events: auto;
        }

        /* Toast */
        .toast {
            position: fixed;
            top: env(safe-area-inset-top, 0);
            left: 16px;
            right: 16px;
            padding: 12px 16px;
            background: #262626;
            border-radius: 12px;
            z-index: 200;
            transform: translateY(-100%);
            opacity: 0;
            transition: all 0.3s ease;
        }
        .toast.show {
            transform: translateY(16px);
            opacity: 1;
        }
    </style>

    @yield('styles')
</head>
<body class="bg-dark-900 text-white">
    <div id="app" class="h-full flex flex-col">
        @yield('content')
    </div>

    <!-- Vue 3 -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>

    <script>
        // Глобальные настройки
        window.API_URL = '{{ url("/api") }}';
        window.CSRF_TOKEN = '{{ csrf_token() }}';

        // Цвета гостей
        window.GUEST_COLORS = {
            1: '#22c55e',
            2: '#f97316',
            3: '#ec4899',
            4: '#3b82f6',
            5: '#8b5cf6',
            6: '#06b6d4',
            7: '#eab308',
            8: '#ef4444',
        };

        // Хелперы
        window.formatMoney = (amount) => {
            return new Intl.NumberFormat('ru-RU', {
                style: 'currency',
                currency: 'RUB',
                minimumFractionDigits: 0
            }).format(amount || 0);
        };

        window.api = async (url, options = {}) => {
            const response = await fetch(API_URL + url, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    ...options.headers
                }
            });
            return response.json();
        };
    </script>

    @yield('scripts')

    <!-- Service Worker -->
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw-waiter.js')
                .then(reg => console.log('SW registered'))
                .catch(err => console.log('SW registration failed:', err));
        }
    </script>
</body>
</html>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#8B5CF6">
    <title>Отслеживание заказа {{ $order->order_number }} | PosLab</title>

    <!-- Yandex Maps API -->
    @if($yandexApiKey)
    <script src="https://api-maps.yandex.ru/2.1/?apikey={{ $yandexApiKey }}&lang=ru_RU"></script>
    @else
    <script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU"></script>
    @endif

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📍</text></svg>">

    <!-- Styles -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        #tracking-app {
            height: 100%;
            width: 100%;
        }

        /* Loading state */
        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            z-index: 9999;
        }

        .loading-screen.hidden {
            display: none;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .loading-text {
            margin-top: 20px;
            font-size: 16px;
            font-weight: 500;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Error state */
        .error-screen {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #f3f4f6;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            text-align: center;
        }

        .error-screen.visible {
            display: flex;
        }

        .error-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .error-title {
            font-size: 24px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 10px;
        }

        .error-message {
            font-size: 16px;
            color: #6b7280;
            max-width: 300px;
        }
    </style>

    @vite(['resources/js/tracking/app.js'])
</head>
<body>
    <!-- Loading Screen -->
    <div id="loading-screen" class="loading-screen">
        <div class="loading-spinner"></div>
        <div class="loading-text">Загрузка карты...</div>
    </div>

    <!-- Error Screen -->
    <div id="error-screen" class="error-screen">
        <div class="error-icon">😕</div>
        <h1 class="error-title">Не удалось загрузить</h1>
        <p class="error-message">Проверьте подключение к интернету и обновите страницу</p>
    </div>

    <!-- Vue App -->
    <div id="tracking-app"
         data-token="{{ $trackingToken }}"
         data-initial='@json([
             "order_number" => $order->order_number,
             "status" => $order->status,
         ])'>
    </div>

    <script>
        // Hide loading screen when Vue app is mounted
        window.addEventListener('DOMContentLoaded', function() {
            // Wait for Yandex Maps to load
            if (typeof ymaps !== 'undefined') {
                ymaps.ready(function() {
                    setTimeout(function() {
                        document.getElementById('loading-screen').classList.add('hidden');
                    }, 500);
                });
            } else {
                // If no Yandex Maps, show error
                setTimeout(function() {
                    document.getElementById('loading-screen').classList.add('hidden');
                    document.getElementById('error-screen').classList.add('visible');
                }, 3000);
            }
        });

        // Handle Vue app mount
        document.addEventListener('vue-app-mounted', function() {
            document.getElementById('loading-screen').classList.add('hidden');
        });
    </script>
</body>
</html>

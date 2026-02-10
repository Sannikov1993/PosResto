<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#f97316">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Личный кабинет - MenuLab</title>

    <link rel="manifest" href="/manifest-cabinet.json">
    <link rel="apple-touch-icon" href="/images/logo/menulab_icon_192.png">

    @vite(['resources/css/app.css', 'resources/js/cabinet/cabinet.ts'])

    <style>
        /* Safe area support for iOS */
        .safe-top { padding-top: env(safe-area-inset-top); }
        .safe-bottom { padding-bottom: env(safe-area-inset-bottom); }

        /* Prevent overscroll bounce */
        html, body {
            overscroll-behavior: none;
            -webkit-overflow-scrolling: touch;
        }

        /* Loading state */
        #staff-cabinet:empty {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
        }
        #staff-cabinet:empty::after {
            content: '';
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div id="staff-cabinet"></div>
</body>
</html>

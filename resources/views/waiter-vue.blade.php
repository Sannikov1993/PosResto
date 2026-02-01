<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="MenuLab">
    <meta name="theme-color" content="#0a0a0a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MenuLab Официант</title>
    <link rel="icon" type="image/svg+xml" href="/images/logo/menulab_favicon.svg">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/images/logo/menulab_icon.svg">
    @vite(['resources/js/waiter/waiter.js'])
</head>
<body class="bg-dark-900">
    <div id="waiter-app"></div>
</body>
</html>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#8B5CF6">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Курьер">
    <link rel="icon" type="image/svg+xml" href="/images/logo/poslab_favicon.svg">
    <link rel="apple-touch-icon" href="/images/logo/poslab_icon.svg">
    <link rel="manifest" href="/manifest-courier.json">
    <title>PosLab Курьер</title>
    @vite(['resources/js/courier/courier.js'])
</head>
<body class="bg-gray-100">
    <div id="app"></div>
</body>
</html>

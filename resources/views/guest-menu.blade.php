<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#F97316">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="icon" type="image/svg+xml" href="/images/logo/poslab_favicon.svg">
    <link rel="manifest" href="/manifest-guest.json">
    <title>Меню</title>
    @vite(['resources/js/guest-menu/guest-menu.js'])
</head>
<body class="bg-gray-100">
    <div id="app"></div>
</body>
</html>

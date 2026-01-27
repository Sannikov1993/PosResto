<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="/images/logo/poslab_favicon.svg">
    <title>PosLab - Гостевой сервис</title>
    @vite(['resources/js/guest-admin/guest-admin.js'])
</head>
<body class="bg-gray-100">
    <div id="app"></div>
</body>
</html>

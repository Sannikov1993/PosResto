<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Регистрация - MenuLab</title>
    <link rel="icon" type="image/svg+xml" href="/images/logo/menulab_icon.svg">
    @vite(['resources/js/register-tenant/register-tenant.ts'])
</head>
<body>
    <div id="app"></div>
</body>
</html>

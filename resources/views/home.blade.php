<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="/images/logo/posresto_favicon.svg">
    <link rel="apple-touch-icon" href="/images/logo/posresto_icon.svg">
    <title>PosResto - CRM System</title>
    @vite(['resources/js/home/home.js'])
</head>
<body class="bg-slate-900">
    <div id="app"></div>
</body>
</html>

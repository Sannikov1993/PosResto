<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="/images/logo/posresto_favicon.svg">
    <title>PosResto - Realtime Monitor</title>
    @vite(['resources/js/realtime-monitor/realtime-monitor.js'])
</head>
<body class="bg-gray-900">
    <div id="app"></div>
</body>
</html>

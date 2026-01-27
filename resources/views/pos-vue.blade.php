<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PosResto POS</title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="/images/logo/posresto_favicon.svg">
    <link rel="apple-touch-icon" href="/images/logo/posresto_icon.svg">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Vite -->
    @vite(['resources/js/pos/pos.js'])
</head>
<body class="bg-dark-950 text-white" style="background-color: #0a0a0f;">
    <div id="pos-app"></div>
</body>
</html>

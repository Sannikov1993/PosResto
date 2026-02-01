<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MenuLab BackOffice</title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="/images/logo/menulab_favicon.svg">
    <link rel="apple-touch-icon" href="/images/logo/menulab_icon.svg">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Vite -->
    @vite(['resources/js/backoffice/backoffice.js'])
</head>
<body class="bg-gray-50">
    <div id="backoffice-app"></div>
</body>
</html>

<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MenuLab API Routes v2.1.0
|--------------------------------------------------------------------------
|
| Роуты разбиты по доменам в директории routes/api/
|
| auth.php          — Авторизация, регистрация
| orders.php        — Заказы, отмены, списания, POS-алиасы
| menu.php          — Меню, прайс-листы
| tables.php        — Столы, зоны, бронирование, рестораны
| staff.php         — Персонал, зарплаты, расписание, кабинет, роли
| kitchen.php       — Кухня: станции, устройства, бар, табло, стоп-лист
| inventory.php     — Склад, ингредиенты, накладные, рецепты
| loyalty.php       — Лояльность, сертификаты, клиенты
| finance.php       — Финансы, фискализация, принтеры, настройки, аналитика
| delivery.php      — Доставка, трекинг, официант
| notifications.php — Уведомления, Telegram, realtime, гостевое меню
| backoffice.php    — Бэк-офис (параллельный API)
| attendance.php    — Учёт рабочего времени, биометрия
|
*/

Route::get('/', function () {
    return response()->json([
        'name' => 'MenuLab API',
        'version' => '2.1.0',
        'status' => 'running',
        'features' => [
            'orders', 'menu', 'tables', 'reservations', 'realtime',
            'staff', 'inventory', 'loyalty', 'analytics', 'printing',
            'guest_menu', 'fiscal', 'finance', 'settings'
        ],
        'public_api' => [
            'v1' => '/api/v1',
            'docs' => '/api/v1/docs',
        ],
    ]);
});

// ============================================================
// Public API v1 (Enterprise integrations)
// ============================================================
Route::prefix('v1')->group(function () {
    require __DIR__.'/api/v1.php';
});

// Подключение модулей
require __DIR__.'/api/auth.php';
require __DIR__.'/api/orders.php';
require __DIR__.'/api/menu.php';
require __DIR__.'/api/tables.php';
require __DIR__.'/api/staff.php';
require __DIR__.'/api/kitchen.php';
require __DIR__.'/api/inventory.php';
require __DIR__.'/api/loyalty.php';
require __DIR__.'/api/finance.php';
require __DIR__.'/api/delivery.php';
require __DIR__.'/api/notifications.php';
require __DIR__.'/api/backoffice.php';
require __DIR__.'/api/attendance.php';

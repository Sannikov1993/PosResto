<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\Pos\TableOrderController;
use App\Http\Controllers\Pos\ReservationController as PosReservationController;
use App\Http\Controllers\WaiterController;
use App\Http\Controllers\TrackingController;

/*
|--------------------------------------------------------------------------
| PosResto Web Routes
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| POS - Заказ на стол (Vue 3 + раздельная оплата)
|--------------------------------------------------------------------------
*/
Route::prefix('pos')->name('pos.')->group(function () {
    // Бар - виртуальный "стол" для барных заказов
    Route::get('/bar/data', [TableOrderController::class, 'getBarData'])->name('bar.data');
    Route::post('/bar/order', [TableOrderController::class, 'storeBarOrder'])->name('bar.order.store');
    Route::post('/bar/order/{order}/item', [TableOrderController::class, 'addBarItem'])->name('bar.order.addItem');
    Route::patch('/bar/order/{order}/item/{item}', [TableOrderController::class, 'updateBarItem'])->name('bar.order.updateItem');
    Route::delete('/bar/order/{order}/item/{item}', [TableOrderController::class, 'removeBarItem'])->name('bar.order.removeItem');
    Route::post('/bar/order/{order}/send-kitchen', [TableOrderController::class, 'sendBarToKitchen'])->name('bar.order.sendKitchen');
    Route::post('/bar/order/{order}/payment', [TableOrderController::class, 'barPayment'])->name('bar.order.payment');

    Route::get('/table/{table}', [TableOrderController::class, 'show'])->name('table.order');
    Route::get('/table/{table}/vue', [TableOrderController::class, 'showVue'])->name('table.order.vue');
    Route::get('/table/{table}/data', [TableOrderController::class, 'getData'])->name('table.order.data');
    Route::get('/table/{table}/menu', [TableOrderController::class, 'getMenu'])->name('table.menu');
    Route::post('/table/{table}/order', [TableOrderController::class, 'store'])->name('table.order.store');
    Route::post('/table/{table}/order/{order}/item', [TableOrderController::class, 'addItem'])->name('table.order.addItem');
    Route::patch('/table/{table}/order/{order}/item/{item}', [TableOrderController::class, 'updateItem'])->name('table.order.updateItem');
    Route::delete('/table/{table}/order/{order}/item/{item}', [TableOrderController::class, 'removeItem'])->name('table.order.removeItem');
    Route::post('/table/{table}/order/{order}/send-kitchen', [TableOrderController::class, 'sendToKitchen'])->name('table.order.sendKitchen');
    Route::post('/table/{table}/order/{order}/save-preorder', [TableOrderController::class, 'savePreorder'])->name('table.order.savePreorder');
    Route::post('/table/{table}/order/{order}/payment', [TableOrderController::class, 'payment'])->name('table.order.payment');
    Route::post('/table/{table}/order/{order}/discount', [TableOrderController::class, 'applyDiscount'])->name('table.order.discount');
    Route::post('/table/{table}/order/{order}/discount/preview', [TableOrderController::class, 'calculateDiscountPreview'])->name('table.order.discount.preview');
    Route::delete('/table/{table}/order/{order}', [TableOrderController::class, 'closeEmptyOrder'])->name('table.order.close');
    Route::post('/table/{table}/cleanup', [TableOrderController::class, 'cleanupEmptyOrders'])->name('table.cleanup');
    Route::post('/table/{table}/reservation', [PosReservationController::class, 'store'])->name('table.reservation.store');
    Route::get('/table/{table}/reservation/slots', [PosReservationController::class, 'getAvailableSlots'])->name('table.reservation.slots');
});

// Страница заказа для стола (полноэкранная) - Compact Pro
Route::get('/order/table/{table}', [OrderController::class, 'tableOrder'])->name('order.table');

// Старый вариант (оставляем для совместимости)
Route::get('/hall/table/{table}', [OrderController::class, 'tableView'])->name('hall.table');
Route::get('/hall/table/{table}/booking', [ReservationController::class, 'tableBooking'])->name('hall.table.booking');

// Гостевое меню - редирект на Vue SPA
Route::get('/menu/{code}', function ($code) {
    return redirect('/guest-menu#' . $code);
});

// Отзыв по номеру заказа
Route::get('/review/{orderNumber}', function ($orderNumber) {
    return redirect('/guest-menu?review=' . $orderNumber);
});

// Регистрация сотрудника по приглашению
Route::get('/register/invite/{token}', function ($token) {
    return response()->file(public_path('register-invite.html'));
});

// Единая страница входа для сотрудников
Route::get('/login', function () {
    return response()->file(public_path('staff-login.html'));
})->name('staff.login');

// Восстановление пароля
Route::get('/forgot-password', function () {
    return response()->file(public_path('forgot-password.html'));
})->name('password.request');

Route::get('/reset-password', function () {
    return response()->file(public_path('reset-password.html'));
})->name('password.reset');

// POS терминал
Route::get('/pos', function () {
    return view('pos-vue');
})->name('pos');

// Kitchen Display (кухня)
Route::get('/kitchen', function () {
    return view('kitchen');
})->name('kitchen');

// Waiter PWA (официант)
Route::get('/waiter', function () {
    return view('waiter-vue');
})->name('waiter');

// BackOffice (бэк-офис)
Route::get('/backoffice', function () {
    return view('backoffice');
})->name('backoffice');

// Floor Editor (редактор зала)
Route::get('/floor-editor', function () {
    return view('floor-editor');
})->name('floor-editor');

// Courier PWA (курьер)
Route::get('/courier', function () {
    return view('courier');
})->name('courier');

// Staff Cabinet (личный кабинет сотрудника)
Route::get('/cabinet', function () {
    return view('cabinet');
})->name('cabinet');

// Reservations (бронирования)
Route::get('/reservations', function () {
    return view('reservations');
})->name('reservations');

// Admin Panel
Route::get('/admin', function () {
    return view('admin');
})->name('admin');

// Vue Guest Admin (управление гостевым сервисом)
Route::get('/guest-admin', function () {
    return view('guest-admin');
})->name('guest-admin');

// Vue Realtime Monitor (отладка SSE)
Route::get('/realtime-monitor', function () {
    return view('realtime-monitor');
})->name('realtime-monitor');

// Vue Guest Menu (гостевое меню)
Route::get('/guest-menu', function () {
    return view('guest-menu');
})->name('guest-menu');

// Главная страница - навигация CRM (Vue)
Route::get('/', function () {
    return view('home');
})->name('home');

/*
|--------------------------------------------------------------------------
| Модуль доставки (Blade)
|--------------------------------------------------------------------------
*/
Route::prefix('delivery')->name('delivery.')->group(function () {
    Route::get('/', [DeliveryController::class, 'index'])->name('index');
    Route::post('/orders', [DeliveryController::class, 'store'])->name('orders.store');
    Route::get('/orders/{order}', [DeliveryController::class, 'show'])->name('orders.show');
    Route::put('/orders/{order}/status', [DeliveryController::class, 'updateStatus'])->name('orders.status');
    Route::post('/orders/{order}/courier', [DeliveryController::class, 'assignCourier'])->name('orders.courier');

    Route::get('/couriers', [DeliveryController::class, 'couriers'])->name('couriers');
    Route::get('/products', [DeliveryController::class, 'products'])->name('products');
    Route::post('/detect-zone', [DeliveryController::class, 'detectZone'])->name('detect-zone');
    Route::get('/suggest-address', [DeliveryController::class, 'suggestAddress'])->name('suggest-address');
    Route::get('/search-customer', [DeliveryController::class, 'searchCustomer'])->name('search-customer');
    Route::get('/analytics', [DeliveryController::class, 'analytics'])->name('analytics');
});

/*
|--------------------------------------------------------------------------
| Публичный трекинг заказа (для клиентов)
|--------------------------------------------------------------------------
*/
Route::prefix('track')->name('tracking.')->group(function () {
    Route::get('/', [TrackingController::class, 'index'])->name('index');
    Route::post('/search', [TrackingController::class, 'search'])->name('search');
    Route::get('/{orderNumber}', [TrackingController::class, 'show'])->name('show');
    Route::get('/{orderNumber}/live', [TrackingController::class, 'showLive'])->name('live');
});

// API для автообновления статуса трекинга
Route::get('/api/track/{orderNumber}/status', [TrackingController::class, 'status'])->name('tracking.status');

/*
|--------------------------------------------------------------------------
| PWA Официанта
|--------------------------------------------------------------------------
*/
Route::prefix('waiter')->name('waiter.')->group(function () {
    Route::get('/', [WaiterController::class, 'hall'])->name('index');
    Route::get('/hall', [WaiterController::class, 'hall'])->name('hall');
    Route::get('/table/{id}', [WaiterController::class, 'table'])->name('table');
    Route::get('/orders', [WaiterController::class, 'orders'])->name('orders');
    Route::get('/profile', [WaiterController::class, 'profile'])->name('profile');
});

// Logout
Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
})->name('logout');
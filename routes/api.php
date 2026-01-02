<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\RealtimeController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\LoyaltyController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\PrinterController;
use App\Http\Controllers\Api\GuestMenuController;

/*
|--------------------------------------------------------------------------
| PosLab API Routes v2.0.0 - FINAL
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return response()->json([
        'name' => 'PosLab API',
        'version' => '2.0.0',
        'status' => 'running',
        'features' => [
            'orders', 'menu', 'tables', 'reservations', 'realtime', 
            'staff', 'inventory', 'loyalty', 'analytics', 'printing', 'guest_menu'
        ],
    ]);
});

// =====================================================
// АВТОРИЗАЦИЯ
// =====================================================
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/login-pin', [AuthController::class, 'loginByPin']);
    Route::get('/check', [AuthController::class, 'check']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/users', [AuthController::class, 'users']);
    Route::post('/change-pin', [AuthController::class, 'changePin']);
});

// =====================================================
// ЗАКАЗЫ
// =====================================================
Route::prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::post('/', [OrderController::class, 'store']);
    Route::get('/{order}', [OrderController::class, 'show']);
    Route::put('/{order}', [OrderController::class, 'update']);
    Route::patch('/{order}/status', [OrderController::class, 'updateStatus']);
    Route::post('/{order}/pay', [OrderController::class, 'pay']);
    Route::patch('/{order}/delivery-status', [OrderController::class, 'updateDeliveryStatus']);
    Route::post('/{order}/assign-courier', [OrderController::class, 'assignCourier']);
    Route::post('/{order}/items', [OrderController::class, 'addItem']);
    Route::delete('/{order}/items/{item}', [OrderController::class, 'removeItem']);
    Route::post('/{order}/print/receipt', [PrinterController::class, 'printReceipt']);
    Route::post('/{order}/print/precheck', [PrinterController::class, 'printPrecheck']);
    Route::post('/{order}/print/kitchen', [PrinterController::class, 'printToKitchen']);
    Route::get('/{order}/print/data', [PrinterController::class, 'getReceiptData']);
});

// =====================================================
// МЕНЮ
// =====================================================
Route::prefix('menu')->group(function () {
    Route::get('/', [MenuController::class, 'index']);
    Route::get('/categories', [MenuController::class, 'categories']);
    Route::post('/categories', [MenuController::class, 'storeCategory']);
    Route::put('/categories/{category}', [MenuController::class, 'updateCategory']);
    Route::delete('/categories/{category}', [MenuController::class, 'destroyCategory']);
    Route::get('/dishes', [MenuController::class, 'dishes']);
    Route::post('/dishes', [MenuController::class, 'storeDish']);
    Route::get('/dishes/{dish}', [MenuController::class, 'showDish']);
    Route::put('/dishes/{dish}', [MenuController::class, 'updateDish']);
    Route::delete('/dishes/{dish}', [MenuController::class, 'destroyDish']);
    Route::patch('/dishes/{dish}/toggle', [MenuController::class, 'toggleAvailability']);
    Route::get('/modifiers', [MenuController::class, 'modifiers']);
});

// =====================================================
// СТОЛЫ
// =====================================================
Route::prefix('tables')->group(function () {
    Route::get('/floor-plan', [TableController::class, 'floorPlan']);
    Route::post('/layout', [TableController::class, 'saveLayout']);
    Route::get('/zones', [TableController::class, 'zones']);
    Route::post('/zones', [TableController::class, 'storeZone']);
    Route::put('/zones/{zone}', [TableController::class, 'updateZone']);
    Route::delete('/zones/{zone}', [TableController::class, 'destroyZone']);
    Route::get('/', [TableController::class, 'index']);
    Route::post('/', [TableController::class, 'store']);
    Route::get('/{table}', [TableController::class, 'show']);
    Route::put('/{table}', [TableController::class, 'update']);
    Route::delete('/{table}', [TableController::class, 'destroy']);
    Route::patch('/{table}/status', [TableController::class, 'updateStatus']);
});

// =====================================================
// БРОНИРОВАНИЕ
// =====================================================
Route::prefix('reservations')->group(function () {
    Route::get('/', [ReservationController::class, 'index']);
    Route::post('/', [ReservationController::class, 'store']);
    Route::get('/calendar', [ReservationController::class, 'calendar']);
    Route::get('/stats', [ReservationController::class, 'stats']);
    Route::get('/available-slots', [ReservationController::class, 'availableSlots']);
    Route::get('/{reservation}', [ReservationController::class, 'show']);
    Route::put('/{reservation}', [ReservationController::class, 'update']);
    Route::delete('/{reservation}', [ReservationController::class, 'destroy']);
    Route::post('/{reservation}/confirm', [ReservationController::class, 'confirm']);
    Route::post('/{reservation}/cancel', [ReservationController::class, 'cancel']);
    Route::post('/{reservation}/seat', [ReservationController::class, 'seat']);
    Route::post('/{reservation}/complete', [ReservationController::class, 'complete']);
    Route::post('/{reservation}/no-show', [ReservationController::class, 'noShow']);
});

// =====================================================
// REAL-TIME
// =====================================================
Route::prefix('realtime')->group(function () {
    Route::get('/stream', [RealtimeController::class, 'stream']);
    Route::get('/poll', [RealtimeController::class, 'poll']);
    Route::get('/recent', [RealtimeController::class, 'recent']);
    Route::post('/send', [RealtimeController::class, 'send']);
    Route::get('/status', [RealtimeController::class, 'status']);
    Route::post('/cleanup', [RealtimeController::class, 'cleanup']);
});

// =====================================================
// ПЕРСОНАЛ
// =====================================================
Route::prefix('staff')->group(function () {
    Route::get('/', [StaffController::class, 'index']);
    Route::get('/schedule', [StaffController::class, 'weekSchedule']);
    Route::get('/shifts', [StaffController::class, 'shifts']);
    Route::post('/shifts', [StaffController::class, 'createShift']);
    Route::put('/shifts/{shift}', [StaffController::class, 'updateShift']);
    Route::delete('/shifts/{shift}', [StaffController::class, 'deleteShift']);
    Route::post('/clock-in', [StaffController::class, 'clockIn']);
    Route::post('/clock-out', [StaffController::class, 'clockOut']);
    Route::get('/time-entries', [StaffController::class, 'timeEntries']);
    Route::get('/working-now', [StaffController::class, 'whoIsWorking']);
    Route::get('/tips', [StaffController::class, 'tips']);
    Route::post('/tips', [StaffController::class, 'addTip']);
    Route::get('/stats', [StaffController::class, 'stats']);
    Route::get('/{user}', [StaffController::class, 'show']);
    Route::put('/{user}', [StaffController::class, 'update']);
    Route::get('/{user}/report', [StaffController::class, 'userReport']);
});

// =====================================================
// СКЛАД
// =====================================================
Route::prefix('inventory')->group(function () {
    Route::get('/ingredients', [InventoryController::class, 'ingredients']);
    Route::post('/ingredients', [InventoryController::class, 'storeIngredient']);
    Route::put('/ingredients/{ingredient}', [InventoryController::class, 'updateIngredient']);
    Route::delete('/ingredients/{ingredient}', [InventoryController::class, 'destroyIngredient']);
    Route::get('/categories', [InventoryController::class, 'categories']);
    Route::get('/units', [InventoryController::class, 'units']);
    Route::get('/movements', [InventoryController::class, 'movements']);
    Route::post('/stock/income', [InventoryController::class, 'stockIncome']);
    Route::post('/stock/write-off', [InventoryController::class, 'stockWriteOff']);
    Route::get('/recipes', [InventoryController::class, 'recipes']);
    Route::post('/recipes', [InventoryController::class, 'storeRecipe']);
    Route::get('/recipes/{recipe}', [InventoryController::class, 'showRecipe']);
    Route::put('/recipes/{recipe}', [InventoryController::class, 'updateRecipe']);
    Route::delete('/recipes/{recipe}', [InventoryController::class, 'destroyRecipe']);
    Route::get('/suppliers', [InventoryController::class, 'suppliers']);
    Route::post('/suppliers', [InventoryController::class, 'storeSupplier']);
    Route::put('/suppliers/{supplier}', [InventoryController::class, 'updateSupplier']);
    Route::get('/checks', [InventoryController::class, 'inventoryChecks']);
    Route::post('/checks', [InventoryController::class, 'createInventoryCheck']);
    Route::get('/checks/{inventoryCheck}', [InventoryController::class, 'showInventoryCheck']);
    Route::put('/checks/{inventoryCheck}/items/{item}', [InventoryController::class, 'updateInventoryCheckItem']);
    Route::post('/checks/{inventoryCheck}/complete', [InventoryController::class, 'completeInventoryCheck']);
    Route::get('/stats', [InventoryController::class, 'stats']);
    Route::get('/alerts/low-stock', [InventoryController::class, 'lowStockAlerts']);
});

// =====================================================
// ПРОГРАММА ЛОЯЛЬНОСТИ
// =====================================================
Route::prefix('loyalty')->group(function () {
    Route::get('/levels', [LoyaltyController::class, 'levels']);
    Route::post('/levels', [LoyaltyController::class, 'storeLevel']);
    Route::put('/levels/{level}', [LoyaltyController::class, 'updateLevel']);
    Route::delete('/levels/{level}', [LoyaltyController::class, 'destroyLevel']);
    Route::get('/promo-codes', [LoyaltyController::class, 'promoCodes']);
    Route::post('/promo-codes', [LoyaltyController::class, 'storePromoCode']);
    Route::put('/promo-codes/{promoCode}', [LoyaltyController::class, 'updatePromoCode']);
    Route::delete('/promo-codes/{promoCode}', [LoyaltyController::class, 'destroyPromoCode']);
    Route::post('/promo-codes/validate', [LoyaltyController::class, 'validatePromoCode']);
    Route::get('/bonus-history', [LoyaltyController::class, 'bonusHistory']);
    Route::post('/bonus/earn', [LoyaltyController::class, 'earnBonus']);
    Route::post('/bonus/spend', [LoyaltyController::class, 'spendBonus']);
    Route::post('/calculate', [LoyaltyController::class, 'calculateDiscount']);
    Route::get('/settings', [LoyaltyController::class, 'settings']);
    Route::put('/settings', [LoyaltyController::class, 'updateSettings']);
    Route::get('/stats', [LoyaltyController::class, 'stats']);
    Route::post('/recalculate-level', [LoyaltyController::class, 'recalculateCustomerLevel']);
});

// =====================================================
// АНАЛИТИКА
// =====================================================
Route::prefix('analytics')->group(function () {
    Route::get('/dashboard', [AnalyticsController::class, 'dashboard']);
    Route::get('/abc', [AnalyticsController::class, 'abcAnalysis']);
    Route::get('/forecast', [AnalyticsController::class, 'salesForecast']);
    Route::get('/comparison', [AnalyticsController::class, 'periodComparison']);
    Route::get('/waiters', [AnalyticsController::class, 'waiterReport']);
    Route::get('/hourly', [AnalyticsController::class, 'hourlyAnalysis']);
    Route::get('/categories', [AnalyticsController::class, 'categoryAnalysis']);
    Route::get('/export/sales', [AnalyticsController::class, 'exportSales']);
    Route::get('/export/abc', [AnalyticsController::class, 'exportAbc']);
});

// =====================================================
// ПРИНТЕРЫ
// =====================================================
Route::prefix('printers')->group(function () {
    Route::get('/', [PrinterController::class, 'index']);
    Route::post('/', [PrinterController::class, 'store']);
    Route::put('/{printer}', [PrinterController::class, 'update']);
    Route::delete('/{printer}', [PrinterController::class, 'destroy']);
    Route::post('/{printer}/test', [PrinterController::class, 'test']);
    Route::get('/{printer}/check', [PrinterController::class, 'checkConnection']);
    Route::get('/queue', [PrinterController::class, 'queue']);
    Route::post('/jobs/{job}/retry', [PrinterController::class, 'retryJob']);
    Route::delete('/jobs/{job}', [PrinterController::class, 'cancelJob']);
    Route::post('/report', [PrinterController::class, 'printReport']);
});

// =====================================================
// ГОСТЕВОЕ МЕНЮ (публичные и админ эндпоинты)
// =====================================================
Route::prefix('guest')->group(function () {
    // Публичные (для гостей)
    Route::get('/menu/{code}', [GuestMenuController::class, 'getMenuByCode']);
    Route::get('/dish/{dish}', [GuestMenuController::class, 'getDish']);
    Route::post('/call', [GuestMenuController::class, 'callWaiter']);
    Route::post('/call/cancel', [GuestMenuController::class, 'cancelCall']);
    Route::post('/review', [GuestMenuController::class, 'submitReview']);
    
    // Админ
    Route::get('/calls', [GuestMenuController::class, 'activeCalls']);
    Route::post('/calls/{call}/accept', [GuestMenuController::class, 'acceptCall']);
    Route::post('/calls/{call}/complete', [GuestMenuController::class, 'completeCall']);
    
    Route::get('/reviews', [GuestMenuController::class, 'reviews']);
    Route::get('/reviews/stats', [GuestMenuController::class, 'reviewStats']);
    Route::post('/reviews/{review}/toggle', [GuestMenuController::class, 'toggleReview']);
    Route::post('/reviews/{review}/respond', [GuestMenuController::class, 'respondToReview']);
    
    Route::get('/qr-codes', [GuestMenuController::class, 'qrCodes']);
    Route::post('/qr-codes', [GuestMenuController::class, 'generateQr']);
    Route::post('/qr-codes/generate-all', [GuestMenuController::class, 'generateAllQr']);
    Route::post('/qr-codes/{qrCode}/regenerate', [GuestMenuController::class, 'regenerateQr']);
    Route::post('/qr-codes/{qrCode}/toggle', [GuestMenuController::class, 'toggleQr']);
    
    Route::get('/settings', [GuestMenuController::class, 'settings']);
    Route::put('/settings', [GuestMenuController::class, 'updateSettings']);
});

// =====================================================
// КЛИЕНТЫ
// =====================================================
Route::prefix('customers')->group(function () {
    Route::get('/', [CustomerController::class, 'index']);
    Route::post('/', [CustomerController::class, 'store']);
    Route::get('/search', [CustomerController::class, 'search']);
    Route::get('/top', [CustomerController::class, 'top']);
    Route::get('/birthdays', [CustomerController::class, 'birthdays']);
    Route::get('/{customer}', [CustomerController::class, 'show']);
    Route::put('/{customer}', [CustomerController::class, 'update']);
    Route::delete('/{customer}', [CustomerController::class, 'destroy']);
    Route::post('/{customer}/bonus/add', [CustomerController::class, 'addBonus']);
    Route::post('/{customer}/bonus/use', [CustomerController::class, 'useBonus']);
});

// =====================================================
// ДАШБОРД
// =====================================================
Route::prefix('dashboard')->group(function () {
    Route::get('/', [DashboardController::class, 'index']);
    Route::get('/stats', [DashboardController::class, 'stats']);
    Route::get('/sales', [DashboardController::class, 'sales']);
    Route::get('/popular-dishes', [DashboardController::class, 'popularDishes']);
});

// =====================================================
// ОТЧЁТЫ
// =====================================================
Route::prefix('reports')->group(function () {
    Route::get('/sales', [DashboardController::class, 'salesReport']);
    Route::get('/dishes', [DashboardController::class, 'dishesReport']);
    Route::get('/hourly', [DashboardController::class, 'hourlyReport']);
});
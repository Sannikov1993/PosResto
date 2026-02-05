<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\StaffManagementController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\LoyaltyController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\PrinterController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\StaffScheduleController;
use App\Http\Controllers\Api\SalaryController;
use App\Http\Controllers\Api\PriceListController;
use App\Http\Controllers\Api\ApiClientController;

// =====================================================
// BACKOFFICE API - Единый префикс для бэк-офиса
// =====================================================

// Публичные маршруты (без auth middleware)
Route::prefix('backoffice')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/me', [AuthController::class, 'check']);
});

// Защищённые маршруты
Route::prefix('backoffice')->middleware('auth.api_token')->group(function () {

    // Дашборд
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Персонал
    Route::get('/staff', [StaffController::class, 'index']);
    Route::post('/staff', [StaffController::class, 'store']);
    Route::get('/staff/{user}', [StaffController::class, 'show']);
    Route::put('/staff/{user}', [StaffController::class, 'update']);
    Route::delete('/staff/{user}', [StaffController::class, 'destroy']);
    Route::post('/staff/{user}/toggle-active', [StaffController::class, 'toggleActive']);
    Route::post('/staff/{user}/invite', [StaffController::class, 'sendInvite']);
    Route::post('/staff/{user}/password-reset', [StaffController::class, 'sendPasswordReset']);
    Route::post('/staff/{user}/fire', [StaffManagementController::class, 'fire']);
    Route::post('/staff/{user}/restore', [StaffManagementController::class, 'restore']);

    // Приглашения персонала
    Route::get('/invitations', [StaffController::class, 'invitations']);
    Route::post('/invitations', [StaffManagementController::class, 'createInvitation']);
    Route::post('/invitations/{invitation}/resend', [StaffController::class, 'resendInvitation']);
    Route::delete('/invitations/{invitation}', [StaffController::class, 'cancelInvitation']);

    // Роли
    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::put('/roles/{role}', [RoleController::class, 'update']);
    Route::delete('/roles/{role}', [RoleController::class, 'destroy']);

    // Расписание персонала
    Route::get('/schedule', [StaffScheduleController::class, 'index']);
    Route::get('/schedule/stats', [StaffScheduleController::class, 'weekStats']);
    Route::get('/schedule/templates', [StaffScheduleController::class, 'templates']);
    Route::post('/schedule', [StaffScheduleController::class, 'store']);
    Route::put('/schedule/{schedule}', [StaffScheduleController::class, 'update']);
    Route::delete('/schedule/{schedule}', [StaffScheduleController::class, 'destroy']);
    Route::post('/schedule/publish', [StaffScheduleController::class, 'publishWeek']);
    Route::post('/schedule/copy-week', [StaffScheduleController::class, 'copyWeek']);
    Route::post('/schedule/templates', [StaffScheduleController::class, 'storeTemplate']);
    Route::put('/schedule/templates/{template}', [StaffScheduleController::class, 'updateTemplate']);
    Route::delete('/schedule/templates/{template}', [StaffScheduleController::class, 'destroyTemplate']);

    // Зоны и столы
    Route::get('/zones', [TableController::class, 'zones']);
    Route::post('/zones', [TableController::class, 'storeZone']);
    Route::put('/zones/{zone}', [TableController::class, 'updateZone']);
    Route::delete('/zones/{zone}', [TableController::class, 'destroyZone']);

    Route::get('/tables', [TableController::class, 'index']);
    Route::post('/tables', [TableController::class, 'store']);
    Route::put('/tables/{table}', [TableController::class, 'update']);
    Route::delete('/tables/{table}', [TableController::class, 'destroy']);

    // Клиенты
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::get('/customers/{customer}', [CustomerController::class, 'show']);
    Route::put('/customers/{customer}', [CustomerController::class, 'update']);
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy']);
    Route::post('/customers/{customer}/bonus', [CustomerController::class, 'addBonus']);

    // Склад
    Route::prefix('inventory')->group(function () {
        Route::get('/ingredients', [InventoryController::class, 'ingredients']);
        Route::post('/ingredients', [InventoryController::class, 'storeIngredient']);
        Route::put('/ingredients/{ingredient}', [InventoryController::class, 'updateIngredient']);
        Route::delete('/ingredients/{ingredient}', [InventoryController::class, 'destroyIngredient']);

        Route::get('/units', [InventoryController::class, 'units']);
        Route::get('/categories', [InventoryController::class, 'categories']);
        Route::get('/warehouses', [InventoryController::class, 'warehouses']);
        Route::get('/suppliers', [InventoryController::class, 'suppliers']);
        Route::post('/suppliers', [InventoryController::class, 'storeSupplier']);
        Route::put('/suppliers/{supplier}', [InventoryController::class, 'updateSupplier']);
        Route::delete('/suppliers/{supplier}', [InventoryController::class, 'destroySupplier']);

        Route::get('/movements', [InventoryController::class, 'movements']);
        Route::post('/quick-income', [InventoryController::class, 'quickIncome']);
        Route::post('/quick-write-off', [InventoryController::class, 'quickWriteOff']);

        Route::get('/checks', [InventoryController::class, 'inventoryChecks']);
        Route::post('/checks', [InventoryController::class, 'storeInventoryCheck']);
        Route::get('/checks/{inventoryCheck}', [InventoryController::class, 'showInventoryCheck']);
        Route::put('/checks/{inventoryCheck}/items/{item}', [InventoryController::class, 'updateCheckItem']);
        Route::post('/checks/{inventoryCheck}/complete', [InventoryController::class, 'completeInventoryCheck']);
        Route::post('/checks/{inventoryCheck}/cancel', [InventoryController::class, 'cancelInventoryCheck']);
    });

    // Меню
    Route::prefix('menu')->group(function () {
        Route::get('/categories', [MenuController::class, 'categories']);
        Route::post('/categories', [MenuController::class, 'storeCategory']);
        Route::put('/categories/{category}', [MenuController::class, 'updateCategory']);
        Route::delete('/categories/{category}', [MenuController::class, 'destroyCategory']);

        Route::get('/dishes', [MenuController::class, 'dishes']);
        Route::post('/dishes', [MenuController::class, 'storeDish']);
        Route::get('/dishes/{dish}', [MenuController::class, 'showDish']);
        Route::put('/dishes/{dish}', [MenuController::class, 'updateDish']);
        Route::delete('/dishes/{dish}', [MenuController::class, 'destroyDish']);

        Route::get('/dishes/{dish}/recipe', [InventoryController::class, 'dishRecipe']);
        Route::post('/dishes/{dish}/recipe', [InventoryController::class, 'saveDishRecipe']);

        Route::get('/dishes/{dish}/modifiers', [\App\Http\Controllers\Api\ModifierController::class, 'dishModifiers']);
        Route::post('/dishes/{dish}/modifiers', [\App\Http\Controllers\Api\ModifierController::class, 'saveDishModifiers']);
    });

    // Прайс-листы
    Route::prefix('price-lists')->group(function () {
        Route::get('/', [PriceListController::class, 'index']);
        Route::post('/', [PriceListController::class, 'store']);
        Route::get('/{priceList}', [PriceListController::class, 'show']);
        Route::put('/{priceList}', [PriceListController::class, 'update']);
        Route::delete('/{priceList}', [PriceListController::class, 'destroy']);
        Route::post('/{priceList}/toggle', [PriceListController::class, 'toggle']);
        Route::post('/{priceList}/default', [PriceListController::class, 'setDefault']);
        Route::get('/{priceList}/items', [PriceListController::class, 'items']);
        Route::post('/{priceList}/items', [PriceListController::class, 'saveItems']);
        Route::delete('/{priceList}/items/{dishId}', [PriceListController::class, 'removeItem']);
    });

    // Модификаторы
    Route::prefix('modifiers')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ModifierController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\ModifierController::class, 'store']);
        Route::get('/{modifier}', [\App\Http\Controllers\Api\ModifierController::class, 'show']);
        Route::put('/{modifier}', [\App\Http\Controllers\Api\ModifierController::class, 'update']);
        Route::delete('/{modifier}', [\App\Http\Controllers\Api\ModifierController::class, 'destroy']);

        Route::post('/{modifier}/options', [\App\Http\Controllers\Api\ModifierController::class, 'storeOption']);
        Route::put('/options/{option}', [\App\Http\Controllers\Api\ModifierController::class, 'updateOption']);
        Route::delete('/options/{option}', [\App\Http\Controllers\Api\ModifierController::class, 'destroyOption']);

        Route::post('/attach-dish', [\App\Http\Controllers\Api\ModifierController::class, 'attachToDish']);
        Route::post('/detach-dish', [\App\Http\Controllers\Api\ModifierController::class, 'detachFromDish']);
    });

    // Лояльность
    Route::prefix('loyalty')->group(function () {
        Route::get('/promotions', [LoyaltyController::class, 'promotions']);
        Route::post('/promotions', [LoyaltyController::class, 'storePromotion']);
        Route::put('/promotions/{promotion}', [LoyaltyController::class, 'updatePromotion']);
        Route::delete('/promotions/{promotion}', [LoyaltyController::class, 'destroyPromotion']);

        Route::get('/promo-codes', [LoyaltyController::class, 'promoCodes']);
        Route::post('/promo-codes', [LoyaltyController::class, 'storePromoCode']);
        Route::put('/promo-codes/{promotion}', [LoyaltyController::class, 'updatePromoCode']);
        Route::delete('/promo-codes/{promotion}', [LoyaltyController::class, 'destroyPromoCode']);

        Route::get('/levels', [LoyaltyController::class, 'levels']);
        Route::post('/levels', [LoyaltyController::class, 'storeLevel']);
        Route::put('/levels/{level}', [LoyaltyController::class, 'updateLevel']);
        Route::delete('/levels/{level}', [LoyaltyController::class, 'destroyLevel']);

        Route::get('/transactions', [LoyaltyController::class, 'bonusHistory']);
        Route::get('/stats', [LoyaltyController::class, 'stats']);

        Route::get('/settings', [LoyaltyController::class, 'settings']);
        Route::put('/settings', [LoyaltyController::class, 'updateSettings']);
    });

    // Финансы
    Route::prefix('finance')->group(function () {
        Route::get('/transactions', [\App\Http\Controllers\Api\FinanceController::class, 'transactions']);
        Route::post('/transactions', [\App\Http\Controllers\Api\FinanceController::class, 'storeTransaction']);
        Route::put('/transactions/{transaction}', [\App\Http\Controllers\Api\FinanceController::class, 'updateTransaction']);
        Route::delete('/transactions/{transaction}', [\App\Http\Controllers\Api\FinanceController::class, 'destroyTransaction']);

        Route::get('/categories', [\App\Http\Controllers\Api\FinanceController::class, 'categories']);
        Route::post('/categories', [\App\Http\Controllers\Api\FinanceController::class, 'storeCategory']);
        Route::put('/categories/{category}', [\App\Http\Controllers\Api\FinanceController::class, 'updateCategory']);
        Route::delete('/categories/{category}', [\App\Http\Controllers\Api\FinanceController::class, 'destroyCategory']);

        Route::get('/stats', [\App\Http\Controllers\Api\FinanceController::class, 'stats']);
        Route::get('/report', [\App\Http\Controllers\Api\FinanceController::class, 'report']);
    });

    // Доставка
    Route::prefix('delivery')->group(function () {
        Route::get('/zones', [\App\Http\Controllers\Api\DeliveryController::class, 'zones']);
        Route::post('/zones', [\App\Http\Controllers\Api\DeliveryController::class, 'createZone']);
        Route::put('/zones/{zone}', [\App\Http\Controllers\Api\DeliveryController::class, 'updateZone']);
        Route::delete('/zones/{zone}', [\App\Http\Controllers\Api\DeliveryController::class, 'deleteZone']);

        Route::get('/couriers', [\App\Http\Controllers\Api\DeliveryController::class, 'couriers']);
        Route::get('/settings', [\App\Http\Controllers\Api\DeliveryController::class, 'settings']);
        Route::put('/settings', [\App\Http\Controllers\Api\DeliveryController::class, 'updateSettings']);

        Route::get('/analytics', [\App\Http\Controllers\Api\DeliveryController::class, 'analytics']);
    });

    // Зарплаты (старый payroll)
    Route::prefix('payroll')->group(function () {
        Route::get('/', [PayrollController::class, 'index']);
        Route::get('/history', [PayrollController::class, 'history']);
        Route::get('/rates', [PayrollController::class, 'rates']);
        Route::post('/rates', [PayrollController::class, 'storeRate']);
        Route::put('/rates/{rate}', [PayrollController::class, 'updateRate']);
        Route::delete('/rates/{rate}', [PayrollController::class, 'destroyRate']);
        Route::post('/calculate', [PayrollController::class, 'calculate']);
        Route::put('/{payroll}', [PayrollController::class, 'update']);
        Route::post('/{payroll}/pay', [PayrollController::class, 'pay']);
    });

    // Зарплаты (новая система)
    Route::prefix('salary')->group(function () {
        Route::get('/periods', [SalaryController::class, 'periods']);
        Route::post('/periods', [SalaryController::class, 'createPeriod']);
        Route::get('/periods/{period}', [SalaryController::class, 'periodDetails']);
        Route::post('/periods/{period}/calculate', [SalaryController::class, 'calculate']);
        Route::post('/periods/{period}/approve', [SalaryController::class, 'approve']);
        Route::post('/periods/{period}/pay-all', [SalaryController::class, 'payAll']);
        Route::get('/periods/{period}/payments', [SalaryController::class, 'periodPayments']);
        Route::get('/periods/{period}/export', [SalaryController::class, 'exportPeriod']);
        Route::post('/periods/{period}/recalculate/{user}', [SalaryController::class, 'recalculateUser']);

        Route::post('/bonus', [SalaryController::class, 'addBonus']);
        Route::post('/penalty', [SalaryController::class, 'addPenalty']);
        Route::post('/advance', [SalaryController::class, 'payAdvance']);

        Route::post('/calculations/{calculation}/pay', [SalaryController::class, 'paySalary']);
        Route::get('/calculations/{calculation}/breakdown', [SalaryController::class, 'calculationBreakdown']);
        Route::patch('/calculations/{calculation}/notes', [SalaryController::class, 'updateCalculationNotes']);

        Route::get('/users/{user}/payments', [SalaryController::class, 'userPayments']);
        Route::post('/payments/{payment}/cancel', [SalaryController::class, 'cancelPayment']);
    });

    // Начисления зарплат (legacy)
    Route::get('/salary-payments', [StaffManagementController::class, 'salaryPayments']);
    Route::post('/salary-payments', [StaffManagementController::class, 'createSalaryPayment']);
    Route::patch('/salary-payments/{payment}', [StaffManagementController::class, 'updateSalaryPayment']);
    Route::delete('/salary-payments/{payment}', [StaffManagementController::class, 'deleteSalaryPayment']);

    // Аналитика
    Route::get('/analytics', [AnalyticsController::class, 'dashboard']);

    // Настройки
    Route::get('/settings', [\App\Http\Controllers\Api\SettingsController::class, 'index']);
    Route::put('/settings', [\App\Http\Controllers\Api\SettingsController::class, 'update']);
    Route::put('/settings/notifications', [\App\Http\Controllers\Api\SettingsController::class, 'updateNotifications']);

    // Настройки Yandex Карт
    Route::get('/settings/yandex', [\App\Http\Controllers\Api\SettingsController::class, 'yandexSettings']);
    Route::put('/settings/yandex', [\App\Http\Controllers\Api\SettingsController::class, 'updateYandexSettings']);
    Route::post('/settings/yandex/test', [\App\Http\Controllers\Api\SettingsController::class, 'testYandexConnection']);
    Route::post('/settings/yandex/geocode', [\App\Http\Controllers\Api\SettingsController::class, 'geocodeRestaurantAddress']);

    // Принтеры
    Route::get('/printers', [PrinterController::class, 'index']);
    Route::get('/printers/system', [PrinterController::class, 'getSystemPrinters']);
    Route::post('/printers', [PrinterController::class, 'store']);
    Route::put('/printers/{printer}', [PrinterController::class, 'update']);
    Route::delete('/printers/{printer}', [PrinterController::class, 'destroy']);
    Route::post('/printers/{printer}/test', [PrinterController::class, 'test']);
    Route::post('/printers/{printer}/test-receipt', [PrinterController::class, 'testReceipt']);

    // Учёт рабочего времени
    Route::prefix('attendance')->middleware('auth:sanctum')->group(function () {
        Route::get('/settings', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'getSettings']);
        Route::put('/settings', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'updateSettings']);
        Route::put('/qr-settings', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'updateQrSettings']);

        Route::get('/devices', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'index']);
        Route::post('/devices', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'store']);
        Route::get('/devices/{id}', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'show']);
        Route::put('/devices/{id}', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'update']);
        Route::delete('/devices/{id}', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'destroy']);
        Route::post('/devices/{id}/regenerate-key', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'regenerateKey']);
        Route::post('/devices/{id}/sync-users', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'syncUsers']);
        Route::post('/devices/{id}/test-connection', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'testConnection']);

        Route::get('/devices/{id}/device-users', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'getDeviceUsers']);
        Route::post('/devices/{id}/device-users', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'addDeviceUser']);
        Route::delete('/devices/{id}/device-users/{deviceUserId}', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'removeDeviceUser']);
        Route::patch('/devices/{id}/device-users/{deviceUserId}', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'updateDeviceUser']);
        Route::post('/devices/{id}/link-user', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'linkDeviceUser']);
        Route::delete('/devices/{id}/unlink-user/{deviceUserId}', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'unlinkDeviceUser']);

        Route::get('/users/{userId}/devices', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'getUserDevices']);
        Route::get('/users/{userId}/biometric-status', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'getUserBiometricStatus']);

        Route::get('/events', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'events']);
        Route::get('/events/{id}', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'showEvent']);
        Route::put('/events/{id}', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'updateEvent']);
        Route::delete('/events/{id}', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'deleteEvent']);
        Route::post('/events', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'createEvent']);

        Route::get('/timesheet', [\App\Http\Controllers\Api\TimesheetController::class, 'index']);
        Route::get('/timesheet/{userId}', [\App\Http\Controllers\Api\TimesheetController::class, 'show']);

        Route::post('/sessions', [\App\Http\Controllers\Api\TimesheetController::class, 'createSession']);
        Route::delete('/sessions/{id}', [\App\Http\Controllers\Api\TimesheetController::class, 'deleteSession']);
        Route::put('/sessions/{id}/close', [\App\Http\Controllers\Api\TimesheetController::class, 'closeSession']);

        Route::post('/day-override', [\App\Http\Controllers\Api\TimesheetController::class, 'setDayOverride']);
        Route::delete('/day-override/{id}', [\App\Http\Controllers\Api\TimesheetController::class, 'deleteDayOverride']);

        Route::get('/schedule', [\App\Http\Controllers\Api\ScheduleController::class, 'index']);
        Route::post('/schedule/shift', [\App\Http\Controllers\Api\ScheduleController::class, 'saveShift']);
        Route::delete('/schedule/shift', [\App\Http\Controllers\Api\ScheduleController::class, 'deleteShift']);
        Route::post('/schedule/bulk', [\App\Http\Controllers\Api\ScheduleController::class, 'bulkSaveShifts']);
        Route::post('/schedule/copy-week', [\App\Http\Controllers\Api\ScheduleController::class, 'copyWeek']);
    });

    // API Клиенты (интеграции)
    Route::prefix('api-clients')->group(function () {
        Route::get('/', [ApiClientController::class, 'index']);
        Route::post('/', [ApiClientController::class, 'store']);
        Route::get('/scopes', [ApiClientController::class, 'scopes']);
        Route::get('/webhook-events', [ApiClientController::class, 'webhookEvents']);
        Route::get('/{apiClient}', [ApiClientController::class, 'show']);
        Route::put('/{apiClient}', [ApiClientController::class, 'update']);
        Route::delete('/{apiClient}', [ApiClientController::class, 'destroy']);
        Route::post('/{apiClient}/regenerate', [ApiClientController::class, 'regenerateCredentials']);
        Route::post('/{apiClient}/toggle-active', [ApiClientController::class, 'toggleActive']);
        Route::get('/{apiClient}/logs', [ApiClientController::class, 'logs']);
        Route::post('/{apiClient}/test-webhook', [ApiClientController::class, 'testWebhook']);
    });
});

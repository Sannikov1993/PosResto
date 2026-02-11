<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\StaffInvitationController;
use App\Http\Controllers\Api\StaffPasswordController;
use App\Http\Controllers\Api\StaffManagementController;
use App\Http\Controllers\Api\Inventory\WarehouseController;
use App\Http\Controllers\Api\Inventory\IngredientController;
use App\Http\Controllers\Api\Inventory\InvoiceController;
use App\Http\Controllers\Api\Inventory\InventoryCheckController;
use App\Http\Controllers\Api\Inventory\StockController;
use App\Http\Controllers\Api\Inventory\SupplierController;
use App\Http\Controllers\Api\Inventory\RecipeController;
use App\Http\Controllers\Api\Inventory\UnitController;
use App\Http\Controllers\Api\Inventory\CategoryController;
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

    // Дашборд — доступен всем авторизованным
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Персонал — требует permission
    Route::middleware('permission:staff.view')->group(function () {
        Route::get('/staff', [StaffController::class, 'index']);
        Route::get('/staff/{user}', [StaffController::class, 'show'])->can('view', 'user');
        Route::get('/invitations', [StaffInvitationController::class, 'index']);
    });
    Route::middleware('permission:staff.create')->group(function () {
        Route::post('/staff', [StaffController::class, 'store']);
        Route::post('/invitations', [StaffManagementController::class, 'createInvitation']);
        Route::post('/invitations/{invitation}/resend', [StaffInvitationController::class, 'resend'])->can('resend', 'invitation');
        Route::post('/staff/{user}/invite', [StaffInvitationController::class, 'sendInvite'])->can('create', \App\Models\User::class);
    });
    Route::middleware('permission:staff.edit')->group(function () {
        Route::put('/staff/{user}', [StaffController::class, 'update'])->can('update', 'user');
        Route::post('/staff/{user}/toggle-active', [StaffController::class, 'toggleActive'])->can('update', 'user');
        Route::post('/staff/{user}/password-reset', [StaffPasswordController::class, 'sendReset'])->can('update', 'user');
        Route::post('/staff/{user}/fire', [StaffManagementController::class, 'fire'])->can('delete', 'user');
        Route::post('/staff/{user}/restore', [StaffManagementController::class, 'restore'])->can('update', 'user');
    });
    Route::middleware('permission:staff.delete')->group(function () {
        Route::delete('/staff/{user}', [StaffController::class, 'destroy'])->can('delete', 'user');
        Route::delete('/invitations/{invitation}', [StaffInvitationController::class, 'cancel'])->can('cancel', 'invitation');
    });

    // Роли — только admin+
    Route::middleware('permission:settings.edit')->group(function () {
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/roles/{role}', [RoleController::class, 'update']);
        Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
    });

    // Расписание персонала
    Route::middleware('permission:staff.view|staff.edit')->group(function () {
        Route::get('/schedule', [StaffScheduleController::class, 'index']);
        Route::get('/schedule/stats', [StaffScheduleController::class, 'weekStats']);
        Route::get('/schedule/templates', [StaffScheduleController::class, 'templates']);
    });
    Route::middleware('permission:staff.edit')->group(function () {
        Route::post('/schedule', [StaffScheduleController::class, 'store']);
        Route::put('/schedule/{schedule}', [StaffScheduleController::class, 'update']);
        Route::delete('/schedule/{schedule}', [StaffScheduleController::class, 'destroy']);
        Route::post('/schedule/publish', [StaffScheduleController::class, 'publishWeek']);
        Route::post('/schedule/copy-week', [StaffScheduleController::class, 'copyWeek']);
        Route::post('/schedule/templates', [StaffScheduleController::class, 'storeTemplate']);
        Route::put('/schedule/templates/{template}', [StaffScheduleController::class, 'updateTemplate']);
        Route::delete('/schedule/templates/{template}', [StaffScheduleController::class, 'destroyTemplate']);
    });

    // Зоны и столы
    Route::middleware('permission:settings.view|settings.edit')->group(function () {
        Route::get('/zones', [TableController::class, 'zones']);
        Route::get('/tables', [TableController::class, 'index']);
    });
    Route::middleware('permission:settings.edit')->group(function () {
        Route::post('/zones', [TableController::class, 'storeZone']);
        Route::put('/zones/{zone}', [TableController::class, 'updateZone']);
        Route::delete('/zones/{zone}', [TableController::class, 'destroyZone']);
        Route::post('/tables', [TableController::class, 'store']);
        Route::put('/tables/{table}', [TableController::class, 'update']);
        Route::delete('/tables/{table}', [TableController::class, 'destroy']);
    });

    // Клиенты
    Route::middleware('permission:orders.view')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index']);
        Route::get('/customers/{customer}', [CustomerController::class, 'show']);
    });
    Route::middleware('permission:orders.create|orders.edit')->group(function () {
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::put('/customers/{customer}', [CustomerController::class, 'update']);
        Route::post('/customers/{customer}/bonus', [CustomerController::class, 'addBonus']);
    });
    Route::middleware('permission:orders.cancel')->group(function () {
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy']);
    });

    // Склад — требует inventory permissions
    Route::prefix('inventory')->middleware('permission:menu.view|menu.edit')->group(function () {
        // Ингредиенты
        Route::get('/ingredients', [IngredientController::class, 'index']);
        Route::post('/ingredients', [IngredientController::class, 'store']);
        Route::get('/ingredients/{ingredient}', [IngredientController::class, 'show']);
        Route::put('/ingredients/{ingredient}', [IngredientController::class, 'update']);
        Route::delete('/ingredients/{ingredient}', [IngredientController::class, 'destroy']);
        Route::get('/ingredients/{ingredient}/packagings', [IngredientController::class, 'packagings']);
        Route::post('/ingredients/{ingredient}/packagings', [IngredientController::class, 'storePackaging']);
        Route::put('/ingredients/{ingredient}/packagings/{packaging}', [IngredientController::class, 'updatePackaging']);
        Route::delete('/ingredients/{ingredient}/packagings/{packaging}', [IngredientController::class, 'destroyPackaging']);
        Route::get('/ingredients/{ingredient}/available-units', [IngredientController::class, 'availableUnits']);

        // Единицы измерения
        Route::get('/units', [UnitController::class, 'index']);
        Route::post('/units', [UnitController::class, 'store']);
        Route::put('/units/{unit}', [UnitController::class, 'update']);
        Route::delete('/units/{unit}', [UnitController::class, 'destroy']);

        // Категории ингредиентов
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

        // Склады (types ПЕРЕД {warehouse} чтобы не перехватывалось)
        Route::get('/warehouses/types', [WarehouseController::class, 'types']);
        Route::get('/warehouses', [WarehouseController::class, 'index']);
        Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show']);
        Route::post('/warehouses', [WarehouseController::class, 'store']);
        Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update']);
        Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy']);

        // Поставщики
        Route::get('/suppliers', [SupplierController::class, 'index']);
        Route::post('/suppliers', [SupplierController::class, 'store']);
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update']);
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy']);

        // Движение товаров
        Route::get('/movements', [StockController::class, 'movements']);
        Route::post('/quick-income', [StockController::class, 'quickIncome']);
        Route::post('/quick-write-off', [StockController::class, 'quickWriteOff']);

        // Накладные
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::post('/invoices', [InvoiceController::class, 'store']);
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::post('/invoices/{invoice}/complete', [InvoiceController::class, 'complete']);
        Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);
        Route::post('/invoices/recognize', [InvoiceController::class, 'recognize']);

        // Инвентаризации
        Route::get('/checks', [InventoryCheckController::class, 'index']);
        Route::post('/checks', [InventoryCheckController::class, 'store']);
        Route::get('/checks/{inventoryCheck}', [InventoryCheckController::class, 'show']);
        Route::put('/checks/{inventoryCheck}/items/{item}', [InventoryCheckController::class, 'updateItem']);
        Route::post('/checks/{inventoryCheck}/items', [InventoryCheckController::class, 'addItem']);
        Route::post('/checks/{inventoryCheck}/complete', [InventoryCheckController::class, 'complete']);
        Route::post('/checks/{inventoryCheck}/cancel', [InventoryCheckController::class, 'cancel']);

        // Статистика
        Route::get('/stats', [StockController::class, 'stats']);
        Route::get('/alerts/low-stock', [StockController::class, 'lowStockAlerts']);

        // Конвертация
        Route::post('/convert-units', [StockController::class, 'convertUnits']);
        Route::post('/calculate-brutto-netto', [StockController::class, 'calculateBruttoNetto']);
    });

    // Меню — требует menu permissions
    Route::prefix('menu')->middleware('permission:menu.view|menu.edit')->group(function () {
        Route::get('/categories', [MenuController::class, 'categories']);
        Route::post('/categories', [MenuController::class, 'storeCategory']);
        Route::put('/categories/{category}', [MenuController::class, 'updateCategory']);
        Route::delete('/categories/{category}', [MenuController::class, 'destroyCategory']);

        Route::get('/dishes', [MenuController::class, 'dishes']);
        Route::post('/dishes', [MenuController::class, 'storeDish']);
        Route::get('/dishes/{dish}', [MenuController::class, 'showDish']);
        Route::put('/dishes/{dish}', [MenuController::class, 'updateDish']);
        Route::delete('/dishes/{dish}', [MenuController::class, 'destroyDish']);

        Route::get('/dishes/{dish}/recipe', [RecipeController::class, 'dishRecipe']);
        Route::post('/dishes/{dish}/recipe', [RecipeController::class, 'saveDishRecipe']);

        Route::get('/dishes/{dish}/modifiers', [\App\Http\Controllers\Api\ModifierController::class, 'dishModifiers']);
        Route::post('/dishes/{dish}/modifiers', [\App\Http\Controllers\Api\ModifierController::class, 'saveDishModifiers']);
    });

    // Прайс-листы — требует menu permissions
    Route::prefix('price-lists')->middleware('permission:menu.view|menu.edit')->group(function () {
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

    // Модификаторы — требует menu permissions
    Route::prefix('modifiers')->middleware('permission:menu.view|menu.edit')->group(function () {
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

    // Лояльность — требует loyalty permissions
    Route::prefix('loyalty')->middleware('permission:loyalty.view|loyalty.edit')->group(function () {
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

    // Финансы — требует finance permissions
    Route::prefix('finance')->middleware('permission:finance.view|finance.edit')->group(function () {
        Route::get('/transactions', [\App\Http\Controllers\Api\FinanceTransactionController::class, 'index']);
        Route::post('/transactions', [\App\Http\Controllers\Api\FinanceTransactionController::class, 'store']);
        Route::put('/transactions/{transaction}', [\App\Http\Controllers\Api\FinanceTransactionController::class, 'update']);
        Route::delete('/transactions/{transaction}', [\App\Http\Controllers\Api\FinanceTransactionController::class, 'destroy']);

        Route::get('/categories', [\App\Http\Controllers\Api\FinanceTransactionController::class, 'categories']);
        Route::post('/categories', [\App\Http\Controllers\Api\FinanceTransactionController::class, 'storeCategory']);
        Route::put('/categories/{category}', [\App\Http\Controllers\Api\FinanceTransactionController::class, 'updateCategory']);
        Route::delete('/categories/{category}', [\App\Http\Controllers\Api\FinanceTransactionController::class, 'destroyCategory']);

        Route::get('/stats', [\App\Http\Controllers\Api\FinanceReportController::class, 'stats']);
        Route::get('/report', [\App\Http\Controllers\Api\FinanceReportController::class, 'report']);
    });

    // Доставка — требует orders permissions
    Route::prefix('delivery')->middleware('permission:orders.view|orders.edit')->group(function () {
        Route::get('/zones', [\App\Http\Controllers\Api\DeliveryZoneController::class, 'zones']);
        Route::post('/zones', [\App\Http\Controllers\Api\DeliveryZoneController::class, 'createZone']);
        Route::put('/zones/{zone}', [\App\Http\Controllers\Api\DeliveryZoneController::class, 'updateZone']);
        Route::delete('/zones/{zone}', [\App\Http\Controllers\Api\DeliveryZoneController::class, 'deleteZone']);

        Route::get('/couriers', [\App\Http\Controllers\Api\DeliveryCourierController::class, 'couriers']);
        Route::get('/settings', [\App\Http\Controllers\Api\DeliverySettingsController::class, 'settings']);
        Route::put('/settings', [\App\Http\Controllers\Api\DeliverySettingsController::class, 'updateSettings']);

        Route::get('/analytics', [\App\Http\Controllers\Api\DeliverySettingsController::class, 'analytics']);
    });

    // Зарплаты (старый payroll) — требует finance permissions
    Route::prefix('payroll')->middleware('permission:finance.view|finance.edit')->group(function () {
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

    // Зарплаты (новая система) — требует finance permissions
    Route::prefix('salary')->middleware('permission:finance.view|finance.edit')->group(function () {
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

    // Начисления зарплат (legacy) — требует finance permissions
    Route::middleware('permission:finance.view|finance.edit')->group(function () {
        Route::get('/salary-payments', [StaffManagementController::class, 'salaryPayments']);
        Route::post('/salary-payments', [StaffManagementController::class, 'createSalaryPayment']);
        Route::patch('/salary-payments/{payment}', [StaffManagementController::class, 'updateSalaryPayment']);
        Route::delete('/salary-payments/{payment}', [StaffManagementController::class, 'deleteSalaryPayment']);
    });

    // Аналитика — требует reports permissions
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('/analytics', [AnalyticsController::class, 'dashboard']);
    });

    // Настройки — требует settings permissions
    Route::middleware('permission:settings.view|settings.edit')->group(function () {
        Route::get('/settings', [\App\Http\Controllers\Api\SettingsController::class, 'index']);
        Route::get('/settings/yandex', [\App\Http\Controllers\Api\YandexSettingsController::class, 'yandexSettings']);
    });
    Route::middleware('permission:settings.edit')->group(function () {
        Route::put('/settings', [\App\Http\Controllers\Api\SettingsController::class, 'update']);
        Route::put('/settings/notifications', [\App\Http\Controllers\Api\IntegrationSettingsController::class, 'updateNotifications']);
        Route::put('/settings/yandex', [\App\Http\Controllers\Api\YandexSettingsController::class, 'updateYandexSettings']);
        Route::post('/settings/yandex/test', [\App\Http\Controllers\Api\YandexSettingsController::class, 'testYandexConnection']);
        Route::post('/settings/yandex/geocode', [\App\Http\Controllers\Api\YandexSettingsController::class, 'geocodeRestaurantAddress']);
    });

    // Принтеры — требует settings permissions
    Route::middleware('permission:settings.view|settings.edit')->group(function () {
        Route::get('/printers', [PrinterController::class, 'index']);
        Route::get('/printers/system', [PrinterController::class, 'getSystemPrinters']);
    });
    Route::middleware('permission:settings.edit')->group(function () {
        Route::post('/printers', [PrinterController::class, 'store']);
        Route::put('/printers/{printer}', [PrinterController::class, 'update']);
        Route::delete('/printers/{printer}', [PrinterController::class, 'destroy']);
        Route::post('/printers/{printer}/test', [PrinterController::class, 'test']);
        Route::post('/printers/{printer}/test-receipt', [PrinterController::class, 'testReceipt']);
    });

    // Учёт рабочего времени — требует staff permissions + sanctum
    Route::prefix('attendance')->middleware(['auth:sanctum', 'permission:staff.view|staff.edit'])->group(function () {
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

    // API Клиенты (интеграции) — только admin+
    Route::prefix('api-clients')->middleware('permission:settings.edit')->group(function () {
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

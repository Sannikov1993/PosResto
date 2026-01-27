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
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\WaiterApiController;
use App\Http\Controllers\Api\StaffManagementController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\StopListController;
use App\Http\Controllers\Api\KitchenStationController;
use App\Http\Controllers\Api\KitchenDeviceController;
use App\Http\Controllers\Api\GiftCertificateController;
use App\Http\Controllers\Pos\TableOrderController;
use App\Http\Controllers\Api\StaffNotificationController;
use App\Http\Controllers\Api\TelegramStaffBotController;
use App\Http\Controllers\Api\StaffScheduleController;
use App\Http\Controllers\Api\SalaryController;
use App\Http\Controllers\Api\StaffCabinetController;

/*
|--------------------------------------------------------------------------
| PosResto API Routes v2.0.0 - FINAL
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return response()->json([
        'name' => 'PosResto API',
        'version' => '2.1.0',
        'status' => 'running',
        'features' => [
            'orders', 'menu', 'tables', 'reservations', 'realtime',
            'staff', 'inventory', 'loyalty', 'analytics', 'printing',
            'guest_menu', 'fiscal', 'finance', 'settings'
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
    // Восстановление пароля
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/check-reset-token', [AuthController::class, 'checkResetToken']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// =====================================================
// ЗАКАЗЫ
// =====================================================
Route::prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::post('/', [OrderController::class, 'store']);
    Route::get('/count-by-dates', [OrderController::class, 'countByDates']); // Количество заказов по датам
    Route::get('/write-offs', [OrderController::class, 'writeOffs']); // Отчёт по списаниям
    Route::get('/{order}', [OrderController::class, 'show']);
    Route::put('/{order}', [OrderController::class, 'update']);
    Route::patch('/{order}/status', [OrderController::class, 'updateStatus']);
    Route::post('/{order}/pay', [OrderController::class, 'pay']);
    Route::post('/{order}/cancel-with-writeoff', [OrderController::class, 'cancelWithWriteOff']); // Отмена со списанием
    Route::post('/{order}/transfer', [OrderController::class, 'transfer']); // Перенос на другой стол
    Route::patch('/{order}/delivery-status', [OrderController::class, 'updateDeliveryStatus']);
    Route::post('/{order}/assign-courier', [OrderController::class, 'assignCourier']);
    Route::post('/{order}/items', [OrderController::class, 'addItem']);
    Route::patch('/{order}/items/{item}/status', [OrderController::class, 'updateItemStatus']);
    Route::delete('/{order}/items/{item}', [OrderController::class, 'removeItem']);
    Route::post('/{order}/call-waiter', [OrderController::class, 'callWaiter']);
    Route::post('/{order}/print/receipt', [PrinterController::class, 'printReceipt']);
    Route::post('/{order}/print/precheck', [PrinterController::class, 'printPrecheck']);
    Route::post('/{order}/print/kitchen', [PrinterController::class, 'printToKitchen']);
    Route::get('/{order}/print/data', [PrinterController::class, 'getReceiptData']);
    Route::get('/{order}/preview/precheck', [PrinterController::class, 'previewPrecheck']);
    Route::get('/{order}/preview/receipt', [PrinterController::class, 'previewReceipt']);
});

// =====================================================
// ЗАКАЗЫ ПО СТОЛАМ (несколько заказов на одном столе)
// =====================================================
Route::prefix('tables/{tableId}/orders')->group(function () {
    Route::get('/', [OrderController::class, 'tableOrders']);
    Route::post('/', [OrderController::class, 'createTableOrder']);
});

// =====================================================
// ОТМЕНЫ ПОЗИЦИЙ
// =====================================================
Route::prefix('order-items')->group(function () {
    Route::post('/{item}/cancel', [OrderController::class, 'cancelItem']);
    Route::post('/{item}/request-cancellation', [OrderController::class, 'requestItemCancellation']);
    Route::post('/{item}/approve-cancellation', [OrderController::class, 'approveItemCancellation']);
    Route::post('/{item}/reject-cancellation', [OrderController::class, 'rejectItemCancellation']);
});

Route::prefix('cancellations')->group(function () {
    Route::get('/reasons', [OrderController::class, 'getCancellationReasons']);
    Route::get('/pending', [OrderController::class, 'pendingCancellations']);
    Route::post('/{order}/approve', [OrderController::class, 'approveCancellation']);
    Route::post('/{order}/reject', [OrderController::class, 'rejectCancellation']);
});

// Заявка на отмену заказа
Route::post('/orders/{order}/request-cancellation', [OrderController::class, 'requestCancellation']);

// История списаний (отменённые заказы и позиции) - legacy
Route::get('/write-offs/cancelled-orders', [OrderController::class, 'writeOffs']);

// =====================================================
// СПИСАНИЯ (новая система)
// =====================================================
Route::prefix('write-offs')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\WriteOffController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\WriteOffController::class, 'store']);
    Route::get('/settings', [\App\Http\Controllers\Api\WriteOffController::class, 'settings']);
    Route::post('/verify-manager', [\App\Http\Controllers\Api\WriteOffController::class, 'verifyManager']);
    Route::get('/{writeOff}', [\App\Http\Controllers\Api\WriteOffController::class, 'show']);
});

// =====================================================
// TABLE ORDER - CUSTOMER
// =====================================================
Route::post('/table-order/{order}/customer', [TableOrderController::class, 'attachCustomer']);
Route::delete('/table-order/{order}/customer', [TableOrderController::class, 'detachCustomer']);

// =====================================================
// МЕНЮ
// =====================================================
Route::prefix('menu')->group(function () {
    Route::get('/', [MenuController::class, 'index']);
    Route::post('/', [MenuController::class, 'storeDish']);
    Route::put('/{dish}', [MenuController::class, 'updateDish']);
    Route::delete('/{dish}', [MenuController::class, 'destroyDish']);
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
    Route::get('/business-date', [ReservationController::class, 'businessDate']);
    Route::get('/available-slots', [ReservationController::class, 'availableSlots']);
    Route::get('/{reservation}', [ReservationController::class, 'show']);
    Route::put('/{reservation}', [ReservationController::class, 'update']);
    Route::delete('/{reservation}', [ReservationController::class, 'destroy']);
    Route::post('/{reservation}/confirm', [ReservationController::class, 'confirm']);
    Route::post('/{reservation}/cancel', [ReservationController::class, 'cancel']);
    Route::post('/{reservation}/seat', [ReservationController::class, 'seat']);
    Route::post('/{reservation}/seat-with-order', [ReservationController::class, 'seatWithOrder']);
    Route::post('/{reservation}/unseat', [ReservationController::class, 'unseat']);
    Route::post('/{reservation}/preorder', [ReservationController::class, 'preorder']);
    Route::get('/{reservation}/preorder-items', [ReservationController::class, 'preorderItems']);
    Route::post('/{reservation}/preorder-items', [ReservationController::class, 'addPreorderItem']);
    Route::patch('/{reservation}/preorder-items/{itemId}', [ReservationController::class, 'updatePreorderItem']);
    Route::delete('/{reservation}/preorder-items/{itemId}', [ReservationController::class, 'removePreorderItem']);
    Route::post('/{reservation}/complete', [ReservationController::class, 'complete']);
    Route::post('/{reservation}/no-show', [ReservationController::class, 'noShow']);
    Route::post('/{reservation}/prepayment', [ReservationController::class, 'prepayment']);
    // Депозит
    Route::post('/{reservation}/deposit/pay', [ReservationController::class, 'payDeposit']);
    Route::post('/{reservation}/deposit/refund', [ReservationController::class, 'refundDeposit']);
    // Печать предзаказа на кухню
    Route::post('/{reservation}/print-preorder', [ReservationController::class, 'printPreorder']);
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
    // Статические маршруты (должны идти ПЕРЕД параметризованными /{user})
    Route::get('/', [StaffController::class, 'index']);
    Route::post('/', [StaffController::class, 'store']);
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
    Route::get('/roles', [StaffController::class, 'roles']);
    Route::get('/roles/{role}/permissions', [StaffController::class, 'rolePermissions']);
    Route::post('/generate-pin', [StaffController::class, 'generatePin']);
    Route::post('/verify-pin', [StaffController::class, 'verifyPin']);

    // Приглашения (статические, перед /{user})
    Route::get('/invitations', [StaffManagementController::class, 'invitations']);
    Route::post('/invitations', [StaffManagementController::class, 'createInvitation']);
    Route::delete('/invitations/{invitation}', [StaffManagementController::class, 'cancelInvitation']);
    Route::post('/invitations/{invitation}/resend', [StaffManagementController::class, 'resendInvitation']);

    // Справочники (статические, перед /{user})
    Route::get('/salary-types', [StaffManagementController::class, 'salaryTypes']);
    Route::get('/available-roles', [StaffManagementController::class, 'availableRoles']);

    // Зарплатные начисления (статические, перед /{user})
    Route::get('/salary-payments', [StaffManagementController::class, 'salaryPayments']);
    Route::post('/salary-payments', [StaffManagementController::class, 'createSalaryPayment']);
    Route::patch('/salary-payments/{payment}', [StaffManagementController::class, 'updateSalaryPayment']);
    Route::delete('/salary-payments/{payment}', [StaffManagementController::class, 'deleteSalaryPayment']);

    // Маршруты с параметром {user} (должны идти ПОСЛЕ статических)
    Route::get('/{user}', [StaffController::class, 'show']);
    Route::put('/{user}', [StaffController::class, 'update']);
    Route::delete('/{user}', [StaffController::class, 'destroy']);
    Route::get('/{user}/report', [StaffController::class, 'userReport']);
    Route::post('/{user}/change-pin', [StaffController::class, 'changePin']);
    Route::post('/{user}/change-password', [StaffController::class, 'changePassword']);
    Route::post('/{user}/toggle-active', [StaffController::class, 'toggleActive']);
    Route::patch('/{user}/salary', [StaffManagementController::class, 'update']);
    Route::patch('/{user}/pin', [StaffManagementController::class, 'updatePin']);
    Route::delete('/{user}/pin', [StaffManagementController::class, 'deletePin']);
    Route::patch('/{user}/password', [StaffManagementController::class, 'updatePassword']);
    Route::post('/{user}/fire', [StaffManagementController::class, 'fire']);
    Route::post('/{user}/restore', [StaffManagementController::class, 'restore']);
    Route::post('/{user}/invite', [StaffManagementController::class, 'sendUserInvite']);
});

// Публичный маршрут для принятия приглашения
Route::get('/invite/{token}', [StaffManagementController::class, 'getInvitation']);
Route::post('/invite/{token}/accept', [StaffManagementController::class, 'acceptInvitation']);

// =====================================================
// ЗАРПЛАТЫ И ТАБЕЛЬ (PAYROLL)
// =====================================================
Route::prefix('payroll')->group(function () {
    // Табель (work sessions)
    Route::get('/timesheet', [PayrollController::class, 'timesheet']);
    Route::post('/clock-in', [PayrollController::class, 'clockIn']);
    Route::post('/clock-out', [PayrollController::class, 'clockOut']);
    Route::get('/clock-status', [PayrollController::class, 'clockStatus']);
    Route::get('/who-is-working', [PayrollController::class, 'whoIsWorking']);

    // Личные методы для авторизованного сотрудника
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/my-status', [PayrollController::class, 'myClockStatus']);
        Route::post('/my-clock-in', [PayrollController::class, 'myClockIn']);
        Route::post('/my-clock-out', [PayrollController::class, 'myClockOut']);
    });
    Route::post('/sessions', [PayrollController::class, 'storeSession']);
    Route::patch('/sessions/{session}', [PayrollController::class, 'correctSession']);
    Route::delete('/sessions/{session}', [PayrollController::class, 'deleteSession']);

    // Расчётные периоды
    Route::get('/periods', [PayrollController::class, 'periods']);
    Route::post('/periods', [PayrollController::class, 'createPeriod']);
    Route::get('/periods/{period}', [PayrollController::class, 'showPeriod']);
    Route::post('/periods/{period}/calculate', [PayrollController::class, 'calculatePeriod']);
    Route::post('/periods/{period}/approve', [PayrollController::class, 'approvePeriod']);
    Route::post('/periods/{period}/pay', [PayrollController::class, 'payPeriod']);

    // Выплаты
    Route::get('/payments', [PayrollController::class, 'payments']);
    Route::post('/payments', [PayrollController::class, 'createPayment']);
    Route::post('/payments/{payment}/cancel', [PayrollController::class, 'cancelPayment']);

    // Сводка по сотруднику
    Route::get('/users/{user}/summary', [PayrollController::class, 'userSummary']);
});

// =====================================================
// РАСПИСАНИЕ СМЕН (Staff Schedule)
// =====================================================
Route::prefix('schedule')->group(function () {
    // Расписание на неделю
    Route::get('/', [StaffScheduleController::class, 'index']);
    Route::get('/stats', [StaffScheduleController::class, 'weekStats']);

    // Управление сменами
    Route::post('/', [StaffScheduleController::class, 'store']);
    Route::put('/{schedule}', [StaffScheduleController::class, 'update']);
    Route::delete('/{schedule}', [StaffScheduleController::class, 'destroy']);

    // Публикация и копирование
    Route::post('/publish', [StaffScheduleController::class, 'publishWeek']);
    Route::post('/copy-week', [StaffScheduleController::class, 'copyWeek']);

    // Шаблоны смен
    Route::get('/templates', [StaffScheduleController::class, 'templates']);
    Route::post('/templates', [StaffScheduleController::class, 'storeTemplate']);
    Route::put('/templates/{template}', [StaffScheduleController::class, 'updateTemplate']);
    Route::delete('/templates/{template}', [StaffScheduleController::class, 'destroyTemplate']);

    // Моё расписание (для сотрудников)
    Route::middleware('auth:sanctum')->get('/my', [StaffScheduleController::class, 'mySchedule']);
});

// =====================================================
// ЗАРПЛАТЫ (Salary Calculation)
// =====================================================
Route::prefix('salary')->group(function () {
    // Расчётные периоды
    Route::get('/periods', [SalaryController::class, 'periods']);
    Route::post('/periods', [SalaryController::class, 'createPeriod']);
    Route::get('/periods/{period}', [SalaryController::class, 'periodDetails']);
    Route::post('/periods/{period}/calculate', [SalaryController::class, 'calculate']);
    Route::post('/periods/{period}/approve', [SalaryController::class, 'approve']);
    Route::post('/periods/{period}/pay-all', [SalaryController::class, 'payAll']);
    Route::get('/periods/{period}/payments', [SalaryController::class, 'periodPayments']);
    Route::get('/periods/{period}/export', [SalaryController::class, 'exportPeriod']);

    // Пересчёт для сотрудника
    Route::post('/periods/{period}/recalculate/{user}', [SalaryController::class, 'recalculateUser']);

    // Начисления (премии/штрафы/авансы)
    Route::post('/bonus', [SalaryController::class, 'addBonus']);
    Route::post('/penalty', [SalaryController::class, 'addPenalty']);
    Route::post('/advance', [SalaryController::class, 'payAdvance']);

    // Выплата зарплаты
    Route::post('/calculations/{calculation}/pay', [SalaryController::class, 'paySalary']);
    Route::get('/calculations/{calculation}/breakdown', [SalaryController::class, 'calculationBreakdown']);
    Route::patch('/calculations/{calculation}/notes', [SalaryController::class, 'updateCalculationNotes']);

    // История платежей
    Route::get('/users/{user}/payments', [SalaryController::class, 'userPayments']);
    Route::post('/payments/{payment}/cancel', [SalaryController::class, 'cancelPayment']);

    // Моя зарплата (для сотрудников)
    Route::middleware('auth:sanctum')->get('/my', [SalaryController::class, 'mySalary']);
});

// =====================================================
// ЛИЧНЫЙ КАБИНЕТ СОТРУДНИКА (Staff Cabinet)
// =====================================================
Route::prefix('cabinet')->middleware('auth:sanctum')->group(function () {
    // Главная (дашборд)
    Route::get('/dashboard', [StaffCabinetController::class, 'dashboard']);

    // Мои смены (расписание)
    Route::get('/schedule', [StaffCabinetController::class, 'mySchedule']);

    // Табель (clock in/out)
    Route::get('/timesheet', [StaffCabinetController::class, 'myTimesheet']);
    Route::post('/clock-in', [StaffCabinetController::class, 'clockIn']);
    Route::post('/clock-out', [StaffCabinetController::class, 'clockOut']);

    // Зарплата
    Route::get('/salary', [StaffCabinetController::class, 'mySalary']);
    Route::get('/salary/{calculation}', [StaffCabinetController::class, 'salaryDetails']);

    // Статистика (для официантов)
    Route::get('/stats', [StaffCabinetController::class, 'myStats']);

    // Профиль
    Route::get('/profile', [StaffCabinetController::class, 'myProfile']);
    Route::patch('/profile', [StaffCabinetController::class, 'updateProfile']);
    Route::post('/profile/pin', [StaffCabinetController::class, 'changePin']);
    Route::post('/profile/password', [StaffCabinetController::class, 'changePassword']);
    Route::patch('/profile/notifications', [StaffCabinetController::class, 'updateNotificationSettings']);

    // Уведомления
    Route::get('/notifications', [StaffCabinetController::class, 'myNotifications']);
    Route::post('/notifications/{notification}/read', [StaffCabinetController::class, 'markNotificationRead']);
    Route::post('/notifications/read-all', [StaffCabinetController::class, 'markAllNotificationsRead']);

    // Push-уведомления
    Route::get('/push/vapid-key', [StaffCabinetController::class, 'getVapidPublicKey']);
    Route::post('/push/subscribe', [StaffCabinetController::class, 'subscribePush']);
    Route::delete('/push/unsubscribe', [StaffCabinetController::class, 'unsubscribePush']);
    Route::get('/push/subscriptions', [StaffCabinetController::class, 'myPushSubscriptions']);
    Route::post('/push/test', [StaffCabinetController::class, 'testPushNotification']);

    // Биометрия (WebAuthn)
    Route::get('/biometric/credentials', [StaffCabinetController::class, 'biometricCredentials']);
    Route::get('/biometric/register-options', [StaffCabinetController::class, 'biometricRegisterOptions']);
    Route::post('/biometric/register', [StaffCabinetController::class, 'biometricRegister']);
    Route::get('/biometric/auth-options', [StaffCabinetController::class, 'biometricAuthOptions']);
    Route::post('/biometric/verify', [StaffCabinetController::class, 'biometricVerify']);
    Route::delete('/biometric/{credentialId}', [StaffCabinetController::class, 'biometricDelete']);
    Route::post('/biometric/toggle-requirement', [StaffCabinetController::class, 'biometricToggleRequirement']);

    // Clock in/out с биометрией
    Route::post('/clock-in-biometric', [StaffCabinetController::class, 'clockInWithBiometric']);
    Route::post('/clock-out-biometric', [StaffCabinetController::class, 'clockOutWithBiometric']);
});

// =====================================================
// РОЛИ И РАЗРЕШЕНИЯ
// =====================================================
Route::prefix('roles')->group(function () {
    Route::get('/', [RoleController::class, 'index']);
    Route::get('/permissions', [RoleController::class, 'permissions']);
    Route::post('/', [RoleController::class, 'store']);
    Route::post('/reorder', [RoleController::class, 'reorder']);
    Route::get('/{role}', [RoleController::class, 'show']);
    Route::put('/{role}', [RoleController::class, 'update']);
    Route::delete('/{role}', [RoleController::class, 'destroy']);
    Route::post('/{role}/toggle-active', [RoleController::class, 'toggleActive']);
    Route::post('/{role}/clone', [RoleController::class, 'clone']);
});

// =====================================================
// ЗОНЫ (алиас для удобства)
// =====================================================
Route::prefix('zones')->group(function () {
    Route::get('/', [TableController::class, 'zones']);
    Route::post('/', [TableController::class, 'storeZone']);
    Route::put('/{zone}', [TableController::class, 'updateZone']);
    Route::delete('/{zone}', [TableController::class, 'destroyZone']);
});

// =====================================================
// РЕСТОРАНЫ
// =====================================================
Route::prefix('restaurants')->group(function () {
    Route::get('/{restaurant}', [DashboardController::class, 'getRestaurant']);
    Route::put('/{restaurant}', [DashboardController::class, 'updateRestaurant']);
});

// =====================================================
// СКЛАД
// =====================================================
Route::prefix('inventory')->group(function () {
    // Склады
    Route::get('/warehouses', [InventoryController::class, 'warehouses']);
    Route::post('/warehouses', [InventoryController::class, 'storeWarehouse']);
    Route::put('/warehouses/{warehouse}', [InventoryController::class, 'updateWarehouse']);
    Route::delete('/warehouses/{warehouse}', [InventoryController::class, 'destroyWarehouse']);
    Route::get('/warehouse-types', [InventoryController::class, 'warehouseTypes']);

    // Ингредиенты
    Route::get('/ingredients', [InventoryController::class, 'ingredients']);
    Route::post('/ingredients', [InventoryController::class, 'storeIngredient']);
    Route::get('/ingredients/{ingredient}', [InventoryController::class, 'showIngredient']);
    Route::put('/ingredients/{ingredient}', [InventoryController::class, 'updateIngredient']);
    Route::delete('/ingredients/{ingredient}', [InventoryController::class, 'destroyIngredient']);

    // Фасовки ингредиентов
    Route::get('/ingredients/{ingredient}/packagings', [InventoryController::class, 'ingredientPackagings']);
    Route::post('/ingredients/{ingredient}/packagings', [InventoryController::class, 'storePackaging']);
    Route::put('/packagings/{packaging}', [InventoryController::class, 'updatePackaging']);
    Route::delete('/packagings/{packaging}', [InventoryController::class, 'destroyPackaging']);

    // Конвертация единиц измерения
    Route::post('/convert-units', [InventoryController::class, 'convertUnits']);
    Route::post('/calculate-brutto-netto', [InventoryController::class, 'calculateBruttoNetto']);
    Route::get('/ingredients/{ingredient}/available-units', [InventoryController::class, 'availableUnits']);
    Route::get('/ingredients/{ingredient}/suggest-parameters', [InventoryController::class, 'suggestParameters']);

    // Категории ингредиентов
    Route::get('/categories', [InventoryController::class, 'categories']);
    Route::post('/categories', [InventoryController::class, 'storeCategory']);
    Route::put('/categories/{category}', [InventoryController::class, 'updateCategory']);
    Route::delete('/categories/{category}', [InventoryController::class, 'destroyCategory']);

    // Единицы измерения
    Route::get('/units', [InventoryController::class, 'units']);
    Route::post('/units', [InventoryController::class, 'storeUnit']);
    Route::put('/units/{unit}', [InventoryController::class, 'updateUnit']);
    Route::delete('/units/{unit}', [InventoryController::class, 'destroyUnit']);

    // Накладные
    Route::get('/invoices', [InventoryController::class, 'invoices']);
    Route::post('/invoices', [InventoryController::class, 'storeInvoice']);
    Route::get('/invoices/{invoice}', [InventoryController::class, 'showInvoice']);
    Route::post('/invoices/{invoice}/complete', [InventoryController::class, 'completeInvoice']);
    Route::post('/invoices/{invoice}/cancel', [InventoryController::class, 'cancelInvoice']);

    // Быстрые операции
    Route::post('/quick-income', [InventoryController::class, 'quickIncome']);
    Route::post('/quick-write-off', [InventoryController::class, 'quickWriteOff']);

    // Движение товаров
    Route::get('/movements', [InventoryController::class, 'movements']);
    Route::post('/stock/income', [InventoryController::class, 'stockIncome']);
    Route::post('/stock/write-off', [InventoryController::class, 'stockWriteOff']);

    // Рецепты блюд (техкарты)
    Route::get('/dishes/{dish}/recipe', [InventoryController::class, 'dishRecipe']);
    Route::post('/dishes/{dish}/recipe', [InventoryController::class, 'saveDishRecipe']);

    // Поставщики
    Route::get('/suppliers', [InventoryController::class, 'suppliers']);
    Route::post('/suppliers', [InventoryController::class, 'storeSupplier']);
    Route::put('/suppliers/{supplier}', [InventoryController::class, 'updateSupplier']);
    Route::delete('/suppliers/{supplier}', [InventoryController::class, 'destroySupplier']);

    // Инвентаризации
    Route::get('/checks', [InventoryController::class, 'inventoryChecks']);
    Route::post('/checks', [InventoryController::class, 'createInventoryCheck']);
    Route::get('/checks/{inventoryCheck}', [InventoryController::class, 'showInventoryCheck']);
    Route::put('/checks/{inventoryCheck}/items/{item}', [InventoryController::class, 'updateInventoryCheckItem']);
    Route::post('/checks/{inventoryCheck}/complete', [InventoryController::class, 'completeInventoryCheck']);
    Route::post('/checks/{inventoryCheck}/items', [InventoryController::class, 'addInventoryCheckItem']);
    Route::post('/checks/{inventoryCheck}/cancel', [InventoryController::class, 'cancelInventoryCheck']);

    // Статистика и алерты
    Route::get('/stats', [InventoryController::class, 'stats']);
    Route::get('/alerts/low-stock', [InventoryController::class, 'lowStockAlerts']);

    // Интеграция с POS (проверка доступности и списание)
    Route::post('/check-availability', [InventoryController::class, 'checkDishAvailability']);
    Route::post('/deduct-for-order/{order}', [InventoryController::class, 'deductForOrder']);

    // Распознавание накладных по фото (Yandex Vision OCR)
    Route::post('/invoices/recognize', [InventoryController::class, 'recognizeInvoice']);
    Route::get('/vision/check', [InventoryController::class, 'checkVisionConfig']);
});

// =====================================================
// ПРОГРАММА ЛОЯЛЬНОСТИ
// =====================================================
Route::prefix('loyalty')->group(function () {
    // Уровни лояльности
    Route::get('/levels', [LoyaltyController::class, 'levels']);
    Route::post('/levels', [LoyaltyController::class, 'storeLevel']);
    Route::put('/levels/{level}', [LoyaltyController::class, 'updateLevel']);
    Route::delete('/levels/{level}', [LoyaltyController::class, 'destroyLevel']);
    Route::post('/levels/recalculate', [LoyaltyController::class, 'recalculateLevels']);

    // Промокоды (теперь это Promotion с кодом)
    Route::get('/promo-codes', [LoyaltyController::class, 'promoCodes']);
    Route::post('/promo-codes', [LoyaltyController::class, 'storePromoCode']);
    Route::put('/promo-codes/{promotion}', [LoyaltyController::class, 'updatePromoCode']);
    Route::delete('/promo-codes/{promotion}', [LoyaltyController::class, 'destroyPromoCode']);
    Route::post('/promo-codes/validate', [LoyaltyController::class, 'validatePromoCode']);
    Route::post('/validate-promo', [LoyaltyController::class, 'validatePromoCode']); // alias
    Route::post('/promo-codes/generate', [LoyaltyController::class, 'generatePromoCode']);
    Route::get('/promo-codes/available', [LoyaltyController::class, 'availablePromoCodes']);

    // Акции
    Route::get('/promotions', [LoyaltyController::class, 'promotions']);
    Route::get('/promotions/active', [LoyaltyController::class, 'activePromotions']);
    Route::get('/promotions/{promotion}', [LoyaltyController::class, 'showPromotion']);
    Route::post('/promotions', [LoyaltyController::class, 'storePromotion']);
    Route::put('/promotions/{promotion}', [LoyaltyController::class, 'updatePromotion']);
    Route::delete('/promotions/{promotion}', [LoyaltyController::class, 'destroyPromotion']);
    Route::post('/promotions/{promotion}/toggle', [LoyaltyController::class, 'togglePromotion']);

    // Бонусы
    Route::get('/bonus-history', [LoyaltyController::class, 'bonusHistory']);
    Route::get('/transactions', [LoyaltyController::class, 'bonusHistory']); // alias
    Route::post('/bonus/earn', [LoyaltyController::class, 'earnBonus']);
    Route::post('/bonus/spend', [LoyaltyController::class, 'spendBonus']);

    // Настройки бонусной программы
    Route::get('/bonus-settings', [LoyaltyController::class, 'bonusSettings']);
    Route::put('/bonus-settings', [LoyaltyController::class, 'updateBonusSettings']);

    // Расчёт и настройки
    Route::post('/calculate', [LoyaltyController::class, 'calculateDiscount']);
    Route::post('/calculate-discount', [LoyaltyController::class, 'calculateDiscount']); // alias for POS
    Route::get('/settings', [LoyaltyController::class, 'settings']);
    Route::put('/settings', [LoyaltyController::class, 'updateSettings']);
    Route::get('/stats', [LoyaltyController::class, 'stats']);
    Route::post('/recalculate-level', [LoyaltyController::class, 'recalculateCustomerLevel']);
});

// =====================================================
// ПОДАРОЧНЫЕ СЕРТИФИКАТЫ
// =====================================================
Route::prefix('gift-certificates')->group(function () {
    Route::get('/', [GiftCertificateController::class, 'index']);
    Route::post('/', [GiftCertificateController::class, 'store']);
    Route::get('/stats', [GiftCertificateController::class, 'stats']);
    Route::post('/check', [GiftCertificateController::class, 'check']); // Проверка по коду
    Route::get('/{giftCertificate}', [GiftCertificateController::class, 'show']);
    Route::put('/{giftCertificate}', [GiftCertificateController::class, 'update']);
    Route::post('/{giftCertificate}/use', [GiftCertificateController::class, 'use']); // Использовать
    Route::post('/{giftCertificate}/activate', [GiftCertificateController::class, 'activate']);
    Route::post('/{giftCertificate}/cancel', [GiftCertificateController::class, 'cancel']);
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

    // RFM-анализ
    Route::get('/rfm', [AnalyticsController::class, 'rfmAnalysis']);
    Route::get('/rfm/segments', [AnalyticsController::class, 'rfmSegments']);
    Route::get('/rfm/descriptions', [AnalyticsController::class, 'rfmSegmentDescriptions']);
    Route::get('/export/rfm', [AnalyticsController::class, 'exportRfm']);
    
    // Анализ оттока
    Route::get('/churn', [AnalyticsController::class, 'churnAnalysis']);
    Route::get('/churn/alerts', [AnalyticsController::class, 'churnAlerts']);
    Route::get('/churn/trend', [AnalyticsController::class, 'churnTrend']);
    Route::get('/export/churn', [AnalyticsController::class, 'exportChurn']);
    
    // Улучшенный прогноз
    Route::get('/forecast/enhanced', [AnalyticsController::class, 'enhancedForecast']);
    Route::get('/forecast/categories', [AnalyticsController::class, 'forecastByCategory']);
    Route::get('/forecast/ingredients', [AnalyticsController::class, 'forecastIngredients']);
    Route::get('/forecast/staff', [AnalyticsController::class, 'forecastStaff']);
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
    Route::get('/{customer}/rfm', [AnalyticsController::class, 'customerRfm']);
    Route::post('/{customer}/blacklist', [CustomerController::class, 'blacklist']);
    Route::post('/{customer}/unblacklist', [CustomerController::class, 'unblacklist']);
    Route::get('/{customer}/addresses', [CustomerController::class, 'addresses']);
    Route::post('/{customer}/addresses', [CustomerController::class, 'addAddress']);
    Route::get('/{customer}/orders', [CustomerController::class, 'orders']);
    Route::get('/{customer}/all-orders', [CustomerController::class, 'allOrders']);
    Route::get('/{customer}/bonus-history', [CustomerController::class, 'bonusHistory']);
    Route::post('/{customer}/save-delivery-address', [CustomerController::class, 'saveDeliveryAddress']);
    Route::delete('/{customer}/addresses/{address}', [CustomerController::class, 'deleteAddress']);
    Route::post('/{customer}/addresses/{address}/set-default', [CustomerController::class, 'setDefaultAddress']);
    Route::post('/{customer}/toggle-blacklist', [CustomerController::class, 'toggleBlacklist']);
});

// =====================================================
// ДАШБОРД
// =====================================================
Route::prefix('dashboard')->group(function () {
    Route::get('/', [DashboardController::class, 'index']);
    Route::get('/stats', [DashboardController::class, 'stats']);
    Route::get('/stats/brief', [DashboardController::class, 'briefStats']);
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

// =====================================================
// ФИСКАЛИЗАЦИЯ (ККТ)
// =====================================================
Route::prefix('fiscal')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\FiscalController::class, 'index']);
    Route::get('/status', [\App\Http\Controllers\Api\FiscalController::class, 'status']);
    Route::get('/{receipt}', [\App\Http\Controllers\Api\FiscalController::class, 'show']);
    Route::post('/{receipt}/check', [\App\Http\Controllers\Api\FiscalController::class, 'checkStatus']);
    Route::post('/{receipt}/retry', [\App\Http\Controllers\Api\FiscalController::class, 'retry']);
    Route::post('/orders/{order}/refund', [\App\Http\Controllers\Api\FiscalController::class, 'refund']);
    Route::post('/callback', [\App\Http\Controllers\Api\FiscalController::class, 'callback']);
});

// =====================================================
// ФИНАНСЫ (Кассовые смены и операции)
// =====================================================
Route::prefix('finance')->group(function () {
    // Кассовые смены
    Route::get('/shifts', [\App\Http\Controllers\Api\FinanceController::class, 'shifts']);
    Route::get('/shifts/current', [\App\Http\Controllers\Api\FinanceController::class, 'currentShift']);
    Route::get('/shifts/last-balance', [\App\Http\Controllers\Api\FinanceController::class, 'lastClosedShiftBalance']);
    Route::post('/shifts/open', [\App\Http\Controllers\Api\FinanceController::class, 'openShift']);
    Route::post('/shifts/{shift}/close', [\App\Http\Controllers\Api\FinanceController::class, 'closeShift']);
    Route::get('/shifts/{shift}', [\App\Http\Controllers\Api\FinanceController::class, 'showShift']);
    Route::get('/shifts/{shift}/orders', [\App\Http\Controllers\Api\FinanceController::class, 'shiftOrders']);
    Route::get('/shifts/{shift}/prepayments', [\App\Http\Controllers\Api\FinanceController::class, 'shiftPrepayments']);
    Route::get('/shifts/{shift}/z-report', [\App\Http\Controllers\Api\FinanceController::class, 'zReport']);
    Route::get('/x-report', [\App\Http\Controllers\Api\FinanceController::class, 'xReport']);

    // Кассовые операции
    Route::get('/operations', [\App\Http\Controllers\Api\FinanceController::class, 'operations']);
    Route::post('/operations/deposit', [\App\Http\Controllers\Api\FinanceController::class, 'deposit']);
    Route::post('/operations/withdrawal', [\App\Http\Controllers\Api\FinanceController::class, 'withdrawal']);
    Route::post('/operations/order-prepayment', [\App\Http\Controllers\Api\FinanceController::class, 'orderPrepayment']);
    Route::post('/operations/refund', [\App\Http\Controllers\Api\FinanceController::class, 'refund']);

    // Аналитика
    Route::get('/summary/daily', [\App\Http\Controllers\Api\FinanceController::class, 'dailySummary']);
    Route::get('/summary/period', [\App\Http\Controllers\Api\FinanceController::class, 'periodSummary']);
    Route::get('/top-dishes', [\App\Http\Controllers\Api\FinanceController::class, 'topDishes']);
    Route::get('/payment-methods', [\App\Http\Controllers\Api\FinanceController::class, 'paymentMethodsSummary']);
});

// =====================================================
// НАСТРОЙКИ
// =====================================================
Route::prefix('settings')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\SettingsController::class, 'index']);

    // Общие настройки (для всех модулей)
    Route::get('/general', [\App\Http\Controllers\Api\SettingsController::class, 'generalSettings']);

    // Роли
    Route::get('/roles', [\App\Http\Controllers\Api\SettingsController::class, 'roles']);
    Route::get('/staff-roles', [\App\Http\Controllers\Api\SettingsController::class, 'staffWithRoles']);
    Route::patch('/staff/{user}/role', [\App\Http\Controllers\Api\SettingsController::class, 'updateStaffRole']);

    // Интеграции
    Route::get('/integrations', [\App\Http\Controllers\Api\SettingsController::class, 'integrations']);
    Route::post('/integrations/check', [\App\Http\Controllers\Api\SettingsController::class, 'checkIntegration']);

    // Уведомления
    Route::get('/notifications', [\App\Http\Controllers\Api\SettingsController::class, 'notifications']);
    Route::put('/notifications', [\App\Http\Controllers\Api\SettingsController::class, 'updateNotifications']);

    // Печать
    Route::get('/print', [\App\Http\Controllers\Api\SettingsController::class, 'printSettings']);
    Route::put('/print', [\App\Http\Controllers\Api\SettingsController::class, 'updatePrintSettings']);

    // POS-терминал
    Route::get('/pos', [\App\Http\Controllers\Api\SettingsController::class, 'posSettings']);
    Route::post('/pos', [\App\Http\Controllers\Api\SettingsController::class, 'updatePosSettings']);

    // Ручные скидки
    Route::get('/manual-discounts', [\App\Http\Controllers\Api\SettingsController::class, 'manualDiscountSettings']);
    Route::put('/manual-discounts', [\App\Http\Controllers\Api\SettingsController::class, 'updateManualDiscountSettings']);
});

// =====================================================
// АЛИАСЫ ДЛЯ POS ИНТЕРФЕЙСА
// =====================================================
Route::get('/categories', [MenuController::class, 'categories']);
Route::get('/dishes', [MenuController::class, 'dishes']);
Route::post('/dishes', [MenuController::class, 'storeDish']);
Route::put('/dishes/{dish}', [MenuController::class, 'updateDish']);
Route::delete('/dishes/{dish}', [MenuController::class, 'destroyDish']);

// Смены (алиас для /finance/shifts)
Route::prefix('shifts')->group(function () {
    Route::get('/current', [\App\Http\Controllers\Api\FinanceController::class, 'currentShift']);
    Route::post('/open', [\App\Http\Controllers\Api\FinanceController::class, 'openShift']);
    Route::post('/{shift}/close', [\App\Http\Controllers\Api\FinanceController::class, 'closeShift']);
});

// =====================================================
// ДОСТАВКА (расширенный модуль)
// =====================================================
Route::prefix('delivery')->group(function () {
    // Расчёт стоимости доставки (алиас detectZone)
    Route::post('/calculate', [\App\Http\Controllers\Api\DeliveryController::class, 'detectZone']);

    // Заказы доставки
    Route::get('/orders', [\App\Http\Controllers\Api\DeliveryController::class, 'orders']);
    Route::post('/orders', [\App\Http\Controllers\Api\DeliveryController::class, 'createOrder']);
    Route::get('/orders/{order}', [\App\Http\Controllers\Api\DeliveryController::class, 'showOrder']);
    Route::patch('/orders/{order}/status', [\App\Http\Controllers\Api\DeliveryController::class, 'updateStatus']);
    Route::post('/orders/{order}/assign-courier', [\App\Http\Controllers\Api\DeliveryController::class, 'assignCourier']);

    // Курьеры
    Route::get('/couriers', [\App\Http\Controllers\Api\DeliveryController::class, 'couriers']);
    Route::patch('/couriers/{user}/status', [\App\Http\Controllers\Api\DeliveryController::class, 'updateCourierStatus']);

    // Зоны доставки
    Route::get('/zones', [\App\Http\Controllers\Api\DeliveryController::class, 'zones']);
    Route::post('/zones', [\App\Http\Controllers\Api\DeliveryController::class, 'createZone']);
    Route::put('/zones/{zone}', [\App\Http\Controllers\Api\DeliveryController::class, 'updateZone']);
    Route::delete('/zones/{zone}', [\App\Http\Controllers\Api\DeliveryController::class, 'deleteZone']);

    // Геокодирование (Yandex)
    Route::post('/detect-zone', [\App\Http\Controllers\Api\DeliveryController::class, 'detectZone']);
    Route::get('/suggest-address', [\App\Http\Controllers\Api\DeliveryController::class, 'suggestAddress']);
    Route::post('/geocode', [\App\Http\Controllers\Api\DeliveryController::class, 'geocode']);

    // Умное назначение курьера
    Route::get('/orders/{order}/suggest-courier', [\App\Http\Controllers\Api\DeliveryController::class, 'suggestCourier']);
    Route::get('/orders/{order}/ranked-couriers', [\App\Http\Controllers\Api\DeliveryController::class, 'rankedCouriers']);
    Route::post('/orders/{order}/auto-assign', [\App\Http\Controllers\Api\DeliveryController::class, 'autoAssignCourier']);

    // Настройки
    Route::get('/settings', [\App\Http\Controllers\Api\DeliveryController::class, 'settings']);
    Route::put('/settings', [\App\Http\Controllers\Api\DeliveryController::class, 'updateSettings']);

    // Аналитика
    Route::get('/analytics', [\App\Http\Controllers\Api\DeliveryController::class, 'analytics']);

    // Карта курьеров
    Route::get('/map-data', [\App\Http\Controllers\Api\DeliveryController::class, 'mapData']);

    // Проблемы доставки
    Route::get('/problems', [\App\Http\Controllers\Api\DeliveryController::class, 'problems']);
    Route::post('/orders/{order}/problem', [\App\Http\Controllers\Api\DeliveryController::class, 'createProblem']);
    Route::patch('/problems/{problem}/resolve', [\App\Http\Controllers\Api\DeliveryController::class, 'resolveProblem']);
    Route::delete('/problems/{problem}', [\App\Http\Controllers\Api\DeliveryController::class, 'cancelProblem']);
});

// =====================================================
// УВЕДОМЛЕНИЯ (Telegram + Web Push)
// =====================================================
Route::prefix('notifications')->group(function () {
    // Web Push
    Route::get('/vapid-key', [\App\Http\Controllers\Api\NotificationController::class, 'getVapidKey']);
    Route::post('/push/subscribe', [\App\Http\Controllers\Api\NotificationController::class, 'subscribePush']);
    Route::post('/push/unsubscribe', [\App\Http\Controllers\Api\NotificationController::class, 'unsubscribePush']);

    // Telegram
    Route::get('/telegram/bot', [\App\Http\Controllers\Api\NotificationController::class, 'getTelegramBot']);
    Route::get('/telegram/subscribe-link', [\App\Http\Controllers\Api\NotificationController::class, 'getTelegramSubscribeLink']);
    Route::post('/telegram/set-webhook', [\App\Http\Controllers\Api\NotificationController::class, 'setTelegramWebhook']);

    // Тестирование
    Route::post('/test', [\App\Http\Controllers\Api\NotificationController::class, 'sendTestNotification']);
});

// Telegram Webhook (отдельный маршрут)
Route::post('/telegram/webhook', [\App\Http\Controllers\Api\NotificationController::class, 'telegramWebhook']);

// =====================================================
// УВЕДОМЛЕНИЯ СОТРУДНИКОВ (Staff Notifications)
// =====================================================
Route::prefix('staff-notifications')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        // Список уведомлений
        Route::get('/', [StaffNotificationController::class, 'index']);
        Route::get('/unread-count', [StaffNotificationController::class, 'unreadCount']);
        Route::post('/{notification}/read', [StaffNotificationController::class, 'markAsRead']);
        Route::post('/read-all', [StaffNotificationController::class, 'markAllAsRead']);
        Route::delete('/{notification}', [StaffNotificationController::class, 'destroy']);

        // Настройки уведомлений
        Route::get('/settings', [StaffNotificationController::class, 'getSettings']);
        Route::put('/settings', [StaffNotificationController::class, 'updateSettings']);

        // Telegram
        Route::get('/telegram-link', [StaffNotificationController::class, 'getTelegramLink']);
        Route::post('/disconnect-telegram', [StaffNotificationController::class, 'disconnectTelegram']);

        // Push-токен
        Route::post('/push-token', [StaffNotificationController::class, 'savePushToken']);

        // Отправка (для менеджеров/админов)
        Route::post('/send-test', [StaffNotificationController::class, 'sendTest']);
        Route::post('/send-to-user', [StaffNotificationController::class, 'sendToUser']);
        Route::post('/send-to-all', [StaffNotificationController::class, 'sendToAll']);
    });
});

// Telegram Staff Bot Webhook (публичный, вызывается Telegram)
Route::post('/telegram/staff-bot/webhook', [TelegramStaffBotController::class, 'webhook']);
Route::post('/telegram/staff-bot/set-webhook', [TelegramStaffBotController::class, 'setWebhook']);
Route::get('/telegram/staff-bot/webhook-info', [TelegramStaffBotController::class, 'getWebhookInfo']);

// =====================================================
// PWA ОФИЦИАНТА
// =====================================================
Route::prefix('waiter')->group(function () {
    // Столы и зоны
    Route::get('/tables', [WaiterApiController::class, 'tables']);
    Route::get('/table/{id}', [WaiterApiController::class, 'table']);

    // Меню
    Route::get('/menu/categories', [WaiterApiController::class, 'menuCategories']);
    Route::get('/menu/category/{id}/products', [WaiterApiController::class, 'categoryProducts']);

    // Управление заказом
    Route::post('/order/add-item', [WaiterApiController::class, 'addOrderItem']);
    Route::patch('/order/item/{id}', [WaiterApiController::class, 'updateOrderItem']);
    Route::delete('/order/item/{id}', [WaiterApiController::class, 'deleteOrderItem']);
    Route::post('/order/{id}/send-kitchen', [WaiterApiController::class, 'sendToKitchen']);
    Route::post('/order/{id}/serve', [WaiterApiController::class, 'serveOrder']);
    Route::post('/order/{id}/pay', [WaiterApiController::class, 'payOrder']);

    // Список заказов
    Route::get('/orders', [WaiterApiController::class, 'orders']);

    // Статистика профиля
    Route::get('/profile/stats', [WaiterApiController::class, 'profileStats']);
});

// =====================================================
// ЦЕХА КУХНИ (Kitchen Stations)
// =====================================================
Route::prefix('kitchen-stations')->group(function () {
    Route::get('/', [KitchenStationController::class, 'index']);
    Route::get('/active', [KitchenStationController::class, 'active']);
    Route::post('/', [KitchenStationController::class, 'store']);
    Route::post('/reorder', [KitchenStationController::class, 'reorder']);
    Route::get('/{kitchenStation}', [KitchenStationController::class, 'show']);
    Route::put('/{kitchenStation}', [KitchenStationController::class, 'update']);
    Route::delete('/{kitchenStation}', [KitchenStationController::class, 'destroy']);
    Route::patch('/{kitchenStation}/toggle', [KitchenStationController::class, 'toggle']);
});

// =====================================================
// БАР (Bar Panel for POS)
// =====================================================
Route::prefix('bar')->group(function () {
    Route::get('/check', [KitchenStationController::class, 'getBar']);       // Проверить есть ли бар
    Route::get('/orders', [KitchenStationController::class, 'getBarOrders']); // Получить позиции бара
    Route::post('/item-status', [KitchenStationController::class, 'updateBarItemStatus']); // Обновить статус позиции
});

// =====================================================
// УСТРОЙСТВА КУХНИ (Kitchen Devices)
// =====================================================
Route::prefix('kitchen-devices')->group(function () {
    // Для планшетов
    Route::post('/register', [KitchenDeviceController::class, 'register']);
    Route::get('/my-station', [KitchenDeviceController::class, 'myStation']);
    Route::post('/change-station', [KitchenDeviceController::class, 'changeStation']);

    // Для админки
    Route::get('/', [KitchenDeviceController::class, 'index']);
    Route::put('/{kitchenDevice}', [KitchenDeviceController::class, 'update']);
    Route::delete('/{kitchenDevice}', [KitchenDeviceController::class, 'destroy']);
});

// =====================================================
// СТОП-ЛИСТ
// =====================================================
Route::prefix('stop-list')->group(function () {
    Route::get('/', [StopListController::class, 'index']);
    Route::post('/', [StopListController::class, 'store']);
    Route::put('/{dish}', [StopListController::class, 'update']);
    Route::delete('/{dish}', [StopListController::class, 'destroy']);
    Route::get('/dish-ids', [StopListController::class, 'dishIds']);
    Route::get('/search-dishes', [StopListController::class, 'searchDishes']);
});

// =====================================================
// LIVE-ТРЕКИНГ КУРЬЕРА
// =====================================================
Route::prefix('tracking')->group(function () {
    // Публичные эндпоинты (по токену, без авторизации)
    Route::get('/{token}/data', [\App\Http\Controllers\Api\LiveTrackingController::class, 'getTrackingData']);
    Route::get('/{token}/stream', [\App\Http\Controllers\Api\LiveTrackingController::class, 'stream']);
    Route::get('/{token}/poll', [\App\Http\Controllers\Api\LiveTrackingController::class, 'poll']);
});

// Обновление позиции курьера (требует авторизации)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/courier/location', [\App\Http\Controllers\Api\LiveTrackingController::class, 'updateCourierLocation']);
});

// =====================================================
// BACKOFFICE API - Единый префикс для бэк-офиса
// =====================================================
Route::prefix('backoffice')->group(function () {

    // Авторизация
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/me', [AuthController::class, 'check']);

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
    Route::post('/staff/{user}/fire', [StaffManagementController::class, 'fire']);
    Route::post('/staff/{user}/restore', [StaffManagementController::class, 'restore']);

    // Приглашения персонала
    Route::get('/invitations', [StaffController::class, 'invitations']);
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
        // Категории
        Route::get('/categories', [\App\Http\Controllers\Api\MenuController::class, 'categories']);
        Route::post('/categories', [\App\Http\Controllers\Api\MenuController::class, 'storeCategory']);
        Route::put('/categories/{category}', [\App\Http\Controllers\Api\MenuController::class, 'updateCategory']);
        Route::delete('/categories/{category}', [\App\Http\Controllers\Api\MenuController::class, 'destroyCategory']);

        // Блюда
        Route::get('/dishes', [\App\Http\Controllers\Api\MenuController::class, 'dishes']);
        Route::post('/dishes', [\App\Http\Controllers\Api\MenuController::class, 'storeDish']);
        Route::get('/dishes/{dish}', [\App\Http\Controllers\Api\MenuController::class, 'showDish']);
        Route::put('/dishes/{dish}', [\App\Http\Controllers\Api\MenuController::class, 'updateDish']);
        Route::delete('/dishes/{dish}', [\App\Http\Controllers\Api\MenuController::class, 'destroyDish']);

        // Рецепты блюд
        Route::get('/dishes/{dish}/recipe', [\App\Http\Controllers\Api\InventoryController::class, 'dishRecipe']);
        Route::post('/dishes/{dish}/recipe', [\App\Http\Controllers\Api\InventoryController::class, 'saveDishRecipe']);

        // Модификаторы блюда
        Route::get('/dishes/{dish}/modifiers', [\App\Http\Controllers\Api\ModifierController::class, 'dishModifiers']);
        Route::post('/dishes/{dish}/modifiers', [\App\Http\Controllers\Api\ModifierController::class, 'saveDishModifiers']);
    });

    // Модификаторы (глобальные шаблоны)
    Route::prefix('modifiers')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ModifierController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\ModifierController::class, 'store']);
        Route::get('/{modifier}', [\App\Http\Controllers\Api\ModifierController::class, 'show']);
        Route::put('/{modifier}', [\App\Http\Controllers\Api\ModifierController::class, 'update']);
        Route::delete('/{modifier}', [\App\Http\Controllers\Api\ModifierController::class, 'destroy']);

        // Опции модификатора
        Route::post('/{modifier}/options', [\App\Http\Controllers\Api\ModifierController::class, 'storeOption']);
        Route::put('/options/{option}', [\App\Http\Controllers\Api\ModifierController::class, 'updateOption']);
        Route::delete('/options/{option}', [\App\Http\Controllers\Api\ModifierController::class, 'destroyOption']);

        // Привязка к блюдам
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

    // Зарплаты (новая система расчёта)
    Route::prefix('salary')->group(function () {
        // Расчётные периоды
        Route::get('/periods', [SalaryController::class, 'periods']);
        Route::post('/periods', [SalaryController::class, 'createPeriod']);
        Route::get('/periods/{period}', [SalaryController::class, 'periodDetails']);
        Route::post('/periods/{period}/calculate', [SalaryController::class, 'calculate']);
        Route::post('/periods/{period}/approve', [SalaryController::class, 'approve']);
        Route::post('/periods/{period}/pay-all', [SalaryController::class, 'payAll']);
        Route::get('/periods/{period}/payments', [SalaryController::class, 'periodPayments']);
        Route::get('/periods/{period}/export', [SalaryController::class, 'exportPeriod']);
        Route::post('/periods/{period}/recalculate/{user}', [SalaryController::class, 'recalculateUser']);

        // Начисления
        Route::post('/bonus', [SalaryController::class, 'addBonus']);
        Route::post('/penalty', [SalaryController::class, 'addPenalty']);
        Route::post('/advance', [SalaryController::class, 'payAdvance']);

        // Выплаты
        Route::post('/calculations/{calculation}/pay', [SalaryController::class, 'paySalary']);
        Route::get('/calculations/{calculation}/breakdown', [SalaryController::class, 'calculationBreakdown']);
        Route::patch('/calculations/{calculation}/notes', [SalaryController::class, 'updateCalculationNotes']);

        // История
        Route::get('/users/{user}/payments', [SalaryController::class, 'userPayments']);
        Route::post('/payments/{payment}/cancel', [SalaryController::class, 'cancelPayment']);
    });

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

    // Учёт рабочего времени (устройства, настройки)
    Route::prefix('attendance')->middleware('auth.api_token')->group(function () {
        // Настройки
        Route::get('/settings', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'getSettings']);
        Route::put('/settings', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'updateSettings']);
        Route::put('/qr-settings', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'updateQrSettings']);

        // Устройства
        Route::get('/devices', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'index']);
        Route::post('/devices', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'store']);
        Route::get('/devices/{id}', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'show']);
        Route::put('/devices/{id}', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'update']);
        Route::delete('/devices/{id}', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'destroy']);
        Route::post('/devices/{id}/regenerate-key', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'regenerateKey']);
        Route::post('/devices/{id}/sync-users', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'syncUsers']);
        Route::post('/devices/{id}/test-connection', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'testConnection']);

        // Синхронизация пользователей устройства
        Route::get('/devices/{id}/device-users', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'getDeviceUsers']);
        Route::post('/devices/{id}/device-users', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'addDeviceUser']);
        Route::delete('/devices/{id}/device-users/{deviceUserId}', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'removeDeviceUser']);
        Route::patch('/devices/{id}/device-users/{deviceUserId}', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'updateDeviceUser']);
        Route::post('/devices/{id}/link-user', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'linkDeviceUser']);
        Route::delete('/devices/{id}/unlink-user/{deviceUserId}', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'unlinkDeviceUser']);

        // Доступ пользователя к устройствам (для карточки сотрудника)
        Route::get('/users/{userId}/devices', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'getUserDevices']);
        Route::get('/users/{userId}/biometric-status', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'getUserBiometricStatus']);

        // События
        Route::get('/events', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'events']);
        Route::get('/events/{id}', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'showEvent']);
        Route::put('/events/{id}', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'updateEvent']);
        Route::delete('/events/{id}', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'deleteEvent']);
        Route::post('/events', [\App\Http\Controllers\Api\AttendanceDeviceController::class, 'createEvent']);

        // Табель (Timesheet)
        Route::get('/timesheet', [\App\Http\Controllers\Api\TimesheetController::class, 'index']);
        Route::get('/timesheet/{userId}', [\App\Http\Controllers\Api\TimesheetController::class, 'show']);

        // Ручное создание/удаление смен
        Route::post('/sessions', [\App\Http\Controllers\Api\TimesheetController::class, 'createSession']);
        Route::delete('/sessions/{id}', [\App\Http\Controllers\Api\TimesheetController::class, 'deleteSession']);
        Route::put('/sessions/{id}/close', [\App\Http\Controllers\Api\TimesheetController::class, 'closeSession']);

        // Типы дней (overrides)
        Route::post('/day-override', [\App\Http\Controllers\Api\TimesheetController::class, 'setDayOverride']);
        Route::delete('/day-override/{id}', [\App\Http\Controllers\Api\TimesheetController::class, 'deleteDayOverride']);

        // График (Schedule)
        Route::get('/schedule', [\App\Http\Controllers\Api\ScheduleController::class, 'index']);
        Route::post('/schedule/shift', [\App\Http\Controllers\Api\ScheduleController::class, 'saveShift']);
        Route::delete('/schedule/shift', [\App\Http\Controllers\Api\ScheduleController::class, 'deleteShift']);
        Route::post('/schedule/bulk', [\App\Http\Controllers\Api\ScheduleController::class, 'bulkSaveShifts']);
        Route::post('/schedule/copy-week', [\App\Http\Controllers\Api\ScheduleController::class, 'copyWeek']);
    });
});

// =====================================================
// УЧЁТ РАБОЧЕГО ВРЕМЕНИ (Attendance - публичные webhook)
// =====================================================

// Webhook для устройств биометрии (авторизация по API-ключу, rate limiting)
Route::middleware('throttle:100,1')->group(function () {
    Route::post('/attendance/webhook/{type}', [\App\Http\Controllers\Api\AttendanceWebhookController::class, 'handle']);
    Route::post('/attendance/heartbeat', [\App\Http\Controllers\Api\AttendanceWebhookController::class, 'heartbeat']);
});

// QR-код для отображения в ресторане (rate limiting для защиты от DoS)
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/attendance/qr/{restaurantId}', [\App\Http\Controllers\Api\AttendanceController::class, 'getQrCode']);
    Route::post('/attendance/qr/{restaurantId}/refresh', [\App\Http\Controllers\Api\AttendanceController::class, 'refreshQrCode']);
});

// Эндпоинты для личного кабинета сотрудника (с авторизацией)
Route::prefix('cabinet/attendance')->middleware('auth:sanctum')->group(function () {
    Route::get('/status', [\App\Http\Controllers\Api\AttendanceController::class, 'status']);
    Route::post('/qr/clock-in', [\App\Http\Controllers\Api\AttendanceController::class, 'clockInQr']);
    Route::post('/qr/clock-out', [\App\Http\Controllers\Api\AttendanceController::class, 'clockOutQr']);
    Route::post('/qr/validate', [\App\Http\Controllers\Api\AttendanceController::class, 'validateQr']);
    Route::get('/history', [\App\Http\Controllers\Api\AttendanceController::class, 'history']);
});

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\StaffShiftController;
use App\Http\Controllers\Api\StaffTimeTrackingController;
use App\Http\Controllers\Api\StaffTipController;
use App\Http\Controllers\Api\StaffStatsController;
use App\Http\Controllers\Api\StaffPinController;
use App\Http\Controllers\Api\StaffPasswordController;
use App\Http\Controllers\Api\StaffInvitationController;
use App\Http\Controllers\Api\StaffManagementController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\StaffScheduleController;
use App\Http\Controllers\Api\SalaryController;
use App\Http\Controllers\Api\StaffCabinetController;
use App\Http\Controllers\Api\RoleController;

// =====================================================
// ПЕРСОНАЛ
// =====================================================
Route::prefix('staff')->middleware('auth.api_token')->group(function () {
    // Чтение — staff.view
    Route::middleware('permission:staff.view')->group(function () {
        Route::get('/', [StaffController::class, 'index']);
        Route::get('/schedule', [StaffShiftController::class, 'weekSchedule']);
        Route::get('/shifts', [StaffShiftController::class, 'index']);
        Route::get('/time-entries', [StaffTimeTrackingController::class, 'index']);
        Route::get('/working-now', [StaffTimeTrackingController::class, 'whoIsWorking']);
        Route::get('/tips', [StaffTipController::class, 'index']);
        Route::get('/stats', [StaffStatsController::class, 'index']);
        Route::get('/roles', [RoleController::class, 'index']);
        Route::get('/roles/{role}/permissions', [RoleController::class, 'show']);
        Route::get('/invitations', [StaffInvitationController::class, 'index']);
        Route::get('/salary-types', [StaffManagementController::class, 'salaryTypes']);
        Route::get('/available-roles', [StaffManagementController::class, 'availableRoles']);
        Route::get('/salary-payments', [StaffManagementController::class, 'salaryPayments']);
        Route::get('/{user}', [StaffController::class, 'show'])->can('view', 'user');
        Route::get('/{user}/report', [StaffStatsController::class, 'userReport'])->can('view', 'user');
    });
    // Создание — staff.create
    Route::middleware('permission:staff.create')->group(function () {
        Route::post('/', [StaffController::class, 'store']);
        Route::post('/invitations', [StaffManagementController::class, 'createInvitation']);
        Route::post('/{user}/invite', [StaffInvitationController::class, 'sendInvite'])->can('create', \App\Models\User::class);
    });
    // Редактирование — staff.edit
    Route::middleware('permission:staff.edit')->group(function () {
        Route::post('/shifts', [StaffShiftController::class, 'store']);
        Route::put('/shifts/{shift}', [StaffShiftController::class, 'update']);
        Route::delete('/shifts/{shift}', [StaffShiftController::class, 'destroy']);
        Route::post('/clock-in', [StaffTimeTrackingController::class, 'clockIn']);
        Route::post('/clock-out', [StaffTimeTrackingController::class, 'clockOut']);
        Route::post('/tips', [StaffTipController::class, 'store']);
        Route::post('/generate-pin', [StaffPinController::class, 'generate']);
        Route::post('/verify-pin', [StaffPinController::class, 'verify']);
        Route::post('/invitations/{invitation}/resend', [StaffInvitationController::class, 'resend'])->can('resend', 'invitation');
        Route::post('/salary-payments', [StaffManagementController::class, 'createSalaryPayment']);
        Route::patch('/salary-payments/{payment}', [StaffManagementController::class, 'updateSalaryPayment']);
        Route::delete('/salary-payments/{payment}', [StaffManagementController::class, 'deleteSalaryPayment']);
        Route::put('/{user}', [StaffController::class, 'update'])->can('update', 'user');
        Route::post('/{user}/change-pin', [StaffPinController::class, 'update'])->can('update', 'user');
        Route::post('/{user}/change-password', [StaffPasswordController::class, 'update'])->can('update', 'user');
        Route::post('/{user}/toggle-active', [StaffController::class, 'toggleActive'])->can('update', 'user');
        Route::patch('/{user}/salary', [StaffManagementController::class, 'update'])->can('update', 'user');
        Route::patch('/{user}/pin', [StaffManagementController::class, 'updatePin'])->can('update', 'user');
        Route::delete('/{user}/pin', [StaffManagementController::class, 'deletePin'])->can('update', 'user');
        Route::patch('/{user}/password', [StaffManagementController::class, 'updatePassword'])->can('update', 'user');
        Route::post('/{user}/restore', [StaffManagementController::class, 'restore'])->can('update', 'user');
    });
    // Удаление — staff.delete
    Route::middleware('permission:staff.delete')->group(function () {
        Route::delete('/{user}', [StaffController::class, 'destroy'])->can('delete', 'user');
        Route::delete('/invitations/{invitation}', [StaffInvitationController::class, 'cancel'])->can('cancel', 'invitation');
        Route::post('/{user}/fire', [StaffManagementController::class, 'fire'])->can('delete', 'user');
    });
});

// Публичный маршрут для принятия приглашения
Route::get('/invite/{token}', [StaffManagementController::class, 'getInvitation']);
Route::post('/invite/{token}/accept', [StaffManagementController::class, 'acceptInvitation']);

// =====================================================
// ЗАРПЛАТЫ И ТАБЕЛЬ (PAYROLL)
// =====================================================
Route::prefix('payroll')->middleware('auth:sanctum')->group(function () {
    // Табель (work sessions)
    Route::get('/timesheet', [PayrollController::class, 'timesheet']);
    Route::post('/clock-in', [PayrollController::class, 'clockIn']);
    Route::post('/clock-out', [PayrollController::class, 'clockOut']);
    Route::get('/clock-status', [PayrollController::class, 'clockStatus']);
    Route::get('/who-is-working', [PayrollController::class, 'whoIsWorking']);

    // Личные методы для авторизованного сотрудника
    Route::get('/my-status', [PayrollController::class, 'myClockStatus']);
    Route::post('/my-clock-in', [PayrollController::class, 'myClockIn']);
    Route::post('/my-clock-out', [PayrollController::class, 'myClockOut']);

    // Сессии
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
Route::prefix('schedule')->middleware('auth:sanctum')->group(function () {
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
    Route::get('/my', [StaffScheduleController::class, 'mySchedule']);
});

// =====================================================
// ЗАРПЛАТЫ (Salary Calculation)
// =====================================================
Route::prefix('salary')->middleware('auth:sanctum')->group(function () {
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
    Route::get('/my', [SalaryController::class, 'mySalary']);
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
Route::prefix('roles')->middleware('auth.api_token')->group(function () {
    // Чтение — без ограничений (нужно для отображения ролей в UI)
    Route::get('/', [RoleController::class, 'index']);
    Route::get('/permissions', [RoleController::class, 'permissions']);
    Route::get('/{role}', [RoleController::class, 'show']);
    // Запись — settings.roles
    Route::middleware('permission:settings.roles')->group(function () {
        Route::post('/', [RoleController::class, 'store']);
        Route::post('/reorder', [RoleController::class, 'reorder']);
        Route::put('/{role}', [RoleController::class, 'update']);
        Route::delete('/{role}', [RoleController::class, 'destroy']);
        Route::post('/{role}/toggle-active', [RoleController::class, 'toggleActive']);
        Route::post('/{role}/clone', [RoleController::class, 'clone']);
    });
});

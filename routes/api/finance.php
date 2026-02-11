<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AbcAnalyticsController;
use App\Http\Controllers\Api\RfmAnalyticsController;
use App\Http\Controllers\Api\ChurnAnalyticsController;
use App\Http\Controllers\Api\SalesForecastController;
use App\Http\Controllers\Api\ReportExportController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PrinterController;

// =====================================================
// АНАЛИТИКА
// =====================================================
Route::prefix('analytics')->middleware(['auth.api_token', 'permission:reports.view|reports.analytics'])->group(function () {
    Route::get('/dashboard', [AnalyticsController::class, 'dashboard']);
    Route::get('/abc', [AbcAnalyticsController::class, 'abcAnalysis']);
    Route::get('/forecast', [SalesForecastController::class, 'salesForecast']);
    Route::get('/comparison', [AnalyticsController::class, 'periodComparison']);
    Route::get('/waiters', [AnalyticsController::class, 'waiterReport']);
    Route::get('/hourly', [AnalyticsController::class, 'hourlyAnalysis']);
    Route::get('/categories', [AnalyticsController::class, 'categoryAnalysis']);
    Route::get('/export/sales', [ReportExportController::class, 'exportSales']);
    Route::get('/export/abc', [ReportExportController::class, 'exportAbc']);

    // RFM-анализ
    Route::get('/rfm', [RfmAnalyticsController::class, 'rfmAnalysis']);
    Route::get('/rfm/segments', [RfmAnalyticsController::class, 'rfmSegments']);
    Route::get('/rfm/descriptions', [RfmAnalyticsController::class, 'rfmSegmentDescriptions']);
    Route::get('/export/rfm', [ReportExportController::class, 'exportRfm']);

    // Анализ оттока
    Route::get('/churn', [ChurnAnalyticsController::class, 'churnAnalysis']);
    Route::get('/churn/alerts', [ChurnAnalyticsController::class, 'churnAlerts']);
    Route::get('/churn/trend', [ChurnAnalyticsController::class, 'churnTrend']);
    Route::get('/export/churn', [ReportExportController::class, 'exportChurn']);

    // Улучшенный прогноз
    Route::get('/forecast/enhanced', [SalesForecastController::class, 'enhancedForecast']);
    Route::get('/forecast/categories', [SalesForecastController::class, 'forecastByCategory']);
    Route::get('/forecast/ingredients', [SalesForecastController::class, 'forecastIngredients']);
    Route::get('/forecast/staff', [SalesForecastController::class, 'forecastStaff']);
});

// =====================================================
// ПРИНТЕРЫ
// =====================================================
Route::prefix('printers')->middleware('auth.api_token')->group(function () {
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
// ДАШБОРД
// =====================================================
Route::prefix('dashboard')->middleware('auth.api_token')->group(function () {
    Route::get('/', [DashboardController::class, 'index']);
    Route::get('/stats', [DashboardController::class, 'stats']);
    Route::get('/stats/brief', [DashboardController::class, 'briefStats']);
    Route::get('/sales', [DashboardController::class, 'sales']);
    Route::get('/popular-dishes', [DashboardController::class, 'popularDishes']);
});

// =====================================================
// ОТЧЁТЫ
// =====================================================
Route::prefix('reports')->middleware(['auth.api_token', 'permission:reports.view'])->group(function () {
    Route::get('/sales', [DashboardController::class, 'salesReport']);
    Route::get('/dishes', [DashboardController::class, 'dishesReport']);
    Route::get('/hourly', [DashboardController::class, 'hourlyReport']);
});

// =====================================================
// ФИСКАЛИЗАЦИЯ (ККТ)
// =====================================================
// Защищённые эндпоинты (требуется авторизация)
Route::prefix('fiscal')->middleware('auth.api_token')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\FiscalController::class, 'index']);
    Route::get('/status', [\App\Http\Controllers\Api\FiscalController::class, 'status']);
    Route::get('/{receipt}', [\App\Http\Controllers\Api\FiscalController::class, 'show']);
    Route::post('/{receipt}/check', [\App\Http\Controllers\Api\FiscalController::class, 'checkStatus']);
    Route::post('/{receipt}/retry', [\App\Http\Controllers\Api\FiscalController::class, 'retry']);
    Route::post('/orders/{order}/refund', [\App\Http\Controllers\Api\FiscalController::class, 'refund']);
});

// Webhook от ОФД (публичный, с IP allowlist + token verification)
Route::post('/fiscal/callback', [\App\Http\Controllers\Api\FiscalController::class, 'callback'])
    ->middleware('throttle:30,1');

// =====================================================
// ФИНАНСЫ (Кассовые смены и операции)
// =====================================================
Route::prefix('finance')->middleware('auth.api_token')->group(function () {
    // Чтение — finance.view
    Route::middleware('permission:finance.view')->group(function () {
        Route::get('/shifts', [\App\Http\Controllers\Api\FinanceController::class, 'shifts']);
        Route::get('/shifts/current', [\App\Http\Controllers\Api\FinanceController::class, 'currentShift']);
        Route::get('/shifts/last-balance', [\App\Http\Controllers\Api\FinanceController::class, 'lastClosedShiftBalance']);
        Route::get('/shifts/{shift}', [\App\Http\Controllers\Api\FinanceController::class, 'showShift']);
        Route::get('/shifts/{shift}/orders', [\App\Http\Controllers\Api\FinanceController::class, 'shiftOrders']);
        Route::get('/shifts/{shift}/prepayments', [\App\Http\Controllers\Api\FinanceController::class, 'shiftPrepayments']);
        Route::get('/x-report', [\App\Http\Controllers\Api\FinanceController::class, 'xReport']);
        Route::get('/operations', [\App\Http\Controllers\Api\FinanceController::class, 'operations']);
        Route::get('/summary/daily', [\App\Http\Controllers\Api\FinanceReportController::class, 'dailySummary']);
        Route::get('/summary/period', [\App\Http\Controllers\Api\FinanceReportController::class, 'periodSummary']);
        Route::get('/top-dishes', [\App\Http\Controllers\Api\FinanceReportController::class, 'topDishes']);
        Route::get('/payment-methods', [\App\Http\Controllers\Api\FinanceReportController::class, 'paymentMethodsSummary']);
    });
    // Кассовые смены — finance.shifts
    Route::middleware('permission:finance.shifts')->group(function () {
        Route::post('/shifts/open', [\App\Http\Controllers\Api\FinanceController::class, 'openShift']);
        Route::post('/shifts/{shift}/close', [\App\Http\Controllers\Api\FinanceController::class, 'closeShift']);
        Route::get('/shifts/{shift}/z-report', [\App\Http\Controllers\Api\FinanceController::class, 'zReport']);
    });
    // Кассовые операции — finance.operations
    Route::middleware('permission:finance.operations')->group(function () {
        Route::post('/operations/deposit', [\App\Http\Controllers\Api\FinanceController::class, 'deposit']);
        Route::post('/operations/withdrawal', [\App\Http\Controllers\Api\FinanceController::class, 'withdrawal']);
        Route::post('/operations/order-prepayment', [\App\Http\Controllers\Api\FinanceController::class, 'orderPrepayment']);
        Route::post('/operations/refund', [\App\Http\Controllers\Api\FinanceController::class, 'refund']);
    });
});

// =====================================================
// НАСТРОЙКИ
// =====================================================
Route::prefix('settings')->middleware('auth.api_token')->group(function () {
    // Чтение — settings.view
    Route::middleware('permission:settings.view')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\SettingsController::class, 'index']);
        Route::get('/general', [\App\Http\Controllers\Api\SettingsController::class, 'generalSettings']);
        Route::get('/roles', [\App\Http\Controllers\Api\SettingsController::class, 'roles']);
        Route::get('/staff-roles', [\App\Http\Controllers\Api\SettingsController::class, 'staffWithRoles']);
        Route::get('/integrations', [\App\Http\Controllers\Api\IntegrationSettingsController::class, 'integrations']);
        Route::get('/notifications', [\App\Http\Controllers\Api\IntegrationSettingsController::class, 'notifications']);
        Route::get('/print', [\App\Http\Controllers\Api\PrintSettingsController::class, 'printSettings']);
        Route::get('/pos', [\App\Http\Controllers\Api\PosSettingsController::class, 'posSettings']);
        Route::get('/manual-discounts', [\App\Http\Controllers\Api\DiscountSettingsController::class, 'manualDiscountSettings']);
    });
    // Редактирование — settings.edit
    Route::middleware('permission:settings.edit')->group(function () {
        Route::post('/integrations/check', [\App\Http\Controllers\Api\IntegrationSettingsController::class, 'checkIntegration']);
        Route::put('/notifications', [\App\Http\Controllers\Api\IntegrationSettingsController::class, 'updateNotifications']);
        Route::put('/print', [\App\Http\Controllers\Api\PrintSettingsController::class, 'updatePrintSettings']);
        Route::post('/pos', [\App\Http\Controllers\Api\PosSettingsController::class, 'updatePosSettings']);
        Route::put('/manual-discounts', [\App\Http\Controllers\Api\DiscountSettingsController::class, 'updateManualDiscountSettings']);
    });
    // Роли — settings.roles
    Route::patch('/staff/{user}/role', [\App\Http\Controllers\Api\SettingsController::class, 'updateStaffRole'])->middleware('permission:settings.roles');
});

// =====================================================
// ЮРИДИЧЕСКИЕ ЛИЦА
// =====================================================
Route::prefix('legal-entities')->middleware('auth.api_token')->group(function () {
    Route::get('/dictionaries', [\App\Http\Controllers\Api\LegalEntityController::class, 'dictionaries']);
    Route::get('/', [\App\Http\Controllers\Api\LegalEntityController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\LegalEntityController::class, 'store'])->middleware('permission:settings.edit');
    Route::get('/{legalEntity}', [\App\Http\Controllers\Api\LegalEntityController::class, 'show']);
    Route::put('/{legalEntity}', [\App\Http\Controllers\Api\LegalEntityController::class, 'update'])->middleware('permission:settings.edit');
    Route::delete('/{legalEntity}', [\App\Http\Controllers\Api\LegalEntityController::class, 'destroy'])->middleware('permission:settings.edit');
    Route::post('/{legalEntity}/default', [\App\Http\Controllers\Api\LegalEntityController::class, 'makeDefault'])->middleware('permission:settings.edit');
});

// =====================================================
// КАССОВЫЕ АППАРАТЫ (ККТ)
// =====================================================
Route::prefix('cash-registers')->middleware('auth.api_token')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\CashRegisterController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\CashRegisterController::class, 'store'])->middleware('permission:settings.edit');
    Route::get('/{cashRegister}', [\App\Http\Controllers\Api\CashRegisterController::class, 'show']);
    Route::put('/{cashRegister}', [\App\Http\Controllers\Api\CashRegisterController::class, 'update'])->middleware('permission:settings.edit');
    Route::delete('/{cashRegister}', [\App\Http\Controllers\Api\CashRegisterController::class, 'destroy'])->middleware('permission:settings.edit');
    Route::post('/{cashRegister}/default', [\App\Http\Controllers\Api\CashRegisterController::class, 'makeDefault'])->middleware('permission:settings.edit');
});

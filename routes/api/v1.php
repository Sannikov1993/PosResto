<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API v1 Routes
|--------------------------------------------------------------------------
|
| Enterprise API for external integrations: websites, mobile apps,
| kiosks, aggregators, and third-party services.
|
| Authentication: X-API-Key + X-API-Secret or Bearer Token
| Rate Limiting: Based on client plan (Free/Business/Enterprise)
|
| All routes require authentication and are logged.
|
*/

// Health check (no auth required)
Route::get('/health', function () {
    $checks = ['app' => true];
    $status = 'ok';

    // Database
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $checks['database'] = true;
    } catch (\Throwable) {
        $checks['database'] = false;
        $status = 'degraded';
    }

    // Redis/Cache
    try {
        \Illuminate\Support\Facades\Cache::store()->put('health_check', true, 10);
        $checks['cache'] = \Illuminate\Support\Facades\Cache::store()->get('health_check') === true;
        if (!$checks['cache']) $status = 'degraded';
    } catch (\Throwable) {
        $checks['cache'] = false;
        $status = 'degraded';
    }

    $httpCode = $status === 'ok' ? 200 : 503;

    return response()->json([
        'status' => $status,
        'version' => 'v1',
        'timestamp' => now()->toIso8601String(),
        'checks' => $checks,
    ], $httpCode);
});

// Authenticated routes
Route::middleware(['api.log', 'api.auth', 'api.rate', 'api.idempotency'])->group(function () {

    // ============================================================
    // Auth endpoints
    // ============================================================

    Route::prefix('auth')->group(function () {
        // Refresh token
        Route::post('/refresh', [\App\Http\Controllers\Api\V1\AuthController::class, 'refresh'])
            ->withoutMiddleware(['api.auth']);

        // Revoke token
        Route::post('/revoke', [\App\Http\Controllers\Api\V1\AuthController::class, 'revoke']);

        // Get current token info
        Route::get('/me', [\App\Http\Controllers\Api\V1\AuthController::class, 'me']);
    });

    // ============================================================
    // Menu endpoints
    // ============================================================

    Route::prefix('menu')->middleware('api.scope:menu:read')->group(function () {
        // Categories
        Route::get('/categories', [\App\Http\Controllers\Api\V1\MenuController::class, 'categories']);
        Route::get('/categories/{id}', [\App\Http\Controllers\Api\V1\MenuController::class, 'category']);

        // Dishes
        Route::get('/dishes', [\App\Http\Controllers\Api\V1\MenuController::class, 'dishes']);
        Route::get('/dishes/{id}', [\App\Http\Controllers\Api\V1\MenuController::class, 'dish']);

        // Modifiers
        Route::get('/modifiers', [\App\Http\Controllers\Api\V1\MenuController::class, 'modifiers']);
        Route::get('/modifiers/{id}', [\App\Http\Controllers\Api\V1\MenuController::class, 'modifier']);

        // Stop list
        Route::get('/stop-list', [\App\Http\Controllers\Api\V1\MenuController::class, 'stopList']);

        // Full menu (categories with dishes)
        Route::get('/full', [\App\Http\Controllers\Api\V1\MenuController::class, 'fullMenu']);
    });

    // ============================================================
    // Orders endpoints
    // ============================================================

    Route::prefix('orders')->group(function () {
        // List orders (read scope)
        Route::get('/', [\App\Http\Controllers\Api\V1\OrdersController::class, 'index'])
            ->middleware('api.scope:orders:read');

        // Get single order (read scope)
        Route::get('/{id}', [\App\Http\Controllers\Api\V1\OrdersController::class, 'show'])
            ->middleware('api.scope:orders:read');

        // Create order (write scope)
        Route::post('/', [\App\Http\Controllers\Api\V1\OrdersController::class, 'store'])
            ->middleware('api.scope:orders:write');

        // Update order (write scope)
        Route::patch('/{id}', [\App\Http\Controllers\Api\V1\OrdersController::class, 'update'])
            ->middleware('api.scope:orders:write');

        // Cancel order (write scope)
        Route::post('/{id}/cancel', [\App\Http\Controllers\Api\V1\OrdersController::class, 'cancel'])
            ->middleware('api.scope:orders:write');

        // Order status updates (write scope)
        Route::post('/{id}/confirm', [\App\Http\Controllers\Api\V1\OrdersController::class, 'confirm'])
            ->middleware('api.scope:orders:write');

        Route::post('/{id}/ready', [\App\Http\Controllers\Api\V1\OrdersController::class, 'markReady'])
            ->middleware('api.scope:orders:write');

        Route::post('/{id}/complete', [\App\Http\Controllers\Api\V1\OrdersController::class, 'complete'])
            ->middleware('api.scope:orders:write');

        // Calculate order total (preview, write scope)
        Route::post('/calculate', [\App\Http\Controllers\Api\V1\OrdersController::class, 'calculate'])
            ->middleware('api.scope:orders:write');
    });

    // ============================================================
    // Customers endpoints
    // ============================================================

    Route::prefix('customers')->group(function () {
        // List customers
        Route::get('/', [\App\Http\Controllers\Api\V1\CustomersController::class, 'index'])
            ->middleware('api.scope:customers:read');

        // Get customer
        Route::get('/{id}', [\App\Http\Controllers\Api\V1\CustomersController::class, 'show'])
            ->middleware('api.scope:customers:read');

        // Find by phone
        Route::get('/phone/{phone}', [\App\Http\Controllers\Api\V1\CustomersController::class, 'findByPhone'])
            ->middleware('api.scope:customers:read');

        // Create customer
        Route::post('/', [\App\Http\Controllers\Api\V1\CustomersController::class, 'store'])
            ->middleware('api.scope:customers:write');

        // Update customer
        Route::patch('/{id}', [\App\Http\Controllers\Api\V1\CustomersController::class, 'update'])
            ->middleware('api.scope:customers:write');

        // Customer bonus balance
        Route::get('/{id}/bonus', [\App\Http\Controllers\Api\V1\CustomersController::class, 'bonusBalance'])
            ->middleware('api.scope:customers:read');

        // Customer orders history
        Route::get('/{id}/orders', [\App\Http\Controllers\Api\V1\CustomersController::class, 'orders'])
            ->middleware('api.scope:customers:read,orders:read');

        // Customer addresses
        Route::get('/{id}/addresses', [\App\Http\Controllers\Api\V1\CustomersController::class, 'addresses'])
            ->middleware('api.scope:customers:read');

        Route::post('/{id}/addresses', [\App\Http\Controllers\Api\V1\CustomersController::class, 'addAddress'])
            ->middleware('api.scope:customers:write');
    });

    // ============================================================
    // Tables endpoints
    // ============================================================

    Route::prefix('tables')->middleware('api.scope:tables:read')->group(function () {
        // List tables
        Route::get('/', [\App\Http\Controllers\Api\V1\TablesController::class, 'index']);

        // Get table
        Route::get('/{id}', [\App\Http\Controllers\Api\V1\TablesController::class, 'show']);

        // List zones
        Route::get('/zones', [\App\Http\Controllers\Api\V1\TablesController::class, 'zones']);

        // Table availability
        Route::get('/{id}/availability', [\App\Http\Controllers\Api\V1\TablesController::class, 'availability']);
    });

    // ============================================================
    // Reservations endpoints
    // ============================================================

    Route::prefix('reservations')->group(function () {
        // List reservations
        Route::get('/', [\App\Http\Controllers\Api\V1\ReservationsController::class, 'index'])
            ->middleware('api.scope:reservations:read');

        // Get reservation
        Route::get('/{id}', [\App\Http\Controllers\Api\V1\ReservationsController::class, 'show'])
            ->middleware('api.scope:reservations:read');

        // Create reservation
        Route::post('/', [\App\Http\Controllers\Api\V1\ReservationsController::class, 'store'])
            ->middleware('api.scope:reservations:write');

        // Update reservation
        Route::patch('/{id}', [\App\Http\Controllers\Api\V1\ReservationsController::class, 'update'])
            ->middleware('api.scope:reservations:write');

        // Cancel reservation
        Route::post('/{id}/cancel', [\App\Http\Controllers\Api\V1\ReservationsController::class, 'cancel'])
            ->middleware('api.scope:reservations:write');

        // Check availability
        Route::post('/availability', [\App\Http\Controllers\Api\V1\ReservationsController::class, 'checkAvailability'])
            ->middleware('api.scope:reservations:read');
    });

    // ============================================================
    // Delivery endpoints
    // ============================================================

    Route::prefix('delivery')->middleware('api.scope:orders:read')->group(function () {
        // Delivery zones
        Route::get('/zones', [\App\Http\Controllers\Api\V1\DeliveryController::class, 'zones']);

        // Check delivery availability by address
        Route::post('/check', [\App\Http\Controllers\Api\V1\DeliveryController::class, 'checkAddress']);

        // Calculate delivery fee
        Route::post('/calculate', [\App\Http\Controllers\Api\V1\DeliveryController::class, 'calculateFee']);

        // Track order delivery
        Route::get('/track/{orderId}', [\App\Http\Controllers\Api\V1\DeliveryController::class, 'track']);
    });

    // ============================================================
    // Promotions & Promo codes
    // ============================================================

    Route::prefix('promotions')->middleware('api.scope:menu:read')->group(function () {
        // List active promotions
        Route::get('/', [\App\Http\Controllers\Api\V1\PromotionsController::class, 'index']);

        // Validate promo code
        Route::post('/validate-code', [\App\Http\Controllers\Api\V1\PromotionsController::class, 'validateCode']);
    });

    // ============================================================
    // Restaurant info
    // ============================================================

    Route::prefix('restaurant')->middleware('api.scope:menu:read')->group(function () {
        // Get restaurant info
        Route::get('/info', [\App\Http\Controllers\Api\V1\RestaurantController::class, 'info']);

        // Get working hours
        Route::get('/hours', [\App\Http\Controllers\Api\V1\RestaurantController::class, 'hours']);

        // Check if open now
        Route::get('/is-open', [\App\Http\Controllers\Api\V1\RestaurantController::class, 'isOpen']);
    });

    // ============================================================
    // Payments
    // ============================================================

    Route::prefix('payments')->group(function () {
        // Available payment methods
        Route::get('/methods', [\App\Http\Controllers\Api\V1\PaymentsController::class, 'methods'])
            ->middleware('api.scope:payments:read');

        // Calculate payment preview
        Route::post('/calculate', [\App\Http\Controllers\Api\V1\PaymentsController::class, 'calculate'])
            ->middleware('api.scope:payments:read');

        // Payment status
        Route::get('/status/{orderId}', [\App\Http\Controllers\Api\V1\PaymentsController::class, 'status'])
            ->middleware('api.scope:payments:read');

        // Validate promo code
        Route::post('/validate-promo', [\App\Http\Controllers\Api\V1\PaymentsController::class, 'validatePromo'])
            ->middleware('api.scope:payments:read');

        // Process payment
        Route::post('/pay', [\App\Http\Controllers\Api\V1\PaymentsController::class, 'pay'])
            ->middleware('api.scope:payments:write');

        // Process refund
        Route::post('/refund', [\App\Http\Controllers\Api\V1\PaymentsController::class, 'refund'])
            ->middleware('api.scope:payments:write');
    });

    // ============================================================
    // Kitchen (KDS)
    // ============================================================

    Route::prefix('kitchen')->group(function () {
        // Kitchen stations list
        Route::get('/stations', [\App\Http\Controllers\Api\V1\KitchenController::class, 'stations'])
            ->middleware('api.scope:kitchen:read');

        // Kitchen queue (orders with items)
        Route::get('/queue', [\App\Http\Controllers\Api\V1\KitchenController::class, 'queue'])
            ->middleware('api.scope:kitchen:read');

        // Single order for kitchen
        Route::get('/orders/{orderId}', [\App\Http\Controllers\Api\V1\KitchenController::class, 'order'])
            ->middleware('api.scope:kitchen:read');

        // Items by station
        Route::get('/items', [\App\Http\Controllers\Api\V1\KitchenController::class, 'items'])
            ->middleware('api.scope:kitchen:read');

        // Kitchen statistics
        Route::get('/stats', [\App\Http\Controllers\Api\V1\KitchenController::class, 'stats'])
            ->middleware('api.scope:kitchen:read');

        // Start cooking item
        Route::post('/items/{itemId}/start', [\App\Http\Controllers\Api\V1\KitchenController::class, 'startItem'])
            ->middleware('api.scope:kitchen:write');

        // Mark item ready
        Route::post('/items/{itemId}/ready', [\App\Http\Controllers\Api\V1\KitchenController::class, 'readyItem'])
            ->middleware('api.scope:kitchen:write');

        // Mark item served
        Route::post('/items/{itemId}/served', [\App\Http\Controllers\Api\V1\KitchenController::class, 'servedItem'])
            ->middleware('api.scope:kitchen:write');

        // Recall item to kitchen
        Route::post('/items/{itemId}/recall', [\App\Http\Controllers\Api\V1\KitchenController::class, 'recallItem'])
            ->middleware('api.scope:kitchen:write');

        // Bulk status update
        Route::post('/items/bulk-status', [\App\Http\Controllers\Api\V1\KitchenController::class, 'bulkStatus'])
            ->middleware('api.scope:kitchen:write');
    });

    // ============================================================
    // Loyalty (Bonus program)
    // ============================================================

    Route::prefix('loyalty')->group(function () {
        // Get loyalty program info (public)
        Route::get('/program', [\App\Http\Controllers\Api\V1\LoyaltyController::class, 'program'])
            ->middleware('api.scope:loyalty:read');

        // Customer balance and level
        Route::get('/balance/{customerId}', [\App\Http\Controllers\Api\V1\LoyaltyController::class, 'balance'])
            ->middleware('api.scope:loyalty:read');

        // Customer transaction history
        Route::get('/transactions/{customerId}', [\App\Http\Controllers\Api\V1\LoyaltyController::class, 'transactions'])
            ->middleware('api.scope:loyalty:read');

        // Calculate earning preview
        Route::post('/calculate-earning', [\App\Http\Controllers\Api\V1\LoyaltyController::class, 'calculateEarning'])
            ->middleware('api.scope:loyalty:read');

        // Calculate spending preview
        Route::post('/calculate-spending', [\App\Http\Controllers\Api\V1\LoyaltyController::class, 'calculateSpending'])
            ->middleware('api.scope:loyalty:read');

        // Earn bonuses
        Route::post('/earn', [\App\Http\Controllers\Api\V1\LoyaltyController::class, 'earn'])
            ->middleware('api.scope:loyalty:write');

        // Spend bonuses
        Route::post('/spend', [\App\Http\Controllers\Api\V1\LoyaltyController::class, 'spend'])
            ->middleware('api.scope:loyalty:write');
    });

    // ============================================================
    // Batch Operations
    // ============================================================

    Route::prefix('batch')->group(function () {
        // Generic batch execute (requires appropriate scopes per operation)
        Route::post('/', [\App\Http\Controllers\Api\V1\BatchController::class, 'execute'])
            ->middleware('api.scope:orders:write');

        // Batch update stop list
        Route::post('/stop-list', [\App\Http\Controllers\Api\V1\BatchController::class, 'updateStopList'])
            ->middleware('api.scope:menu:write');

        // Batch update order items status
        Route::post('/order-items-status', [\App\Http\Controllers\Api\V1\BatchController::class, 'updateOrderItemsStatus'])
            ->middleware('api.scope:kitchen:write');

        // Batch confirm orders
        Route::post('/confirm-orders', [\App\Http\Controllers\Api\V1\BatchController::class, 'confirmOrders'])
            ->middleware('api.scope:orders:write');

        // Batch update customer tags
        Route::post('/customer-tags', [\App\Http\Controllers\Api\V1\BatchController::class, 'updateCustomerTags'])
            ->middleware('api.scope:customers:write');
    });

    // ============================================================
    // Webhooks management
    // ============================================================

    Route::prefix('webhooks')->middleware('api.scope:webhooks:manage')->group(function () {
        // Get current webhook config
        Route::get('/', [\App\Http\Controllers\Api\V1\WebhooksController::class, 'show']);

        // Update webhook config
        Route::put('/', [\App\Http\Controllers\Api\V1\WebhooksController::class, 'update']);

        // Test webhook
        Route::post('/test', [\App\Http\Controllers\Api\V1\WebhooksController::class, 'test']);

        // List available events
        Route::get('/events', [\App\Http\Controllers\Api\V1\WebhooksController::class, 'events']);

        // Webhook deliveries (for recovery)
        Route::get('/deliveries', [\App\Http\Controllers\Api\V1\WebhooksController::class, 'deliveries']);
        Route::get('/deliveries/{eventId}', [\App\Http\Controllers\Api\V1\WebhooksController::class, 'delivery']);
        Route::post('/deliveries/{eventId}/retry', [\App\Http\Controllers\Api\V1\WebhooksController::class, 'retryDelivery']);
    });

});

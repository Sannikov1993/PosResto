<?php

/**
 * Reservation Actions Routes
 *
 * Enterprise-level routes using Action-based controllers.
 *
 * Include in routes/api.php:
 *   require __DIR__ . '/reservation-actions.php';
 */

use App\Http\Controllers\Api\ReservationActionsController;
use Illuminate\Support\Facades\Route;

Route::prefix('reservations/{reservation}')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        // Status transitions
        Route::post('confirm', [ReservationActionsController::class, 'confirm'])
            ->name('reservations.confirm');

        Route::post('seat', [ReservationActionsController::class, 'seat'])
            ->name('reservations.seat');

        Route::post('seat-with-order', [ReservationActionsController::class, 'seatWithOrder'])
            ->name('reservations.seatWithOrder');

        Route::post('unseat', [ReservationActionsController::class, 'unseat'])
            ->name('reservations.unseat');

        Route::post('complete', [ReservationActionsController::class, 'complete'])
            ->name('reservations.complete');

        Route::post('cancel', [ReservationActionsController::class, 'cancel'])
            ->name('reservations.cancel');

        Route::post('no-show', [ReservationActionsController::class, 'noShow'])
            ->name('reservations.noShow');

        // Deposit operations
        Route::prefix('deposit')->group(function () {
            Route::get('/', [ReservationActionsController::class, 'depositSummary'])
                ->name('reservations.deposit.summary');

            Route::post('pay', [ReservationActionsController::class, 'payDeposit'])
                ->name('reservations.deposit.pay');

            Route::post('refund', [ReservationActionsController::class, 'refundDeposit'])
                ->name('reservations.deposit.refund');
        });
    });

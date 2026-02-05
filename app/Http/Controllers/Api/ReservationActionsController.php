<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Reservation\Actions\CancelReservation;
use App\Domain\Reservation\Actions\CompleteReservation;
use App\Domain\Reservation\Actions\ConfirmReservation;
use App\Domain\Reservation\Actions\MarkNoShow;
use App\Domain\Reservation\Actions\SeatGuests;
use App\Domain\Reservation\Actions\UnseatGuests;
use App\Domain\Reservation\DTOs\CancelReservationData;
use App\Domain\Reservation\DTOs\DepositPaymentData;
use App\Domain\Reservation\DTOs\SeatGuestsData;
use App\Domain\Reservation\Services\DepositService;
use App\Events\ReservationEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thin controller for reservation status actions.
 *
 * Delegates business logic to Action classes.
 * Handles HTTP concerns only:
 * - Request validation
 * - Response formatting
 * - Real-time events
 *
 * Usage:
 *   Route::post('/reservations/{reservation}/confirm', [ReservationActionsController::class, 'confirm']);
 */
class ReservationActionsController extends Controller
{
    public function __construct(
        private readonly ConfirmReservation $confirmAction,
        private readonly SeatGuests $seatGuestsAction,
        private readonly UnseatGuests $unseatGuestsAction,
        private readonly CompleteReservation $completeAction,
        private readonly CancelReservation $cancelAction,
        private readonly MarkNoShow $noShowAction,
        private readonly DepositService $depositService,
    ) {}

    /**
     * Confirm a pending reservation.
     *
     * POST /api/reservations/{reservation}/confirm
     */
    public function confirm(Reservation $reservation): JsonResponse
    {
        $result = $this->confirmAction->execute(
            reservation: $reservation,
            userId: auth()->id()
        );

        $this->dispatchRealtimeEvent($reservation, 'reservation_confirmed');

        return ApiResponse::success(
            data: new ReservationResource($result->reservation),
            message: $result->message
        );
    }

    /**
     * Seat guests (without creating order).
     *
     * POST /api/reservations/{reservation}/seat
     */
    public function seat(Reservation $reservation): JsonResponse
    {
        $data = SeatGuestsData::withoutOrder(auth()->id());

        $result = $this->seatGuestsAction->execute(
            reservation: $reservation,
            createOrder: $data->createOrder,
            userId: $data->userId,
            transferDeposit: $data->transferDeposit,
            guestsCount: $data->guestsCount
        );

        $this->dispatchRealtimeEvent($reservation, 'reservation_seated', [
            'table_id' => $reservation->table_id,
        ]);

        return ApiResponse::success(
            data: new ReservationResource($result->reservation),
            message: $result->message
        );
    }

    /**
     * Seat guests and create order.
     *
     * POST /api/reservations/{reservation}/seat-with-order
     */
    public function seatWithOrder(Request $request, Reservation $reservation): JsonResponse
    {
        $data = SeatGuestsData::fromRequest($request);

        $result = $this->seatGuestsAction->execute(
            reservation: $reservation,
            createOrder: true,
            userId: $data->userId,
            transferDeposit: $data->transferDeposit,
            guestsCount: $data->guestsCount
        );

        $this->dispatchRealtimeEvent($reservation, 'reservation_seated', [
            'table_id' => $reservation->table_id,
            'order_id' => $result->order?->id,
        ]);

        $message = 'Гости сели, заказ создан';
        if ($result->depositTransferred) {
            $message .= sprintf(' (депозит %.0f₽ учтён)', $reservation->deposit);
        }

        return ApiResponse::success(
            data: [
                'reservation' => $result->reservation,
                'order' => $result->order,
                'deposit_transferred' => $result->depositTransferred,
            ],
            message: $message
        );
    }

    /**
     * Unseat guests (return to confirmed status).
     *
     * POST /api/reservations/{reservation}/unseat
     */
    public function unseat(Request $request, Reservation $reservation): JsonResponse
    {
        $force = $request->boolean('force', false);

        $result = $this->unseatGuestsAction->execute(
            reservation: $reservation,
            force: $force,
            userId: auth()->id()
        );

        return ApiResponse::success(
            data: ['reservation' => $result->reservation],
            message: $result->message
        );
    }

    /**
     * Complete a reservation.
     *
     * POST /api/reservations/{reservation}/complete
     */
    public function complete(Request $request, Reservation $reservation): JsonResponse
    {
        $force = $request->boolean('force', false);

        $result = $this->completeAction->execute(
            reservation: $reservation,
            force: $force,
            userId: auth()->id()
        );

        $this->dispatchRealtimeEvent($reservation, 'reservation_completed');

        return ApiResponse::success(
            data: $result->reservation,
            message: $result->message
        );
    }

    /**
     * Cancel a reservation.
     *
     * POST /api/reservations/{reservation}/cancel
     */
    public function cancel(Request $request, Reservation $reservation): JsonResponse
    {
        $request->validate(CancelReservationData::rules());

        $data = CancelReservationData::fromRequest($request);

        $result = $this->cancelAction->execute(
            reservation: $reservation,
            reason: $data->reason,
            refundDeposit: $data->refundDeposit,
            userId: $data->userId
        );

        $this->dispatchRealtimeEvent($reservation, 'reservation_cancelled', [
            'reason' => $data->reason,
            'deposit_refunded' => $result->metadata['deposit_refunded'] ?? false,
        ]);

        $message = 'Бронирование отменено';
        if ($result->metadata['deposit_refunded'] ?? false) {
            $message .= sprintf(' (депозит %.0f₽ возвращён)', $reservation->deposit);
        }

        return ApiResponse::success(
            data: [
                'reservation' => $result->reservation,
                'deposit_refunded' => $result->metadata['deposit_refunded'] ?? false,
            ],
            message: $message
        );
    }

    /**
     * Mark reservation as no-show.
     *
     * POST /api/reservations/{reservation}/no-show
     */
    public function noShow(Request $request, Reservation $reservation): JsonResponse
    {
        $forfeitDeposit = $request->boolean('forfeit_deposit', true);
        $notes = $request->input('notes');

        $result = $this->noShowAction->execute(
            reservation: $reservation,
            forfeitDeposit: $forfeitDeposit,
            userId: auth()->id(),
            notes: $notes
        );

        $this->dispatchRealtimeEvent($reservation, 'reservation_no_show');

        return ApiResponse::success(
            data: $result->reservation,
            message: $result->message
        );
    }

    /**
     * Pay deposit for reservation.
     *
     * POST /api/reservations/{reservation}/deposit/pay
     */
    public function payDeposit(Request $request, Reservation $reservation): JsonResponse
    {
        $request->validate(DepositPaymentData::rules());

        $data = DepositPaymentData::fromRequest($request);

        $result = $this->depositService->markAsPaid(
            reservation: $reservation,
            paymentMethod: $data->paymentMethod,
            transactionId: $data->transactionId,
            userId: $data->userId
        );

        $this->dispatchRealtimeEvent($reservation, 'deposit_paid', [
            'amount' => $reservation->deposit,
            'method' => $data->paymentMethod,
        ]);

        return ApiResponse::success(
            data: [
                'reservation' => $result,
                'amount' => $reservation->deposit,
                'method' => $data->paymentMethod,
            ],
            message: 'Депозит успешно оплачен'
        );
    }

    /**
     * Refund deposit for reservation.
     *
     * POST /api/reservations/{reservation}/deposit/refund
     */
    public function refundDeposit(Request $request, Reservation $reservation): JsonResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $amount = (float) $reservation->deposit;

        $result = $this->depositService->refund(
            reservation: $reservation,
            reason: $request->input('reason'),
            userId: auth()->id()
        );

        $this->dispatchRealtimeEvent($reservation, 'deposit_refunded', [
            'amount' => $amount,
        ]);

        return ApiResponse::success(
            data: [
                'reservation' => $result,
                'amount' => $amount,
            ],
            message: 'Депозит успешно возвращён'
        );
    }

    /**
     * Get deposit summary.
     *
     * GET /api/reservations/{reservation}/deposit
     */
    public function depositSummary(Reservation $reservation): JsonResponse
    {
        return ApiResponse::success(
            data: $this->depositService->getSummary($reservation)
        );
    }

    /**
     * Dispatch real-time event.
     */
    private function dispatchRealtimeEvent(
        Reservation $reservation,
        string $type,
        array $extra = []
    ): void {
        ReservationEvent::dispatch($reservation->restaurant_id, $type, array_merge([
            'reservation_id' => $reservation->id,
            'table_id' => $reservation->table_id,
            'customer_name' => $reservation->guest_name,
        ], $extra));
    }
}

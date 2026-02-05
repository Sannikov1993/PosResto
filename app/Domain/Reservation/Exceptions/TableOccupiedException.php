<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Exceptions;

use App\Models\Table;
use Illuminate\Support\Collection;

/**
 * Exception thrown when attempting to use occupied tables.
 *
 * Used during seating operations when tables are already in use.
 */
final class TableOccupiedException extends ReservationException
{
    protected string $errorCode = 'tables_occupied';
    protected int $httpStatus = 409; // Conflict

    /**
     * IDs of occupied tables.
     */
    public readonly array $tableIds;

    /**
     * Numbers/names of occupied tables (for display).
     */
    public readonly array $tableNumbers;

    /**
     * Current orders on the tables (if any).
     */
    public readonly array $orderIds;

    private function __construct(
        string $message,
        array $tableIds,
        array $tableNumbers,
        array $orderIds = []
    ) {
        parent::__construct($message);

        $this->tableIds = $tableIds;
        $this->tableNumbers = $tableNumbers;
        $this->orderIds = $orderIds;

        $this->context = [
            'table_ids' => $tableIds,
            'table_numbers' => $tableNumbers,
            'order_ids' => $orderIds,
        ];
    }

    /**
     * Create exception for multiple occupied tables.
     *
     * @param Collection<Table>|array $tables
     */
    public static function tables(Collection|array $tables): self
    {
        $tables = $tables instanceof Collection ? $tables : collect($tables);

        $tableIds = $tables->pluck('id')->toArray();
        $tableNumbers = $tables->map(fn($t) => $t->name ?? $t->number ?? $t)->toArray();
        $orderIds = $tables->pluck('current_order_id')->filter()->toArray();

        $numbersStr = implode(', ', $tableNumbers);
        $message = count($tableNumbers) === 1
            ? "Стол {$numbersStr} уже занят."
            : "Столы {$numbersStr} уже заняты.";

        return new self($message, $tableIds, $tableNumbers, $orderIds);
    }

    /**
     * Create exception for single occupied table.
     */
    public static function table(Table|int|string $table): self
    {
        if ($table instanceof Table) {
            return self::tables(collect([$table]));
        }

        $tableId = is_int($table) ? $table : null;
        $tableNumber = is_string($table) ? $table : (string) $table;

        $message = "Стол {$tableNumber} уже занят.";

        return new self(
            $message,
            $tableId ? [$tableId] : [],
            [$tableNumber],
            []
        );
    }

    /**
     * Create exception when table is occupied by another reservation.
     */
    public static function byReservation(Table $table, int $reservationId): self
    {
        $tableNumber = $table->name ?? $table->number;

        $message = sprintf(
            'Стол %s занят другой бронью #%d.',
            $tableNumber,
            $reservationId
        );

        $instance = new self(
            $message,
            [$table->id],
            [$tableNumber],
            []
        );

        $instance->context['conflicting_reservation_id'] = $reservationId;

        return $instance;
    }

    /**
     * Create exception when table is occupied by an active order.
     */
    public static function byOrder(Table $table, int $orderId): self
    {
        $tableNumber = $table->name ?? $table->number;

        $message = sprintf(
            'Стол %s занят активным заказом #%d.',
            $tableNumber,
            $orderId
        );

        return new self(
            $message,
            [$table->id],
            [$tableNumber],
            [$orderId]
        );
    }

    /**
     * Create exception when tables have different statuses.
     */
    public static function mixedStatus(Collection $tables): self
    {
        $occupied = $tables->where('status', 'occupied');
        $free = $tables->where('status', 'free');

        $occupiedNumbers = $occupied->map(fn($t) => $t->name ?? $t->number)->implode(', ');
        $freeNumbers = $free->map(fn($t) => $t->name ?? $t->number)->implode(', ');

        $message = "Нельзя объединить столы: {$occupiedNumbers} заняты, {$freeNumbers} свободны.";

        $instance = new self(
            $message,
            $occupied->pluck('id')->toArray(),
            $occupied->map(fn($t) => $t->name ?? $t->number)->toArray(),
            $occupied->pluck('current_order_id')->filter()->toArray()
        );

        $instance->context['free_table_ids'] = $free->pluck('id')->toArray();
        $instance->context['free_table_numbers'] = $free->map(fn($t) => $t->name ?? $t->number)->toArray();

        return $instance;
    }

    /**
     * Check if specific table is in the occupied list.
     */
    public function hasTable(int $tableId): bool
    {
        return in_array($tableId, $this->tableIds, true);
    }

    /**
     * Get count of occupied tables.
     */
    public function getCount(): int
    {
        return count($this->tableIds);
    }
}

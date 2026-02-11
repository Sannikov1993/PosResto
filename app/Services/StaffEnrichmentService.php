<?php

namespace App\Services;

use App\Models\TimeEntry;
use App\Models\Order;
use App\Models\Tip;
use App\Models\StaffInvitation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StaffEnrichmentService
{
    /**
     * Обогащение коллекции сотрудников статистикой (батч-запросы вместо N+1)
     */
    public function enrichUsers(Collection $users): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        $userIds = $users->pluck('id')->toArray();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        // Батч: активные смены (кто сейчас работает)
        $activeEntries = TimeEntry::whereIn('user_id', $userIds)
            ->where('status', 'active')
            ->get()
            ->keyBy('user_id');

        // Батч: заказы за месяц (count + sum)
        $orderStats = Order::whereIn('user_id', $userIds)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->where('status', 'completed')
            ->groupBy('user_id')
            ->select('user_id', DB::raw('COUNT(*) as orders_count'), DB::raw('SUM(total) as orders_sum'))
            ->get()
            ->keyBy('user_id');

        // Батч: часы работы за месяц
        $hoursStats = TimeEntry::whereIn('user_id', $userIds)
            ->whereBetween('clock_in', [$monthStart, $monthEnd])
            ->where('status', 'completed')
            ->groupBy('user_id')
            ->select('user_id', DB::raw('SUM(worked_minutes) as total_minutes'))
            ->get()
            ->keyBy('user_id');

        // Батч: чаевые за месяц
        $tipsStats = Tip::whereIn('user_id', $userIds)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->groupBy('user_id')
            ->select('user_id', DB::raw('SUM(amount) as total_tips'))
            ->get()
            ->keyBy('user_id');

        // Батч: ожидающие приглашения
        $pendingInvitations = StaffInvitation::whereIn('user_id', $userIds)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->pluck('user_id')
            ->flip()
            ->toArray();

        return $users->map(function ($user) use ($activeEntries, $orderStats, $hoursStats, $tipsStats, $pendingInvitations) {
            $activeEntry = $activeEntries->get($user->id);
            $orders = $orderStats->get($user->id);
            $hours = $hoursStats->get($user->id);
            $tips = $tipsStats->get($user->id);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'login' => $user->login,
                'phone' => $user->phone,
                'role' => $user->role,
                'position' => $user->position,
                'is_active' => $user->is_active,
                'has_pin' => !empty($user->pin_code),
                'has_password' => !empty($user->password),
                'pending_invitation' => isset($pendingInvitations[$user->id]),
                'hire_date' => $user->hire_date,
                'hired_at' => $user->hire_date,
                'birth_date' => $user->birth_date,
                'address' => $user->address,
                'emergency_contact' => $user->emergency_contact,
                'salary' => $user->salary,
                'salary_type' => $user->salary_type ?? 'fixed',
                'hourly_rate' => $user->hourly_rate,
                'sales_percent' => $user->percent_rate,
                'bank_card' => $user->bank_card,
                'fired_at' => $user->fired_at,
                'fire_reason' => $user->fire_reason,
                'is_working' => $activeEntry !== null,
                'current_shift_start' => $activeEntry?->clock_in?->format('H:i'),
                'month_orders_count' => $orders?->orders_count ?? 0,
                'month_orders_sum' => round($orders?->orders_sum ?? 0, 2),
                'month_hours_worked' => round(($hours?->total_minutes ?? 0) / 60, 1),
                'month_tips' => round($tips?->total_tips ?? 0, 2),
            ];
        })->toArray();
    }
}

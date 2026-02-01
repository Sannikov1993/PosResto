<?php

namespace App\Services;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Illuminate\Support\Facades\Log;
use App\Models\PushSubscription;
use App\Models\User;

/**
 * Сервис для Web Push уведомлений (VAPID)
 */
class WebPushService
{
    private ?string $publicKey;
    private ?string $privateKey;
    private string $subject;
    private ?WebPush $webPush = null;

    public function __construct()
    {
        $this->publicKey = config('services.webpush.public_key') ?: env('VAPID_PUBLIC_KEY');
        $this->privateKey = config('services.webpush.private_key') ?: env('VAPID_PRIVATE_KEY');
        $this->subject = config('services.webpush.subject') ?: env('VAPID_SUBJECT', 'mailto:admin@menulab.ru');
    }

    /**
     * Проверить настроен ли сервис
     */
    public function isConfigured(): bool
    {
        return !empty($this->publicKey) && !empty($this->privateKey);
    }

    /**
     * Получить публичный ключ для клиента
     */
    public function getPublicKey(): ?string
    {
        return $this->publicKey;
    }

    /**
     * Get WebPush instance
     */
    protected function getWebPush(): ?WebPush
    {
        if (!$this->isConfigured()) {
            return null;
        }

        if ($this->webPush === null) {
            try {
                $this->webPush = new WebPush([
                    'VAPID' => [
                        'subject' => $this->subject,
                        'publicKey' => $this->publicKey,
                        'privateKey' => $this->privateKey,
                    ],
                ]);
                $this->webPush->setReuseVAPIDHeaders(true);
            } catch (\Exception $e) {
                Log::error('WebPushService: Failed to initialize WebPush', [
                    'message' => $e->getMessage(),
                ]);
                return null;
            }
        }

        return $this->webPush;
    }

    /**
     * Сохранить подписку клиента
     */
    public function saveSubscription(array $subscription, ?int $customerId = null, ?string $phone = null): ?PushSubscription
    {
        try {
            return PushSubscription::createOrUpdateForCustomer($customerId, $subscription, $phone);
        } catch (\Exception $e) {
            Log::error('WebPushService: Ошибка сохранения подписки', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Сохранить подписку сотрудника
     */
    public function saveUserSubscription(int $userId, array $subscription): ?PushSubscription
    {
        try {
            return PushSubscription::createOrUpdateForUser($userId, $subscription);
        } catch (\Exception $e) {
            Log::error('WebPushService: Ошибка сохранения подписки пользователя', [
                'message' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            return null;
        }
    }

    /**
     * Удалить подписку
     */
    public function deleteSubscription(string $endpoint): bool
    {
        return PushSubscription::where('endpoint', $endpoint)->delete() > 0;
    }

    /**
     * Отправить уведомление одному подписчику
     */
    public function sendNotification(PushSubscription $pushSub, array $payload): bool
    {
        $webPush = $this->getWebPush();
        if (!$webPush) {
            Log::warning('WebPushService: VAPID ключи не настроены');
            return false;
        }

        try {
            $subscription = Subscription::create($pushSub->toWebPush());

            $webPush->queueNotification(
                $subscription,
                json_encode($payload),
                ['TTL' => 86400]
            );

            $results = $webPush->flush();

            foreach ($results as $report) {
                if ($report->isSuccess()) {
                    $pushSub->markAsUsed();
                    return true;
                } else {
                    $statusCode = $report->getResponse()?->getStatusCode();

                    // 410 Gone или 404 - подписка недействительна
                    if (in_array($statusCode, [410, 404])) {
                        $pushSub->deactivate();
                    }

                    Log::warning('WebPushService: Push failed', [
                        'endpoint' => $report->getEndpoint(),
                        'reason' => $report->getReason(),
                    ]);
                    return false;
                }
            }

            return false;

        } catch (\Exception $e) {
            Log::error('WebPushService: Ошибка отправки', [
                'message' => $e->getMessage(),
                'endpoint' => $pushSub->endpoint,
            ]);
            return false;
        }
    }

    /**
     * Отправить уведомление клиенту по ID или телефону
     */
    public function sendToCustomer(?int $customerId, ?string $phone, array $payload): int
    {
        $query = PushSubscription::active();

        if ($customerId) {
            $query->forCustomer($customerId);
        } elseif ($phone) {
            $query->forPhone($phone);
        } else {
            return 0;
        }

        return $this->sendBatch($query->get(), $payload);
    }

    /**
     * Отправить уведомление сотруднику
     */
    public function sendToUser(int $userId, array $payload): int
    {
        $subscriptions = PushSubscription::forUser($userId)->active()->get();
        return $this->sendBatch($subscriptions, $payload);
    }

    /**
     * Отправить уведомление нескольким пользователям
     */
    public function sendToUsers(array $userIds, array $payload): int
    {
        $subscriptions = PushSubscription::getForUsers($userIds);
        return $this->sendBatch($subscriptions, $payload);
    }

    /**
     * Отправить batch уведомлений
     */
    protected function sendBatch($subscriptions, array $payload): int
    {
        $webPush = $this->getWebPush();
        if (!$webPush || $subscriptions->isEmpty()) {
            return 0;
        }

        $sent = 0;
        $payloadJson = json_encode($payload);

        try {
            foreach ($subscriptions as $pushSub) {
                $subscription = Subscription::create($pushSub->toWebPush());
                $webPush->queueNotification($subscription, $payloadJson, ['TTL' => 86400]);
            }

            $results = $webPush->flush();
            $subIndex = 0;

            foreach ($results as $report) {
                $pushSub = $subscriptions[$subIndex] ?? null;
                $subIndex++;

                if (!$pushSub) continue;

                if ($report->isSuccess()) {
                    $pushSub->markAsUsed();
                    $sent++;
                } else {
                    $statusCode = $report->getResponse()?->getStatusCode();
                    if (in_array($statusCode, [410, 404])) {
                        $pushSub->deactivate();
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('WebPushService: Batch send error', [
                'message' => $e->getMessage(),
            ]);
        }

        return $sent;
    }

    /**
     * Уведомление для сотрудника: напоминание о смене
     */
    public function notifyShiftReminder(User $user, array $shiftData, string $type = '24h'): int
    {
        if ($type === '24h') {
            $title = 'Напоминание о смене';
            $body = "Завтра смена с {$shiftData['start_time']} до {$shiftData['end_time']}";
        } else {
            $title = 'Смена через час';
            $body = "Ваша смена начинается в {$shiftData['start_time']}. Не опаздывайте!";
        }

        $payload = [
            'title' => $title,
            'body' => $body,
            'icon' => '/images/logo/menulab_icon_192.png',
            'badge' => '/images/logo/menulab_icon_72.png',
            'tag' => "shift-{$shiftData['shift_id']}-{$type}",
            'data' => [
                'type' => 'shift_reminder',
                'shift_id' => $shiftData['shift_id'],
                'reminder_type' => $type,
                'url' => '/cabinet#schedule',
            ],
            'vibrate' => [200, 100, 200],
        ];

        return $this->sendToUser($user->id, $payload);
    }

    /**
     * Уведомление для сотрудника: расписание опубликовано
     */
    public function notifySchedulePublished(User $user, array $data): int
    {
        $payload = [
            'title' => 'Расписание опубликовано',
            'body' => "Новое расписание на {$data['week_label']}. Проверьте свои смены.",
            'icon' => '/images/logo/menulab_icon_192.png',
            'tag' => 'schedule-published',
            'data' => [
                'type' => 'schedule_published',
                'url' => '/cabinet#schedule',
            ],
        ];

        return $this->sendToUser($user->id, $payload);
    }

    /**
     * Уведомление для сотрудника: зарплата выплачена
     */
    public function notifySalaryPaid(User $user, array $data): int
    {
        $payload = [
            'title' => 'Зарплата выплачена',
            'body' => "Вам выплачено {$data['amount']} ₽",
            'icon' => '/images/logo/menulab_icon_192.png',
            'tag' => 'salary-paid',
            'data' => [
                'type' => 'salary_paid',
                'url' => '/cabinet#salary',
            ],
        ];

        return $this->sendToUser($user->id, $payload);
    }

    /**
     * Уведомление для сотрудника: бонус/штраф
     */
    public function notifyBonusPenalty(User $user, string $type, array $data): int
    {
        $isBonus = $type === 'bonus';

        $payload = [
            'title' => $isBonus ? 'Получена премия' : 'Начислен штраф',
            'body' => ($isBonus ? '+' : '-') . "{$data['amount']} ₽" .
                      (!empty($data['reason']) ? ": {$data['reason']}" : ''),
            'icon' => '/images/logo/menulab_icon_192.png',
            'tag' => "{$type}-{$data['id']}",
            'data' => [
                'type' => $type,
                'url' => '/cabinet#salary',
            ],
        ];

        return $this->sendToUser($user->id, $payload);
    }

    // ==================== CUSTOMER NOTIFICATIONS ====================

    /**
     * Уведомление о новом заказе
     */
    public function notifyOrderCreated(?int $customerId, ?string $phone, array $orderData): int
    {
        $payload = [
            'title' => "Заказ #{$orderData['order_number']} принят!",
            'body' => "Сумма: {$orderData['total']} ₽. Ожидайте, готовим!",
            'icon' => '/images/icon-192.png',
            'badge' => '/images/badge-72.png',
            'tag' => "order-{$orderData['order_id']}",
            'data' => [
                'type' => 'order_created',
                'order_id' => $orderData['order_id'],
                'url' => $orderData['track_url'] ?? '/',
            ],
        ];

        return $this->sendToCustomer($customerId, $phone, $payload);
    }

    /**
     * Уведомление о статусе "Готовится"
     */
    public function notifyOrderCooking(?int $customerId, ?string $phone, array $orderData): int
    {
        $payload = [
            'title' => "Заказ #{$orderData['order_number']}",
            'body' => 'Ваш заказ готовится на кухне!',
            'icon' => '/images/icon-192.png',
            'tag' => "order-{$orderData['order_id']}",
            'data' => [
                'type' => 'order_cooking',
                'order_id' => $orderData['order_id'],
            ],
        ];

        return $this->sendToCustomer($customerId, $phone, $payload);
    }

    /**
     * Уведомление о курьере
     */
    public function notifyOrderCourierAssigned(?int $customerId, ?string $phone, array $orderData): int
    {
        $body = 'Курьер в пути!';
        if (!empty($orderData['eta'])) {
            $body .= " Примерное время: {$orderData['eta']} мин";
        }

        $payload = [
            'title' => "Заказ #{$orderData['order_number']}",
            'body' => $body,
            'icon' => '/images/icon-192.png',
            'tag' => "order-{$orderData['order_id']}",
            'vibrate' => [200, 100, 200],
            'data' => [
                'type' => 'order_courier',
                'order_id' => $orderData['order_id'],
            ],
        ];

        return $this->sendToCustomer($customerId, $phone, $payload);
    }

    /**
     * Уведомление о доставке
     */
    public function notifyOrderDelivered(?int $customerId, ?string $phone, array $orderData): int
    {
        $payload = [
            'title' => "Заказ #{$orderData['order_number']} доставлен!",
            'body' => 'Приятного аппетита!',
            'icon' => '/images/icon-192.png',
            'tag' => "order-{$orderData['order_id']}",
            'data' => [
                'type' => 'order_delivered',
                'order_id' => $orderData['order_id'],
            ],
        ];

        return $this->sendToCustomer($customerId, $phone, $payload);
    }
}

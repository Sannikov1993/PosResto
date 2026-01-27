<?php

namespace App\Services;

use App\Models\FiscalReceipt;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AtolOnlineService
{
    protected ?string $apiUrl;
    protected ?string $login;
    protected ?string $password;
    protected ?string $groupCode;
    protected int $timeout;
    protected bool $enabled;

    public function __construct()
    {
        $testMode = config('atol.test_mode', true);
        $this->apiUrl = $testMode
            ? config('atol.test_api_url', '')
            : config('atol.api_url', '');

        $this->login = config('atol.login', '');
        $this->password = config('atol.password', '');
        $this->groupCode = config('atol.group_code', '');
        $this->timeout = config('atol.timeout', 30);
        $this->enabled = config('atol.enabled', false);
    }

    /**
     * Проверка, включена ли фискализация
     */
    public function isEnabled(): bool
    {
        return $this->enabled && $this->login && $this->password && $this->groupCode;
    }

    /**
     * Получение токена авторизации (с кешированием)
     */
    public function getToken(): ?string
    {
        $cacheKey = 'atol_token_' . md5($this->login);

        return Cache::remember($cacheKey, config('atol.token_ttl', 86400) - 60, function () {
            try {
                $response = Http::timeout($this->timeout)
                    ->post("{$this->apiUrl}/getToken", [
                        'login' => $this->login,
                        'pass' => $this->password,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['token'])) {
                        return $data['token'];
                    }
                    if (isset($data['error'])) {
                        Log::error('ATOL getToken error', $data['error']);
                    }
                }

                Log::error('ATOL getToken failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            } catch (\Exception $e) {
                Log::error('ATOL getToken exception', ['message' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Сброс кешированного токена
     */
    public function resetToken(): void
    {
        Cache::forget('atol_token_' . md5($this->login));
    }

    /**
     * Создать чек продажи
     */
    public function sell(Order $order, string $paymentMethod, ?string $customerContact = null): FiscalReceipt
    {
        return $this->createReceipt($order, 'sell', $paymentMethod, $customerContact);
    }

    /**
     * Создать чек возврата
     */
    public function sellRefund(Order $order, string $paymentMethod, ?string $customerContact = null): FiscalReceipt
    {
        return $this->createReceipt($order, 'sell_refund', $paymentMethod, $customerContact);
    }

    /**
     * Создание фискального чека
     */
    protected function createReceipt(
        Order $order,
        string $operation,
        string $paymentMethod,
        ?string $customerContact = null
    ): FiscalReceipt {
        $externalId = Str::uuid()->toString();

        // Формируем позиции чека
        $items = $this->buildReceiptItems($order);

        // Формируем платежи
        $payments = $this->buildPayments($order->total, $paymentMethod);

        // Определяем контакт покупателя
        $email = null;
        $phone = null;
        if ($customerContact) {
            if (filter_var($customerContact, FILTER_VALIDATE_EMAIL)) {
                $email = $customerContact;
            } else {
                $phone = $customerContact;
            }
        }

        // Создаём запись в БД
        $fiscalReceipt = FiscalReceipt::create([
            'restaurant_id' => $order->restaurant_id,
            'order_id' => $order->id,
            'operation' => $operation,
            'external_id' => $externalId,
            'status' => FiscalReceipt::STATUS_PENDING,
            'total' => $order->total,
            'items' => $items,
            'payments' => $payments,
            'customer_email' => $email,
            'customer_phone' => $phone,
        ]);

        // Если фискализация отключена, сразу помечаем как выполнено (тестовый режим)
        if (!$this->isEnabled()) {
            $fiscalReceipt->markAsDone([
                'test_mode' => true,
                'message' => 'Фискализация отключена',
            ]);
            return $fiscalReceipt;
        }

        // Отправляем в АТОЛ
        $this->sendToAtol($fiscalReceipt, $operation);

        return $fiscalReceipt;
    }

    /**
     * Отправка чека в АТОЛ
     */
    protected function sendToAtol(FiscalReceipt $receipt, string $operation): void
    {
        $token = $this->getToken();

        if (!$token) {
            $receipt->markAsFailed('Не удалось получить токен авторизации АТОЛ');
            return;
        }

        $payload = $this->buildAtolPayload($receipt);

        try {
            $response = Http::timeout($this->timeout)
                ->withToken($token)
                ->post("{$this->apiUrl}/{$this->groupCode}/{$operation}", $payload);

            $data = $response->json();

            if ($response->successful() && isset($data['uuid'])) {
                $receipt->markAsProcessing($data['uuid']);
            } else {
                $errorMessage = $data['error']['text'] ?? 'Неизвестная ошибка АТОЛ';
                $receipt->markAsFailed($errorMessage, $data);

                // Если ошибка авторизации - сбросим токен
                if (isset($data['error']['code']) && $data['error']['code'] == 10) {
                    $this->resetToken();
                }
            }
        } catch (\Exception $e) {
            Log::error('ATOL send exception', [
                'receipt_id' => $receipt->id,
                'message' => $e->getMessage(),
            ]);
            $receipt->markAsFailed('Ошибка соединения с АТОЛ: ' . $e->getMessage());
        }
    }

    /**
     * Проверить статус чека
     */
    public function checkStatus(FiscalReceipt $receipt): FiscalReceipt
    {
        if (!$receipt->atol_uuid || !$receipt->isPending()) {
            return $receipt;
        }

        $token = $this->getToken();
        if (!$token) {
            return $receipt;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withToken($token)
                ->get("{$this->apiUrl}/{$this->groupCode}/report/{$receipt->atol_uuid}");

            $data = $response->json();

            if ($response->successful()) {
                $status = $data['status'] ?? null;

                if ($status === 'done') {
                    $payload = $data['payload'] ?? [];
                    $receipt->markAsDone([
                        'fiscal_document_number' => $payload['fiscal_document_number'] ?? null,
                        'fiscal_document_attribute' => $payload['fiscal_document_attribute'] ?? null,
                        'fn_number' => $payload['fn_number'] ?? null,
                        'shift_number' => $payload['shift_number'] ?? null,
                        'receipt_datetime' => $payload['receipt_datetime'] ?? null,
                        'ofd_sum' => $payload['ofd_receipt_url'] ?? null,
                    ]);

                    // Обновляем заказ
                    $receipt->order->update([
                        'is_fiscalized' => true,
                        'fiscal_receipt_number' => $payload['fiscal_document_number'] ?? null,
                    ]);
                } elseif ($status === 'fail') {
                    $errorMessage = $data['error']['text'] ?? 'Ошибка фискализации';
                    $receipt->markAsFailed($errorMessage, $data);
                }
                // status === 'wait' - продолжаем ждать
            }
        } catch (\Exception $e) {
            Log::error('ATOL checkStatus exception', [
                'receipt_id' => $receipt->id,
                'message' => $e->getMessage(),
            ]);
        }

        return $receipt->fresh();
    }

    /**
     * Формирование payload для АТОЛ
     */
    protected function buildAtolPayload(FiscalReceipt $receipt): array
    {
        $client = [];
        if ($receipt->customer_email) {
            $client['email'] = $receipt->customer_email;
        }
        if ($receipt->customer_phone) {
            $client['phone'] = $receipt->customer_phone;
        }

        $payload = [
            'external_id' => $receipt->external_id,
            'receipt' => [
                'client' => $client ?: new \stdClass(),
                'company' => [
                    'inn' => config('atol.company.inn'),
                    'email' => config('atol.company.email'),
                    'payment_address' => config('atol.company.payment_address'),
                ],
                'items' => $receipt->items,
                'payments' => $receipt->payments,
                'total' => (float) $receipt->total,
            ],
            'service' => [
                'callback_url' => config('atol.callback_url'),
            ],
            'timestamp' => now()->format('d.m.Y H:i:s'),
        ];

        // Добавляем СНО если указана
        $sno = config('atol.sno');
        if ($sno) {
            $payload['receipt']['company']['sno'] = $sno;
        }

        return $payload;
    }

    /**
     * Формирование позиций чека из заказа
     */
    protected function buildReceiptItems(Order $order): array
    {
        $items = [];
        $vat = $this->mapVat(config('atol.default_vat', 'none'));

        foreach ($order->items as $item) {
            $items[] = [
                'name' => mb_substr($item->name, 0, 128),
                'price' => (float) $item->price,
                'quantity' => (float) $item->quantity,
                'sum' => (float) $item->total,
                'payment_method' => config('atol.payment_method', 'full_payment'),
                'payment_object' => config('atol.payment_object', 'commodity'),
                'vat' => $vat,
            ];
        }

        return $items;
    }

    /**
     * Формирование платежей
     */
    protected function buildPayments(float $total, string $method): array
    {
        // Тип оплаты: 1 - наличные, 2 - безналичные
        $type = match ($method) {
            'cash' => 1,
            'card', 'online' => 2,
            default => 2,
        };

        return [
            [
                'type' => $type,
                'sum' => $total,
            ],
        ];
    }

    /**
     * Маппинг НДС
     */
    protected function mapVat(string $vat): array
    {
        $vatMap = [
            'none' => ['type' => 'none'],
            'vat0' => ['type' => 'vat0'],
            'vat10' => ['type' => 'vat10'],
            'vat20' => ['type' => 'vat20'],
            'vat110' => ['type' => 'vat110'],
            'vat120' => ['type' => 'vat120'],
        ];

        return $vatMap[$vat] ?? ['type' => 'none'];
    }

    /**
     * Обработка callback от АТОЛ
     */
    public function handleCallback(array $data): ?FiscalReceipt
    {
        $uuid = $data['uuid'] ?? null;
        if (!$uuid) {
            return null;
        }

        $receipt = FiscalReceipt::where('atol_uuid', $uuid)->first();
        if (!$receipt) {
            Log::warning('ATOL callback: receipt not found', ['uuid' => $uuid]);
            return null;
        }

        $status = $data['status'] ?? null;

        if ($status === 'done') {
            $payload = $data['payload'] ?? [];
            $receipt->markAsDone([
                'fiscal_document_number' => $payload['fiscal_document_number'] ?? null,
                'fiscal_document_attribute' => $payload['fiscal_document_attribute'] ?? null,
                'fn_number' => $payload['fn_number'] ?? null,
                'shift_number' => $payload['shift_number'] ?? null,
                'receipt_datetime' => $payload['receipt_datetime'] ?? null,
                'ofd_sum' => $payload['total'] ?? null,
            ]);

            // Обновляем заказ
            $receipt->order->update([
                'is_fiscalized' => true,
                'fiscal_receipt_number' => $payload['fiscal_document_number'] ?? null,
            ]);
        } elseif ($status === 'fail') {
            $errorMessage = $data['error']['text'] ?? 'Ошибка фискализации';
            $receipt->markAsFailed($errorMessage, $data);
        }

        return $receipt->fresh();
    }
}

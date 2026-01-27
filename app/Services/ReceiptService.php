<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Printer;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Сервис генерации чеков
 */
class ReceiptService
{
    private EscPosService $escPos;
    private Printer $printer;
    private array $settings;
    private string $timezone;
    private bool $roundAmounts = false;

    public function __construct(Printer $printer)
    {
        $this->printer = $printer;
        $this->escPos = new EscPosService(
            $printer->chars_per_line,
            $printer->encoding
        );
        $this->loadSettings($printer->restaurant_id);
        $this->loadTimezone($printer->restaurant_id);
    }

    /**
     * Загрузить таймзону ресторана
     */
    private function loadTimezone(int $restaurantId): void
    {
        $cacheKey = "general_settings_{$restaurantId}";
        $generalSettings = Cache::get($cacheKey, []);
        $this->timezone = $generalSettings['timezone'] ?? 'Europe/Moscow';
        $this->roundAmounts = $generalSettings['round_amounts'] ?? false;
    }

    /**
     * Округлить сумму согласно настройкам
     */
    private function roundAmount(float $amount): float
    {
        return $this->roundAmounts ? floor($amount) : round($amount, 2);
    }

    /**
     * Форматировать деньги с учётом настроек округления
     */
    private function formatMoney(float $amount): string
    {
        return EscPosService::formatMoney($this->roundAmount($amount), $this->roundAmounts);
    }

    /**
     * Получить текущее время в таймзоне ресторана
     */
    private function now(): Carbon
    {
        return Carbon::now($this->timezone);
    }

    /**
     * Загрузить настройки печати
     */
    private function loadSettings(int $restaurantId): void
    {
        $cacheKey = "print_settings_{$restaurantId}";

        $defaults = [
            // Шапка чека
            'receipt_header_name' => '',
            'receipt_header_address' => '',
            'receipt_header_phone' => '',
            'receipt_header_inn' => '',

            // QR
            'print_qr' => false,
            'qr_url' => '',
            'qr_text' => 'Сканируйте для отзыва',

            // Отображение на чеке гостя
            'show_waiter' => true,
            'show_table' => true,
            'show_guests_count' => false,
            'show_order_number' => true,
            'show_order_time' => true,
            'show_payment_method' => true,

            // Футер чека
            'receipt_footer_line1' => 'Спасибо за визит!',
            'receipt_footer_line2' => 'Ждем вас снова!',

            // Футер доставки
            'delivery_footer_line1' => 'Спасибо за заказ!',
            'delivery_footer_line2' => 'Приятного аппетита!',

            // Отображение на чеке доставки
            'delivery_show_customer' => true,
            'delivery_show_phone' => true,
            'delivery_show_address' => true,
            'delivery_show_entrance' => true,
            'delivery_show_intercom' => true,
            'delivery_show_courier' => true,
            'delivery_show_comment' => true,

            // Кухня
            'kitchen_beep' => true,
            'kitchen_large_font' => true,
            'kitchen_bold_items' => true,
            'kitchen_header_text' => 'НОВЫЙ ЗАКАЗ',
            'kitchen_show_table' => true,
            'kitchen_show_waiter' => true,
            'kitchen_show_order_number' => true,
            'kitchen_show_time' => true,
            'kitchen_show_order_type' => true,
            'kitchen_show_modifiers' => true,
            'kitchen_show_comments' => true,

            // Пречек
            'precheck_title' => 'ПРЕДВАРИТЕЛЬНЫЙ СЧЁТ',
            'precheck_subtitle' => '(не является фискальным документом)',
            'precheck_show_table' => true,
            'precheck_show_waiter' => true,
            'precheck_show_date' => true,
            'precheck_show_guests' => false,
            'precheck_footer' => 'Приятного аппетита!',
        ];

        $saved = Cache::get($cacheKey, []);
        $this->settings = array_merge($defaults, $saved);
    }

    /**
     * Получить значение из настроек с fallback
     */
    private function getSetting(string $key, $default = '')
    {
        return $this->settings[$key] ?? $default;
    }
    
    /**
     * Генерация чека для гостя
     */
    public function generateReceipt(Order $order): string
    {
        // Для доставки используем специальный формат
        if ($order->type === 'delivery') {
            return $this->generateDeliveryReceipt($order);
        }

        $e = $this->escPos;

        $e->initialize()
          ->setCharset();

        // Шапка (из настроек или из ресторана)
        $headerName = $this->getSetting('receipt_header_name') ?: ($order->restaurant?->name ?? 'РЕСТОРАН');
        $headerAddress = $this->getSetting('receipt_header_address') ?: ($order->restaurant?->address ?? '');
        $headerInn = $this->getSetting('receipt_header_inn') ?: ($order->restaurant?->inn ?? '');
        $headerPhone = $this->getSetting('receipt_header_phone') ?: ($order->restaurant?->phone ?? '');

        $e->titleLine($headerName);
        if ($headerAddress) $e->centerLine($headerAddress);
        if ($headerPhone) $e->centerLine('Тел: ' . $headerPhone);
        if ($headerInn) $e->centerLine('ИНН: ' . $headerInn);
        $e->feed(1)->separator();

        // Информация о заказе (из настроек)
        $e->centerLine('КАССОВЫЙ ЧЕК')
          ->feed(1);

        if ($this->getSetting('show_order_number', true)) {
            $e->twoColumns('Чек N:', $order->order_number);
        }
        if ($this->getSetting('show_order_time', true)) {
            $e->twoColumns('Дата:', $order->created_at->format('d.m.Y H:i'));
        }
        if ($this->getSetting('show_table', true)) {
            $e->twoColumns('Стол:', $order->table?->number ?? '-');
        }
        if ($this->getSetting('show_waiter', true)) {
            $e->twoColumns('Официант:', $order->waiter?->name ?? '-');
        }
        if ($this->getSetting('show_guests_count', false) && $order->guests_count) {
            $e->twoColumns('Гостей:', $order->guests_count);
        }
        if ($order->customer) {
            $e->twoColumns('Клиент:', $order->customer->name);
        }

        $e->separator();
        
        // Позиции
        $e->setBold(true)
          ->threeColumns('Кол', 'Наименование', 'Сумма')
          ->setBold(false)
          ->separator();
        
        foreach ($order->items as $item) {
            $qty = $item->quantity . 'x';
            $name = mb_substr($item->dish?->name ?? $item->name, 0, 30);
            $sum = EscPosService::formatMoney($item->total);
            
            $e->threeColumns($qty, $name, $sum);
            
            // Модификаторы
            if (!empty($item->modifiers)) {
                foreach ($item->modifiers as $mod) {
                    $e->text('    + ' . $mod['name'])->feed(1);
                }
            }
            
            // Комментарий
            if (!empty($item->comment)) {
                $e->text('    * ' . $item->comment)->feed(1);
            }
        }
        
        $e->separator();
        
        // Итоги
        $e->twoColumns('Подитог:', EscPosService::formatMoney($order->subtotal));
        
        if ($order->discount > 0) {
            $e->twoColumns('Скидка:', '-' . EscPosService::formatMoney($order->discount));
        }
        
        if ($order->bonus_used > 0) {
            $e->twoColumns('Бонусы:', '-' . EscPosService::formatMoney($order->bonus_used));
        }
        
        $e->doubleSeparator()
          ->setBold(true)
          ->setFontSize(EscPosService::FONT_DOUBLE_HEIGHT)
          ->twoColumns('ИТОГО:', EscPosService::formatMoney($order->total))
          ->setFontSize(EscPosService::FONT_NORMAL)
          ->setBold(false)
          ->separator();
        
        // Оплата (из настроек)
        if ($this->getSetting('show_payment_method', true)) {
            $paymentMethod = $order->payment_method === 'cash' ? 'Наличные' : 'Банковская карта';
            $e->twoColumns('Способ оплаты:', $paymentMethod);

            if ($order->payment_method === 'cash' && $order->cash_received) {
                $e->twoColumns('Получено:', EscPosService::formatMoney($order->cash_received));
                $change = $order->cash_received - $order->total;
                if ($change > 0) {
                    $e->twoColumns('Сдача:', EscPosService::formatMoney($change));
                }
            }
        }
        
        // Бонусы начислены
        if ($order->bonus_earned > 0) {
            $e->feed(1)
              ->centerLine('Начислено бонусов: ' . $order->bonus_earned);
        }
        
        $e->separator();

        // QR-код (из настроек)
        if ($this->getSetting('print_qr', false) || $this->printer->print_qr) {
            $e->feed(1)
              ->setAlign(EscPosService::ALIGN_CENTER)
              ->qrCode($this->generateQrData($order), 5)
              ->feed(1)
              ->line('Сканируйте для отзыва')
              ->setAlign(EscPosService::ALIGN_LEFT);
        }

        // Футер (из настроек)
        $footerLine1 = $this->getSetting('receipt_footer_line1', 'Спасибо за визит!');
        $footerLine2 = $this->getSetting('receipt_footer_line2', 'Ждем вас снова!');

        $e->feed(1);
        if ($footerLine1) $e->centerLine($footerLine1);
        if ($footerLine2) $e->centerLine($footerLine2);
        $e->feed(1)->centerLine($this->now()->format('d.m.Y H:i:s'));

        // Отрезка
        if ($this->printer->cut_paper) {
            $e->cut();
        }

        // Денежный ящик
        if ($this->printer->open_drawer && $order->payment_method === 'cash') {
            $e->openDrawer();
        }

        return $e->getBase64();
    }

    /**
     * Генерация чека для доставки
     */
    public function generateDeliveryReceipt(Order $order): string
    {
        $e = $this->escPos;

        $e->initialize()
          ->setCharset();

        // Шапка (из настроек или из ресторана)
        $headerName = $this->getSetting('receipt_header_name') ?: ($order->restaurant?->name ?? 'РЕСТОРАН');
        $headerAddress = $this->getSetting('receipt_header_address') ?: ($order->restaurant?->address ?? '');
        $headerInn = $this->getSetting('receipt_header_inn') ?: ($order->restaurant?->inn ?? '');
        $headerPhone = $this->getSetting('receipt_header_phone') ?: ($order->restaurant?->phone ?? '');

        $e->titleLine($headerName);
        if ($headerAddress) $e->centerLine($headerAddress);
        if ($headerPhone) $e->centerLine('Тел: ' . $headerPhone);
        if ($headerInn) $e->centerLine('ИНН: ' . $headerInn);
        $e->feed(1)->separator();

        // Тип заказа - ДОСТАВКА
        $e->setInverse(true)
          ->setFontSize(EscPosService::FONT_DOUBLE)
          ->centerLine(' ДОСТАВКА ')
          ->setFontSize(EscPosService::FONT_NORMAL)
          ->setInverse(false)
          ->feed(1);

        // Информация о заказе
        $e->twoColumns('Заказ N:', $order->order_number)
          ->twoColumns('Дата:', $order->created_at->format('d.m.Y H:i'));

        // Информация о клиенте (из настроек)
        if ($this->getSetting('delivery_show_customer', true) && $order->customer) {
            $e->separator()
              ->setBold(true)
              ->line('КЛИЕНТ:')
              ->setBold(false)
              ->line($order->customer->name);

            if ($this->getSetting('delivery_show_phone', true) && $order->customer->phone) {
                $e->line('Тел: ' . $order->customer->phone);
            }
        }

        // Адрес доставки (из настроек)
        if ($this->getSetting('delivery_show_address', true) && $order->delivery_address) {
            $e->feed(1)
              ->setBold(true)
              ->line('АДРЕС ДОСТАВКИ:')
              ->setBold(false)
              ->line($order->delivery_address);

            if ($this->getSetting('delivery_show_entrance', true) && $order->delivery_entrance) {
                $e->line('Подъезд: ' . $order->delivery_entrance);
            }
            if ($order->delivery_floor) {
                $e->line('Этаж: ' . $order->delivery_floor);
            }
            if ($order->delivery_apartment) {
                $e->line('Кв./Офис: ' . $order->delivery_apartment);
            }
            if ($this->getSetting('delivery_show_intercom', true) && $order->delivery_intercom) {
                $e->line('Домофон: ' . $order->delivery_intercom);
            }
        }

        // Комментарий к заказу (из настроек)
        if ($this->getSetting('delivery_show_comment', true) && $order->comment) {
            $e->feed(1)
              ->setInverse(true)
              ->line(' КОММЕНТАРИЙ: ')
              ->setInverse(false)
              ->line($order->comment);
        }

        // Курьер (из настроек)
        if ($this->getSetting('delivery_show_courier', true) && $order->courier) {
            $e->feed(1)
              ->twoColumns('Курьер:', $order->courier->name);
        }

        $e->separator();

        // Позиции
        $e->setBold(true)
          ->threeColumns('Кол', 'Наименование', 'Сумма')
          ->setBold(false)
          ->separator();

        foreach ($order->items as $item) {
            $qty = $item->quantity . 'x';
            $name = mb_substr($item->dish?->name ?? $item->name, 0, 30);
            $sum = EscPosService::formatMoney($item->total);

            $e->threeColumns($qty, $name, $sum);

            // Модификаторы
            if (!empty($item->modifiers)) {
                foreach ($item->modifiers as $mod) {
                    $e->text('    + ' . $mod['name'])->feed(1);
                }
            }

            // Комментарий
            if (!empty($item->comment)) {
                $e->text('    * ' . $item->comment)->feed(1);
            }
        }

        $e->separator();

        // Итоги
        $e->twoColumns('Подитог:', EscPosService::formatMoney($order->subtotal));

        if ($order->discount > 0) {
            $e->twoColumns('Скидка:', '-' . EscPosService::formatMoney($order->discount));
        }

        if ($order->bonus_used > 0) {
            $e->twoColumns('Бонусы:', '-' . EscPosService::formatMoney($order->bonus_used));
        }

        // Стоимость доставки
        if ($order->delivery_fee > 0) {
            $e->twoColumns('Доставка:', EscPosService::formatMoney($order->delivery_fee));
        } elseif ($order->delivery_fee == 0) {
            $e->twoColumns('Доставка:', 'БЕСПЛАТНО');
        }

        $e->doubleSeparator()
          ->setBold(true)
          ->setFontSize(EscPosService::FONT_DOUBLE_HEIGHT)
          ->twoColumns('ИТОГО:', EscPosService::formatMoney($order->total))
          ->setFontSize(EscPosService::FONT_NORMAL)
          ->setBold(false)
          ->separator();

        // Оплата
        $paymentMethod = match($order->payment_method) {
            'cash' => 'Наличные',
            'card' => 'Карта',
            'online' => 'Онлайн',
            default => $order->payment_method ?? 'Не указан',
        };
        $e->twoColumns('Оплата:', $paymentMethod);

        if ($order->payment_method === 'cash' && $order->cash_received) {
            $e->twoColumns('Получено:', EscPosService::formatMoney($order->cash_received));
            $change = $order->cash_received - $order->total;
            if ($change > 0) {
                $e->twoColumns('Сдача:', EscPosService::formatMoney($change));
            }
        }

        // Бонусы начислены
        if ($order->bonus_earned > 0) {
            $e->feed(1)
              ->centerLine('Начислено бонусов: ' . $order->bonus_earned);
        }

        $e->separator();

        // QR-код (из настроек)
        if ($this->getSetting('print_qr', false) || $this->printer->print_qr) {
            $e->feed(1)
              ->setAlign(EscPosService::ALIGN_CENTER)
              ->qrCode($this->generateQrData($order), 5)
              ->feed(1)
              ->line('Сканируйте для отзыва')
              ->setAlign(EscPosService::ALIGN_LEFT);
        }

        // Футер (из настроек для доставки)
        $footerLine1 = $this->getSetting('delivery_footer_line1', 'Спасибо за заказ!');
        $footerLine2 = $this->getSetting('delivery_footer_line2', 'Приятного аппетита!');

        $e->feed(1);
        if ($footerLine1) $e->centerLine($footerLine1);
        if ($footerLine2) $e->centerLine($footerLine2);
        $e->feed(1)->centerLine($this->now()->format('d.m.Y H:i:s'));

        // Отрезка
        if ($this->printer->cut_paper) {
            $e->cut();
        }

        return $e->getBase64();
    }

    /**
     * Генерация пречека (счёт для гостя)
     * @param Order $order
     * @param int|null $forGuestNumber Номер гостя для отдельного счёта (null = общий счёт)
     */
    public function generatePrecheck(Order $order, ?int $forGuestNumber = null): string
    {
        $e = $this->escPos;

        $e->initialize()
          ->setCharset();

        // Шапка (из настроек)
        $title = $this->getSetting('precheck_title', 'ПРЕДВАРИТЕЛЬНЫЙ СЧЁТ');
        $subtitle = $this->getSetting('precheck_subtitle', '(не является фискальным документом)');

        // Если печатаем для конкретного гостя - добавляем номер в заголовок
        if ($forGuestNumber !== null) {
            $title = "СЧЁТ - ГОСТЬ $forGuestNumber";
        }

        $e->titleLine($title)
          ->feed(1)
          ->centerLine($subtitle)
          ->separator();

        // Информация (из настроек)
        if ($this->getSetting('precheck_show_table', true)) {
            $e->twoColumns('Стол №:', $order->table?->number ?? '-');
        }
        if ($this->getSetting('precheck_show_date', true)) {
            $e->twoColumns('Дата:', $this->now()->format('d.m.Y H:i'));
        }
        if ($this->getSetting('precheck_show_waiter', true)) {
            $e->twoColumns('Официант:', $order->waiter?->name ?? '-');
        }
        if ($this->getSetting('precheck_show_guests', false) && $order->guests_count && $forGuestNumber === null) {
            $e->twoColumns('Гостей:', $order->guests_count);
        }
        $e->separator();

        // Подсчёт скидок (общих по заказу для расчёта пропорции)
        $orderSubtotal = $order->items->sum('total');
        $discountAmount = $order->discount_amount ?? 0;
        $loyaltyDiscount = $order->loyalty_discount_amount ?? 0;
        $totalDiscount = $discountAmount + $loyaltyDiscount;

        // Процент скидки для пропорционального распределения
        $discountPercent = $orderSubtotal > 0 ? $totalDiscount / $orderSubtotal : 0;

        // Если печатаем для конкретного гостя - фильтруем позиции
        if ($forGuestNumber !== null) {
            $guestItems = $order->items->filter(fn($item) => ($item->guest_number ?? 1) === $forGuestNumber);

            // Заголовок таблицы
            $e->setBold(true)
              ->threeColumns('Кол', 'Наименование', 'Сумма')
              ->setBold(false)
              ->separator();

            // Позиции гостя
            $guestSubtotal = 0;
            foreach ($guestItems as $item) {
                $qty = $item->quantity . 'x';
                $name = mb_substr($item->dish?->name ?? $item->name, 0, 30);
                $sum = $this->formatMoney($item->total);
                $e->threeColumns($qty, $name, $sum);
                $guestSubtotal += $item->total;
            }

            $e->doubleSeparator();

            // Скидка гостя (пропорциональная) - используем round() как в JS
            if ($totalDiscount > 0) {
                $guestDiscount = round($totalDiscount * ($guestSubtotal / $orderSubtotal));
                $guestTotal = $this->roundAmount($guestSubtotal - $guestDiscount);

                $e->twoColumns('Подитог:', $this->formatMoney($guestSubtotal));
                $e->twoColumns('Скидка:', '-' . $this->formatMoney($guestDiscount));
                $e->separator();

                $e->setBold(true)
                  ->setFontSize(EscPosService::FONT_DOUBLE_HEIGHT)
                  ->twoColumns('К ОПЛАТЕ:', $this->formatMoney($guestTotal))
                  ->setFontSize(EscPosService::FONT_NORMAL)
                  ->setBold(false);
            } else {
                $e->setBold(true)
                  ->setFontSize(EscPosService::FONT_DOUBLE_HEIGHT)
                  ->twoColumns('К ОПЛАТЕ:', $this->formatMoney($guestSubtotal))
                  ->setFontSize(EscPosService::FONT_NORMAL)
                  ->setBold(false);
            }
        } else {
            // Общий счёт - группируем позиции по гостям
            $itemsByGuest = [];
            foreach ($order->items as $item) {
                $guestNumber = $item->guest_number ?? 1;
                if (!isset($itemsByGuest[$guestNumber])) {
                    $itemsByGuest[$guestNumber] = [];
                }
                $itemsByGuest[$guestNumber][] = $item;
            }

            // Сортируем по номеру гостя
            ksort($itemsByGuest);

            // Определяем, нужна ли группировка (больше одного гостя)
            $multipleGuests = count($itemsByGuest) > 1;

            // Печатаем позиции по гостям
            foreach ($itemsByGuest as $guestNumber => $items) {
                if ($multipleGuests) {
                    // Заголовок гостя
                    $e->feed(1)
                      ->setBold(true)
                      ->centerLine("--- Гость $guestNumber ---")
                      ->setBold(false)
                      ->feed(1);
                }

                // Заголовок таблицы
                $e->setBold(true)
                  ->threeColumns('Кол', 'Наименование', 'Сумма')
                  ->setBold(false)
                  ->separator();

                // Позиции гостя
                $guestSubtotal = 0;
                foreach ($items as $item) {
                    $qty = $item->quantity . 'x';
                    $name = mb_substr($item->dish?->name ?? $item->name, 0, 30);
                    $sum = $this->formatMoney($item->total);
                    $e->threeColumns($qty, $name, $sum);
                    $guestSubtotal += $item->total;
                }

                // Итого по гостю (если несколько гостей)
                if ($multipleGuests) {
                    $e->separator();

                    // Если есть скидка - показываем подитог, скидку и итого
                    // Используем round() как в JS для согласованности с терминалом
                    if ($totalDiscount > 0) {
                        $guestDiscount = round($totalDiscount * ($guestSubtotal / $orderSubtotal));
                        $guestTotal = $this->roundAmount($guestSubtotal - $guestDiscount);

                        $e->twoColumns('Подитог:', $this->formatMoney($guestSubtotal));
                        $e->twoColumns('Скидка:', '-' . $this->formatMoney($guestDiscount));
                        $e->setBold(true)
                          ->twoColumns("К оплате Гость $guestNumber:", $this->formatMoney($guestTotal))
                          ->setBold(false);
                    } else {
                        $e->setBold(true)
                          ->twoColumns("Итого Гость $guestNumber:", $this->formatMoney($guestSubtotal))
                          ->setBold(false);
                    }
                }
            }

            $e->doubleSeparator();

            // Общий подитог и скидка (если есть)
            if ($totalDiscount > 0) {
                $e->twoColumns('Подитог:', $this->formatMoney($orderSubtotal));

                if ($discountAmount > 0) {
                    $discountLabel = $order->discount_reason ? "Скидка ({$order->discount_reason}):" : 'Скидка:';
                    $e->twoColumns($discountLabel, '-' . $this->formatMoney($discountAmount));
                }

                if ($loyaltyDiscount > 0) {
                    $e->twoColumns('Скидка по карте:', '-' . $this->formatMoney($loyaltyDiscount));
                }

                $e->separator();
            }

            // Итого к оплате - берём из заказа (уже округлена при сохранении)
            $e->setBold(true)
              ->setFontSize(EscPosService::FONT_DOUBLE_HEIGHT)
              ->twoColumns('К ОПЛАТЕ:', $this->formatMoney($order->total))
              ->setFontSize(EscPosService::FONT_NORMAL)
              ->setBold(false);
        }

        // Футер (из настроек)
        $footer = $this->getSetting('precheck_footer', 'Приятного аппетита!');
        $e->feed(2)
          ->centerLine($footer);

        if ($this->printer->cut_paper) {
            $e->cut();
        }

        return $e->getBase64();
    }
    
    /**
     * Генерация заказа для кухни
     */
    public function generateKitchenOrder(Order $order, array $items = null): string
    {
        $e = $this->escPos;
        $items = $items ?? $order->items;

        $useBeep = $this->getSetting('kitchen_beep', true);
        $useLargeFont = $this->getSetting('kitchen_large_font', true);
        $headerText = $this->getSetting('kitchen_header_text', 'НОВЫЙ ЗАКАЗ');

        $e->initialize()
          ->setCharset();

        // Звуковой сигнал (если включён в настройках)
        if ($useBeep) {
            $e->beep(2);
        }

        // Заголовок (из настроек)
        $e->setAlign(EscPosService::ALIGN_CENTER)
          ->setInverse(true)
          ->setFontSize($useLargeFont ? EscPosService::FONT_DOUBLE : EscPosService::FONT_NORMAL)
          ->line(' ' . $headerText . ' ')
          ->setInverse(false)
          ->setFontSize(EscPosService::FONT_NORMAL)
          ->setAlign(EscPosService::ALIGN_LEFT)
          ->feed(1);

        // Информация (из настроек)
        if ($this->getSetting('kitchen_show_table', true)) {
            $e->setBold(true)
              ->setFontSize($useLargeFont ? EscPosService::FONT_DOUBLE : EscPosService::FONT_NORMAL)
              ->line('СТОЛ: ' . ($order->table?->number ?? '-'))
              ->setFontSize(EscPosService::FONT_NORMAL)
              ->setBold(false);
        }

        if ($this->getSetting('kitchen_show_order_number', true)) {
            $e->twoColumns('Заказ №:', $order->order_number);
        }
        if ($this->getSetting('kitchen_show_time', true)) {
            $e->twoColumns('Время:', $order->created_at->format('H:i'));
        }
        if ($this->getSetting('kitchen_show_waiter', true)) {
            $e->twoColumns('Официант:', $order->waiter?->name ?? '-');
        }

        // Тип заказа (из настроек)
        if ($this->getSetting('kitchen_show_order_type', true)) {
            $type = match($order->type) {
                'dine_in' => 'В зале',
                'takeaway' => 'С собой',
                'delivery' => 'ДОСТАВКА',
                default => $order->type,
            };

            if ($order->type !== 'dine_in') {
                $e->setInverse(true)
                  ->line(' ' . $type . ' ')
                  ->setInverse(false);
            }
        }

        $e->doubleSeparator();

        // Позиции (крупным шрифтом если включено в настройках)
        $useBoldItems = $this->getSetting('kitchen_bold_items', true);
        $showModifiers = $this->getSetting('kitchen_show_modifiers', true);
        $showComments = $this->getSetting('kitchen_show_comments', true);

        foreach ($items as $item) {
            if ($useLargeFont) {
                $e->setFontSize(EscPosService::FONT_DOUBLE_HEIGHT);
            }
            if ($useBoldItems) {
                $e->setBold(true);
            }
            $e->line($item->quantity . 'x ' . ($item->dish?->name ?? $item->name));
            if ($useBoldItems) {
                $e->setBold(false);
            }
            $e->setFontSize(EscPosService::FONT_NORMAL);

            // Модификаторы (из настроек)
            if ($showModifiers && !empty($item->modifiers)) {
                foreach ($item->modifiers as $mod) {
                    $e->line('   + ' . $mod['name']);
                }
            }
            
            // Комментарий (из настроек)
            if ($showComments && !empty($item->comment)) {
                $e->setInverse(true)
                  ->line(' ! ' . $item->comment . ' ')
                  ->setInverse(false);
            }
            
            $e->feed(1);
        }
        
        $e->doubleSeparator()
          ->centerLine('>>> ПРИНЯТ В ' . $this->now()->format('H:i') . ' <<<')
          ->feed(1);
        
        if ($this->printer->cut_paper) {
            $e->cut();
        }
        
        return $e->getBase64();
    }

    /**
     * Генерация предзаказа для кухни (из бронирования)
     */
    public function generatePreorderKitchen(\App\Models\Reservation $reservation, array $items): string
    {
        $e = $this->escPos;

        $useBeep = $this->getSetting('kitchen_beep', true);
        $useLargeFont = $this->getSetting('kitchen_large_font', true);

        $e->initialize()
          ->setCharset();

        // Звуковой сигнал
        if ($useBeep) {
            $e->beep(2);
        }

        // Заголовок - ПРЕДЗАКАЗ
        $e->setAlign(EscPosService::ALIGN_CENTER)
          ->setInverse(true)
          ->setFontSize($useLargeFont ? EscPosService::FONT_DOUBLE : EscPosService::FONT_NORMAL)
          ->line(' ПРЕДЗАКАЗ ')
          ->setInverse(false)
          ->setFontSize(EscPosService::FONT_NORMAL)
          ->setAlign(EscPosService::ALIGN_LEFT)
          ->feed(1);

        // Информация о брони
        $e->setBold(true)
          ->setFontSize($useLargeFont ? EscPosService::FONT_DOUBLE : EscPosService::FONT_NORMAL)
          ->line('СТОЛ: ' . ($reservation->table?->number ?? $reservation->table?->name ?? '-'))
          ->setFontSize(EscPosService::FONT_NORMAL)
          ->setBold(false);

        // Дата и время брони
        $e->twoColumns('Дата:', date('d.m.Y', strtotime($reservation->date)));
        $e->twoColumns('Время:', substr($reservation->time_from, 0, 5) . ' - ' . substr($reservation->time_to, 0, 5));

        // Гость
        if ($reservation->guest_name) {
            $e->twoColumns('Гость:', $reservation->guest_name);
        }

        // Количество гостей
        if ($reservation->guests_count) {
            $e->twoColumns('Кол-во гостей:', $reservation->guests_count);
        }

        $e->doubleSeparator();

        // Позиции
        $useBoldItems = $this->getSetting('kitchen_bold_items', true);
        $showComments = $this->getSetting('kitchen_show_comments', true);

        foreach ($items as $item) {
            if ($useLargeFont) {
                $e->setFontSize(EscPosService::FONT_DOUBLE_HEIGHT);
            }
            if ($useBoldItems) {
                $e->setBold(true);
            }

            $name = is_object($item) ? ($item->dish?->name ?? $item->name ?? 'Позиция') : ($item['name'] ?? 'Позиция');
            $qty = is_object($item) ? $item->quantity : ($item['quantity'] ?? 1);
            $e->line($qty . 'x ' . $name);

            if ($useBoldItems) {
                $e->setBold(false);
            }
            $e->setFontSize(EscPosService::FONT_NORMAL);

            // Комментарий
            $comment = is_object($item) ? $item->comment : ($item['comment'] ?? null);
            if ($showComments && !empty($comment)) {
                $e->setInverse(true)
                  ->line(' ! ' . $comment . ' ')
                  ->setInverse(false);
            }

            $e->feed(1);
        }

        $e->doubleSeparator()
          ->centerLine('>>> ПРИГОТОВИТЬ К ' . substr($reservation->time_from, 0, 5) . ' <<<')
          ->feed(1);

        if ($this->printer->cut_paper) {
            $e->cut();
        }

        return $e->getBase64();
    }

    /**
     * Генерация отчёта X или Z
     */
    public function generateReport(array $data, string $type = 'X'): string
    {
        $e = $this->escPos;
        
        $e->initialize()
          ->setCharset();
        
        // Заголовок
        $e->titleLine($type . '-ОТЧЁТ')
          ->feed(1)
          ->twoColumns('Дата:', $this->now()->format('d.m.Y'))
          ->twoColumns('Время:', $this->now()->format('H:i:s'))
          ->twoColumns('Смена:', $data['shift_number'] ?? '1')
          ->separator();
        
        // Статистика
        $e->twoColumns('Заказов:', $data['orders_count'] ?? 0)
          ->twoColumns('Гостей:', $data['guests_count'] ?? 0)
          ->twoColumns('Средний чек:', EscPosService::formatMoney($data['avg_check'] ?? 0))
          ->separator();
        
        // Выручка
        $e->setBold(true)
          ->line('ВЫРУЧКА')
          ->setBold(false)
          ->twoColumns('Наличные:', EscPosService::formatMoney($data['cash'] ?? 0))
          ->twoColumns('Карты:', EscPosService::formatMoney($data['card'] ?? 0))
          ->doubleSeparator()
          ->setBold(true)
          ->setFontSize(EscPosService::FONT_DOUBLE_HEIGHT)
          ->twoColumns('ИТОГО:', EscPosService::formatMoney($data['total'] ?? 0))
          ->setFontSize(EscPosService::FONT_NORMAL)
          ->setBold(false);
        
        if ($this->printer->cut_paper) {
            $e->cut();
        }
        
        return $e->getBase64();
    }
    
    /**
     * Генерация данных для QR
     */
    private function generateQrData(Order $order): string
    {
        // Ссылка на онлайн-чек или страницу отзыва
        $baseUrl = config('app.url', 'http://localhost:8000');
        return $baseUrl . '/review/' . $order->order_number;
    }
    
    /**
     * Тестовая печать
     */
    public function generateTestPage(): string
    {
        $e = $this->escPos;
        
        $e->initialize()
          ->setCharset()
          ->titleLine('ТЕСТ ПРИНТЕРА')
          ->feed(1)
          ->centerLine('Принтер: ' . $this->printer->name)
          ->centerLine('IP: ' . $this->printer->ip_address)
          ->separator()
          ->line('Обычный текст')
          ->setBold(true)->line('Жирный текст')->setBold(false)
          ->setUnderline(true)->line('Подчёркнутый')->setUnderline(false)
          ->setFontSize(EscPosService::FONT_DOUBLE)->line('Крупный')->setFontSize(EscPosService::FONT_NORMAL)
          ->separator()
          ->line('Символов в строке: ' . $this->printer->chars_per_line)
          ->line('Кодировка: ' . $this->printer->encoding)
          ->separator()
          ->line('Кириллица: АБВГДЕЁЖЗ')
          ->line('Цифры: 0123456789')
          ->line('Символы: @#$%&*()_+-=')
          ->separator();
        
        if ($this->printer->print_qr) {
            $e->setAlign(EscPosService::ALIGN_CENTER)
              ->qrCode('https://poslab.test', 6)
              ->line('QR-код')
              ->setAlign(EscPosService::ALIGN_LEFT);
        }
        
        $e->feed(1)
          ->centerLine($this->now()->format('d.m.Y H:i:s'))
          ->centerLine('Тест завершён');
        
        if ($this->printer->cut_paper) {
            $e->cut();
        }
        
        return $e->getBase64();
    }
}

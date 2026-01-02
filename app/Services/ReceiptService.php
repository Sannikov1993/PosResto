<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Printer;

/**
 * Сервис генерации чеков
 */
class ReceiptService
{
    private EscPosService $escPos;
    private Printer $printer;
    
    public function __construct(Printer $printer)
    {
        $this->printer = $printer;
        $this->escPos = new EscPosService(
            $printer->chars_per_line,
            $printer->encoding
        );
    }
    
    /**
     * Генерация чека для гостя
     */
    public function generateReceipt(Order $order): string
    {
        $e = $this->escPos;
        
        $e->initialize()
          ->setCharset();
        
        // Шапка
        $e->titleLine($order->restaurant?->name ?? 'РЕСТОРАН')
          ->centerLine($order->restaurant?->address ?? '')
          ->centerLine('ИНН: ' . ($order->restaurant?->inn ?? '0000000000'))
          ->feed(1)
          ->separator();
        
        // Информация о заказе
        $e->centerLine('КАССОВЫЙ ЧЕК')
          ->feed(1)
          ->twoColumns('Чек №:', $order->order_number)
          ->twoColumns('Дата:', $order->created_at->format('d.m.Y H:i'))
          ->twoColumns('Стол:', $order->table?->number ?? '-')
          ->twoColumns('Официант:', $order->waiter?->name ?? '-');
        
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
        
        // Оплата
        $paymentMethod = $order->payment_method === 'cash' ? 'Наличные' : 'Банковская карта';
        $e->twoColumns('Способ оплаты:', $paymentMethod);
        
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
        
        // QR-код (для онлайн-чека)
        if ($this->printer->print_qr) {
            $e->feed(1)
              ->setAlign(EscPosService::ALIGN_CENTER)
              ->qrCode($this->generateQrData($order), 5)
              ->feed(1)
              ->line('Сканируйте для отзыва')
              ->setAlign(EscPosService::ALIGN_LEFT);
        }
        
        // Футер
        $e->feed(1)
          ->centerLine('Спасибо за визит!')
          ->centerLine('Ждём вас снова!')
          ->feed(1)
          ->centerLine(date('d.m.Y H:i:s'));
        
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
     * Генерация пречека (счёт для гостя)
     */
    public function generatePrecheck(Order $order): string
    {
        $e = $this->escPos;
        
        $e->initialize()
          ->setCharset();
        
        // Шапка
        $e->titleLine('ПРЕДВАРИТЕЛЬНЫЙ СЧЁТ')
          ->feed(1)
          ->centerLine('(не является фискальным документом)')
          ->separator();
        
        // Информация
        $e->twoColumns('Стол №:', $order->table?->number ?? '-')
          ->twoColumns('Дата:', $order->created_at->format('d.m.Y H:i'))
          ->twoColumns('Официант:', $order->waiter?->name ?? '-')
          ->separator();
        
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
        }
        
        $e->doubleSeparator()
          ->setBold(true)
          ->setFontSize(EscPosService::FONT_DOUBLE_HEIGHT)
          ->twoColumns('К ОПЛАТЕ:', EscPosService::formatMoney($order->total))
          ->setFontSize(EscPosService::FONT_NORMAL)
          ->setBold(false)
          ->feed(2)
          ->centerLine('Приятного аппетита!');
        
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
        
        $e->initialize()
          ->setCharset()
          ->beep(2); // Звуковой сигнал
        
        // Заголовок
        $e->setAlign(EscPosService::ALIGN_CENTER)
          ->setInverse(true)
          ->setFontSize(EscPosService::FONT_DOUBLE)
          ->line(' НОВЫЙ ЗАКАЗ ')
          ->setInverse(false)
          ->setFontSize(EscPosService::FONT_NORMAL)
          ->setAlign(EscPosService::ALIGN_LEFT)
          ->feed(1);
        
        // Информация
        $e->setBold(true)
          ->setFontSize(EscPosService::FONT_DOUBLE)
          ->line('СТОЛ: ' . ($order->table?->number ?? '-'))
          ->setFontSize(EscPosService::FONT_NORMAL)
          ->setBold(false);
        
        $e->twoColumns('Заказ №:', $order->order_number)
          ->twoColumns('Время:', $order->created_at->format('H:i'))
          ->twoColumns('Официант:', $order->waiter?->name ?? '-');
        
        // Тип заказа
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
        
        $e->doubleSeparator();
        
        // Позиции (крупным шрифтом)
        foreach ($items as $item) {
            $e->setFontSize(EscPosService::FONT_DOUBLE_HEIGHT)
              ->setBold(true)
              ->line($item->quantity . 'x ' . ($item->dish?->name ?? $item->name))
              ->setBold(false)
              ->setFontSize(EscPosService::FONT_NORMAL);
            
            // Модификаторы
            if (!empty($item->modifiers)) {
                foreach ($item->modifiers as $mod) {
                    $e->line('   + ' . $mod['name']);
                }
            }
            
            // Комментарий (выделяем)
            if (!empty($item->comment)) {
                $e->setInverse(true)
                  ->line(' ! ' . $item->comment . ' ')
                  ->setInverse(false);
            }
            
            $e->feed(1);
        }
        
        $e->doubleSeparator()
          ->centerLine('>>> ПРИНЯТ В ' . date('H:i') . ' <<<')
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
          ->twoColumns('Дата:', date('d.m.Y'))
          ->twoColumns('Время:', date('H:i:s'))
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
          ->centerLine(date('d.m.Y H:i:s'))
          ->centerLine('Тест завершён');
        
        if ($this->printer->cut_paper) {
            $e->cut();
        }
        
        return $e->getBase64();
    }
}

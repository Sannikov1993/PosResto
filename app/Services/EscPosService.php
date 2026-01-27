<?php

namespace App\Services;

/**
 * ESC/POS Service - генерация команд для термопринтеров
 * 
 * Поддерживаемые принтеры: Epson, Star, Citizen, Bixolon, и совместимые
 */
class EscPosService
{
    // ESC/POS константы
    const ESC = "\x1B";
    const GS = "\x1D";
    const FS = "\x1C";
    const LF = "\x0A";
    const CR = "\x0D";
    const HT = "\x09";
    const FF = "\x0C";
    
    // Выравнивание
    const ALIGN_LEFT = 0;
    const ALIGN_CENTER = 1;
    const ALIGN_RIGHT = 2;
    
    // Размер шрифта
    const FONT_NORMAL = 0;
    const FONT_DOUBLE_HEIGHT = 1;
    const FONT_DOUBLE_WIDTH = 2;
    const FONT_DOUBLE = 3;
    
    private string $buffer = '';
    private int $charsPerLine = 48;
    private string $encoding = 'cp866';
    
    public function __construct(int $charsPerLine = 48, string $encoding = 'cp866')
    {
        $this->charsPerLine = $charsPerLine;
        $this->encoding = $encoding;
    }
    
    /**
     * Инициализация принтера
     */
    public function initialize(): self
    {
        $this->buffer = self::ESC . '@'; // Reset
        return $this;
    }
    
    /**
     * Установить кодировку
     */
    public function setCharset(): self
    {
        // Выбор кодовой страницы для кириллицы
        // CP866 (Russian) = 17
        $this->buffer .= self::ESC . 't' . chr(17);
        return $this;
    }
    
    /**
     * Выравнивание текста
     */
    public function setAlign(int $align): self
    {
        $this->buffer .= self::ESC . 'a' . chr($align);
        return $this;
    }
    
    /**
     * Размер шрифта
     */
    public function setFontSize(int $size): self
    {
        $n = 0;
        switch ($size) {
            case self::FONT_DOUBLE_HEIGHT:
                $n = 0x01;
                break;
            case self::FONT_DOUBLE_WIDTH:
                $n = 0x10;
                break;
            case self::FONT_DOUBLE:
                $n = 0x11;
                break;
            default:
                $n = 0x00;
        }
        $this->buffer .= self::GS . '!' . chr($n);
        return $this;
    }
    
    /**
     * Жирный шрифт вкл/выкл
     */
    public function setBold(bool $on): self
    {
        $this->buffer .= self::ESC . 'E' . chr($on ? 1 : 0);
        return $this;
    }
    
    /**
     * Подчёркивание вкл/выкл
     */
    public function setUnderline(bool $on): self
    {
        $this->buffer .= self::ESC . '-' . chr($on ? 1 : 0);
        return $this;
    }
    
    /**
     * Инверсия (белый на чёрном)
     */
    public function setInverse(bool $on): self
    {
        $this->buffer .= self::GS . 'B' . chr($on ? 1 : 0);
        return $this;
    }
    
    /**
     * Печать текста
     */
    public function text(string $text): self
    {
        $converted = $this->convertEncoding($text);
        $this->buffer .= $converted;
        return $this;
    }
    
    /**
     * Печать строки с переводом строки
     */
    public function line(string $text = ''): self
    {
        return $this->text($text)->feed(1);
    }
    
    /**
     * Центрированный текст
     */
    public function centerLine(string $text): self
    {
        return $this->setAlign(self::ALIGN_CENTER)->line($text)->setAlign(self::ALIGN_LEFT);
    }
    
    /**
     * Жирная строка по центру
     */
    public function titleLine(string $text): self
    {
        return $this->setAlign(self::ALIGN_CENTER)
            ->setBold(true)
            ->setFontSize(self::FONT_DOUBLE)
            ->line($text)
            ->setFontSize(self::FONT_NORMAL)
            ->setBold(false)
            ->setAlign(self::ALIGN_LEFT);
    }
    
    /**
     * Две колонки (название слева, значение справа)
     */
    public function twoColumns(string $left, string $right): self
    {
        $leftLen = mb_strlen($left);
        $rightLen = mb_strlen($right);
        $space = $this->charsPerLine - $leftLen - $rightLen;
        
        if ($space < 1) {
            // Если не помещается, печатаем на двух строках
            return $this->line($left)->setAlign(self::ALIGN_RIGHT)->line($right)->setAlign(self::ALIGN_LEFT);
        }
        
        return $this->text($left . str_repeat(' ', $space) . $right)->feed(1);
    }
    
    /**
     * Три колонки (количество, название, сумма)
     */
    public function threeColumns(string $col1, string $col2, string $col3, int $col1Width = 4, int $col3Width = 10): self
    {
        $col2Width = $this->charsPerLine - $col1Width - $col3Width;

        // Обрезаем текст до нужной длины
        $col1 = mb_substr($col1, 0, $col1Width);
        $col2 = mb_substr($col2, 0, $col2Width);
        $col3 = mb_substr($col3, 0, $col3Width);

        // Добавляем пробелы с учётом мультибайтовой длины
        $col1 = $col1 . str_repeat(' ', $col1Width - mb_strlen($col1));
        $col2 = $col2 . str_repeat(' ', $col2Width - mb_strlen($col2));
        $col3 = str_repeat(' ', $col3Width - mb_strlen($col3)) . $col3;

        return $this->text($col1 . $col2 . $col3)->feed(1);
    }
    
    /**
     * Разделитель
     */
    public function separator(string $char = '-'): self
    {
        return $this->line(str_repeat($char, $this->charsPerLine));
    }
    
    /**
     * Двойной разделитель
     */
    public function doubleSeparator(): self
    {
        return $this->separator('=');
    }
    
    /**
     * Пустые строки
     */
    public function feed(int $lines = 1): self
    {
        $this->buffer .= str_repeat(self::LF, $lines);
        return $this;
    }
    
    /**
     * QR-код
     */
    public function qrCode(string $data, int $size = 6): self
    {
        // Размер модуля (1-16)
        $size = max(1, min(16, $size));
        
        // Модель QR
        $this->buffer .= self::GS . "(k" . chr(4) . chr(0) . "1A" . chr(50) . chr(0);
        
        // Размер модуля
        $this->buffer .= self::GS . "(k" . chr(3) . chr(0) . "1C" . chr($size);
        
        // Уровень коррекции (L=48, M=49, Q=50, H=51)
        $this->buffer .= self::GS . "(k" . chr(3) . chr(0) . "1E" . chr(49);
        
        // Данные
        $len = strlen($data) + 3;
        $pL = $len % 256;
        $pH = intval($len / 256);
        $this->buffer .= self::GS . "(k" . chr($pL) . chr($pH) . "1P0" . $data;
        
        // Печать
        $this->buffer .= self::GS . "(k" . chr(3) . chr(0) . "1Q0";
        
        return $this;
    }
    
    /**
     * Штрих-код
     */
    public function barcode(string $data, int $type = 4, int $height = 50): self
    {
        // Тип: 0=UPC-A, 1=UPC-E, 2=EAN13, 3=EAN8, 4=CODE39, 5=ITF, 6=CODABAR, 7=CODE93, 8=CODE128
        
        // Высота
        $this->buffer .= self::GS . 'h' . chr($height);
        
        // Позиция цифр (2 = снизу)
        $this->buffer .= self::GS . 'H' . chr(2);
        
        // Ширина
        $this->buffer .= self::GS . 'w' . chr(2);
        
        // Печать
        $this->buffer .= self::GS . 'k' . chr($type) . chr(strlen($data)) . $data;
        
        return $this;
    }
    
    /**
     * Отрезка бумаги
     */
    public function cut(bool $full = false): self
    {
        $this->feed(3);
        $this->buffer .= self::GS . 'V' . chr($full ? 0 : 66) . chr(0);
        return $this;
    }
    
    /**
     * Частичная отрезка
     */
    public function partialCut(): self
    {
        return $this->cut(false);
    }
    
    /**
     * Открыть денежный ящик
     */
    public function openDrawer(): self
    {
        // Пин 2, 100мс
        $this->buffer .= self::ESC . 'p' . chr(0) . chr(50) . chr(50);
        return $this;
    }
    
    /**
     * Звуковой сигнал
     */
    public function beep(int $times = 1): self
    {
        $this->buffer .= self::ESC . 'B' . chr($times) . chr(2);
        return $this;
    }
    
    /**
     * Получить буфер
     */
    public function getBuffer(): string
    {
        return $this->buffer;
    }
    
    /**
     * Получить буфер в base64
     */
    public function getBase64(): string
    {
        return base64_encode($this->buffer);
    }
    
    /**
     * Очистить буфер
     */
    public function clear(): self
    {
        $this->buffer = '';
        return $this;
    }
    
    /**
     * Конвертация в нужную кодировку
     */
    private function convertEncoding(string $text): string
    {
        if ($this->encoding === 'utf-8') {
            return $text;
        }
        
        return mb_convert_encoding($text, $this->encoding, 'UTF-8');
    }
    
    /**
     * Форматирование денег
     * Используем "р." вместо ₽ для совместимости с CP866
     */
    public static function formatMoney($amount, bool $hideDecimals = false): string
    {
        if ($hideDecimals) {
            return number_format($amount, 0, '.', ' ') . ' р.';
        }
        return number_format($amount, 2, '.', ' ') . ' р.';
    }
}

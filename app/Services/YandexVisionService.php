<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Ingredient;

class YandexVisionService
{
    protected string $apiKey;
    protected string $folderId;
    protected string $endpoint = 'https://vision.api.cloud.yandex.net/vision/v1/batchAnalyze';

    public function __construct()
    {
        $this->apiKey = config('services.yandex.vision_api_key', '');
        $this->folderId = config('services.yandex.folder_id', '');
    }

    /**
     * Распознать текст на изображении накладной
     */
    public function recognizeInvoice(string $imageBase64): array
    {
        try {
            // 1. Распознаём текст через Yandex Vision OCR
            $ocrResult = $this->performOcr($imageBase64);

            if (!$ocrResult['success']) {
                return $ocrResult;
            }

            $text = $ocrResult['text'];

            Log::info('OCR recognized text', ['text' => mb_substr($text, 0, 500)]);

            // 2. Парсим текст и извлекаем позиции
            $items = $this->parseInvoiceText($text);

            // 3. Сопоставляем с ингредиентами в базе
            $matchedItems = $this->matchWithIngredients($items);

            return [
                'success' => true,
                'raw_text' => $text,
                'items' => $matchedItems,
                'items_count' => count($matchedItems)
            ];

        } catch (\Exception $e) {
            Log::error('YandexVision error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Ошибка распознавания: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Выполнить OCR через Yandex Vision API
     */
    protected function performOcr(string $imageBase64): array
    {
        if (empty($this->apiKey) || empty($this->folderId)) {
            return [
                'success' => false,
                'message' => 'Yandex Vision API не настроен. Укажите YANDEX_VISION_API_KEY и YANDEX_FOLDER_ID в .env'
            ];
        }

        // Убираем префикс data:image если есть
        if (str_contains($imageBase64, ',')) {
            $imageBase64 = explode(',', $imageBase64)[1];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Api-Key ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($this->endpoint, [
            'folderId' => $this->folderId,
            'analyze_specs' => [
                [
                    'content' => $imageBase64,
                    'features' => [
                        [
                            'type' => 'TEXT_DETECTION',
                            'text_detection_config' => [
                                'language_codes' => ['ru', 'en']
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        if (!$response->successful()) {
            Log::error('Yandex Vision API error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return [
                'success' => false,
                'message' => 'Ошибка API: ' . $response->status()
            ];
        }

        $data = $response->json();

        // Извлекаем текст из ответа
        $text = $this->extractTextFromResponse($data);

        return [
            'success' => true,
            'text' => $text
        ];
    }

    /**
     * Извлечь текст из ответа Yandex Vision
     */
    protected function extractTextFromResponse(array $data): string
    {
        $text = '';

        $results = $data['results'] ?? [];
        foreach ($results as $result) {
            $textDetection = $result['results'][0]['textDetection'] ?? null;
            if (!$textDetection) continue;

            foreach ($textDetection['pages'] ?? [] as $page) {
                foreach ($page['blocks'] ?? [] as $block) {
                    foreach ($block['lines'] ?? [] as $line) {
                        $lineText = '';
                        foreach ($line['words'] ?? [] as $word) {
                            $lineText .= ($word['text'] ?? '') . ' ';
                        }
                        $text .= trim($lineText) . "\n";
                    }
                }
            }
        }

        return trim($text);
    }

    /**
     * Парсить текст накладной и извлечь позиции
     */
    protected function parseInvoiceText(string $text): array
    {
        $items = [];
        $lines = explode("\n", $text);

        // Ищем только строки которые ПОХОЖИ на товарные позиции
        // Товарная позиция обычно содержит: название + число + (единица измерения) + цена
        // Формат: "Сыр Моцарелла 2 кг 450.00" или "1. Мука пшеничная 25 кг х 45 = 1125"

        foreach ($lines as $line) {
            $line = trim($line);

            // Базовые проверки
            if (empty($line) || mb_strlen($line) < 8) continue;

            // Строка должна содержать хотя бы 1 число (количество или цена)
            preg_match_all('/\d+[\.,]?\d*/', $line, $numberMatches);
            if (count($numberMatches[0]) < 1) continue;

            // Строка должна содержать русское слово минимум 4 буквы (название товара)
            if (!preg_match('/[а-яё]{4,}/ui', $line)) continue;

            // Пропускаем служебные строки
            if ($this->isServiceLine($line)) continue;

            // Пробуем распарсить как товар
            $item = $this->parseItemLine($line);

            if ($item && !empty($item['name']) && mb_strlen($item['name']) >= 4) {
                // Дополнительная проверка: название должно быть похоже на товар
                if ($this->looksLikeProductName($item['name'])) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * Проверить, похоже ли название на товар
     */
    protected function looksLikeProductName(string $name): bool
    {
        $lower = mb_strtolower($name);

        // Чёрный список - точно НЕ товары
        $blacklist = [
            'итого', 'всего', 'сумма', 'ндс', 'налог', 'без налога',
            'наименование', 'количество', 'цена', 'единица', 'ставка',
            'документ', 'накладная', 'счет', 'фактура', 'упд',
            'продавец', 'покупатель', 'поставщик', 'получатель',
            'адрес', 'телефон', 'инн', 'кпп', 'огрн',
            'подпись', 'печать', 'директор', 'бухгалтер',
            'российский рубль', 'валюта', 'код валюты',
            'дата', 'номер', 'основание', 'договор',
            'грузоотправитель', 'грузополучатель',
            'страна происхождения', 'таможенная декларация',
            'товар работы услуги', 'имущественные права',
            'универсальный', 'передаточный',
        ];

        foreach ($blacklist as $bad) {
            if (str_contains($lower, $bad)) {
                return false;
            }
        }

        // Название слишком короткое
        $cleanName = preg_replace('/[^а-яёa-z]/ui', '', $name);
        if (mb_strlen($cleanName) < 5) {
            return false;
        }

        // Должно содержать хотя бы одно слово из 4+ букв
        if (!preg_match('/[а-яё]{4,}/ui', $name)) {
            return false;
        }

        return true;
    }

    /**
     * Проверить, является ли строка служебной (заголовки, итоги и т.д.)
     */
    protected function isServiceLine(string $line): bool
    {
        $lower = mb_strtolower(trim($line));
        $original = trim($line);

        // Пустая или слишком короткая строка
        if (empty($original) || mb_strlen($original) < 4) {
            return true;
        }

        // Служебные слова и фразы документа
        $serviceWords = [
            'итого', 'всего', 'сумма', 'ндс', 'налог', 'скидка', 'без ндс',
            'накладная', 'товарная', 'счёт', 'счет', 'фактура', 'упд',
            'универсальный', 'передаточный',
            'поставщик', 'продавец', 'покупатель', 'грузополучатель', 'плательщик', 'получатель',
            'адрес', 'телефон', 'инн', 'кпп', 'огрн', 'окпо', 'октмо',
            'дата', 'номер', '№', 'от ', 'договор', 'основание', 'приложение',
            'наименование', 'количество', 'цена', 'единица', 'ед.изм', 'стоимость',
            'подпись', 'печать', 'директор', 'бухгалтер', 'кладовщик', 'менеджер',
            'принял', 'сдал', 'отпустил', 'получил', 'подпись',
            'банк', 'р/с', 'к/с', 'бик', 'расчетный', 'корреспондентский',
            'российский рубль', 'валюта', 'курс', 'руб', 'код валюты',
            'документ', 'организация', 'контрагент', 'исправление',
            'склад', 'ответственный', 'комментарий', 'примечание',
            'проведен', 'оплачено', 'отгружено', 'статус',
            'постановление', 'правительство', 'федерация', 'российской',
            'пермский', 'край', 'город', 'область', 'улица', 'дом', 'квартира',
            'грузоотправитель', 'грузополучатель', 'консигнатор',
            'товар', 'работы', 'услуги', 'имущественные права',
            'страна', 'происхождения', 'регистрационный', 'таможенная', 'декларация',
        ];

        foreach ($serviceWords as $word) {
            if (str_contains($lower, $word)) {
                return true;
            }
        }

        // Строка только из цифр, знаков препинания и разделителей
        if (preg_match('/^[\d\s\.\,\-\+\=\(\)\/\:\;\|\_\%\*]+$/', $original)) {
            return true;
        }

        // Коды 1С и подобные: "УТ-", "УТ-00001234", "ДО-", артикулы
        // Более широкий паттерн для кодов
        if (preg_match('/^[А-ЯA-Z]{1,4}[\-\s]?\d*$/ui', $original)) {
            return true;
        }

        // Артикулы в различных форматах: "00000012345", "А-123", etc.
        if (preg_match('/^[\d]{6,}$/', $original)) {
            return true;
        }

        // Строки типа "1 —", "2 —" (нумерация в документах)
        if (preg_match('/^\d+\s*[—\-–]\s*/', $original)) {
            return true;
        }

        // Слишком короткое название (менее 3 русских букв подряд)
        if (!preg_match('/[а-яё]{3,}/ui', $original)) {
            return true;
        }

        // Строки вида "ИП Фамилия Имя Отчество" - это реквизиты
        if (preg_match('/^ип\s+[а-яё]+\s+[а-яё]+/ui', $lower)) {
            return true;
        }

        // Числовые индексы, коды, идентификаторы
        if (preg_match('/^\d{3,}[\s\,\.]/', $original)) {
            return true;
        }

        return false;
    }

    /**
     * Парсить строку с товаром
     */
    protected function parseItemLine(string $line): ?array
    {
        $originalLine = $line;

        // Убираем номер позиции в начале
        $line = preg_replace('/^\d+[\.\)\s]+/', '', $line);

        // Убираем коды типа "УТ-00001234" в начале или конце
        $line = preg_replace('/\b[А-ЯA-Z]{1,3}[\-]?\d{4,}\b/u', '', $line);

        // Убираем артикулы в скобках
        $line = preg_replace('/\([А-Яа-яA-Za-z]{1,3}[\-]?\d+\)/', '', $line);

        $line = trim($line);

        if (empty($line) || mb_strlen($line) < 3) {
            return null;
        }

        // Ищем числа с единицами измерения
        preg_match_all('/(\d+[\.,]?\d*)\s*(кг|г|гр|л|мл|шт|штук|уп|упак|пач|пачка|бут|бутылка|банка|банк|кор|коробка)?/ui', $line, $matches, PREG_SET_ORDER);

        $quantity = null;
        $unit = null;
        $price = null;
        $numbers = [];

        foreach ($matches as $match) {
            $num = floatval(str_replace(',', '.', $match[1]));
            $matchUnit = mb_strtolower($match[2] ?? '');

            if ($num <= 0) continue;

            $numbers[] = ['value' => $num, 'unit' => $matchUnit, 'full' => $match[0]];

            if ($matchUnit && $quantity === null) {
                $quantity = $num;
                $unit = $this->normalizeUnit($matchUnit);
            }
        }

        // Если не нашли количество с единицей, берём первое разумное число
        if ($quantity === null && count($numbers) > 0) {
            foreach ($numbers as $n) {
                // Количество обычно < 1000
                if ($n['value'] > 0 && $n['value'] < 1000) {
                    $quantity = $n['value'];
                    break;
                }
            }
        }

        // Цена - обычно последнее число или число > 10
        if (count($numbers) >= 2) {
            $lastNum = end($numbers);
            if ($lastNum['value'] != $quantity) {
                $price = $lastNum['value'];
            }
        }

        // Извлекаем название - всё до первого числа, очищаем
        $name = preg_replace('/\s*\d.*$/', '', $line);
        $name = trim($name, " \t\n\r\0\x0B|,;:.-");

        // Дополнительная очистка названия
        $name = preg_replace('/\s+/', ' ', $name); // множественные пробелы
        $name = preg_replace('/^[\-\.\,\;\:]+/', '', $name); // знаки в начале

        // Проверяем что название не мусор
        if (empty($name) || mb_strlen($name) < 4) {
            return null;
        }

        // Проверяем что есть хотя бы 3 русских буквы подряд (это слово)
        if (!preg_match('/[а-яё]{3,}/ui', $name)) {
            return null;
        }

        // Дополнительная проверка - название не должно быть служебным словом
        $nameLower = mb_strtolower($name);
        $badNames = [
            'код', 'шт', 'кг', 'ед', 'цена', 'сумма', 'кол', 'количество',
            'наименование', 'артикул', 'ставка', 'акциз', 'налог',
        ];
        if (in_array($nameLower, $badNames)) {
            return null;
        }

        // Проверка минимальной длины в символах (не считая пробелы)
        $cleanName = preg_replace('/\s/', '', $name);
        if (mb_strlen($cleanName) < 4) {
            return null;
        }

        return [
            'name' => $name,
            'quantity' => $quantity ?? 1,
            'unit' => $unit ?? 'шт',
            'price' => $price ?? 0,
            'raw_line' => $originalLine
        ];
    }

    /**
     * Нормализовать единицу измерения
     */
    protected function normalizeUnit(string $unit): string
    {
        $unit = mb_strtolower(trim($unit));

        $map = [
            'кг' => 'кг',
            'килограмм' => 'кг',
            'г' => 'г',
            'гр' => 'г',
            'грамм' => 'г',
            'л' => 'л',
            'литр' => 'л',
            'мл' => 'мл',
            'миллилитр' => 'мл',
            'шт' => 'шт',
            'штук' => 'шт',
            'штука' => 'шт',
            'уп' => 'уп',
            'упак' => 'уп',
            'упаковка' => 'уп',
            'пач' => 'пач',
            'пачка' => 'пач',
            'пачек' => 'пач',
            'бут' => 'бут',
            'бутылка' => 'бут',
            'банк' => 'банка',
            'банка' => 'банка',
            'кор' => 'кор',
            'коробка' => 'кор',
        ];

        return $map[$unit] ?? $unit;
    }

    /**
     * Сопоставить распознанные позиции с ингредиентами в базе
     */
    protected function matchWithIngredients(array $items): array
    {
        // Загружаем все ингредиенты
        $ingredients = Ingredient::with('unit')->get();

        $result = [];

        // Порог уверенного совпадения - только при таком score выбираем ингредиент
        $confidenceThreshold = 60;

        foreach ($items as $item) {
            $bestMatch = $this->findBestMatch($item['name'], $ingredients);
            $score = $bestMatch['score'];
            $isConfident = $score >= $confidenceThreshold;

            $result[] = [
                'recognized_name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
                'price' => $item['price'],
                'raw_line' => $item['raw_line'] ?? '',
                // Если совпадение низкое - не выбираем ингредиент по умолчанию
                'ingredient_id' => $isConfident ? $bestMatch['ingredient']?->id : null,
                'ingredient_name' => $isConfident ? $bestMatch['ingredient']?->name : null,
                'ingredient_unit' => $isConfident ? $bestMatch['ingredient']?->unit?->abbreviation : null,
                'match_score' => $score,
                'matched' => $isConfident
            ];
        }

        return $result;
    }

    /**
     * Найти лучшее совпадение для названия
     */
    protected function findBestMatch(string $name, $ingredients): array
    {
        $name = mb_strtolower(trim($name));
        $bestScore = 0;
        $bestIngredient = null;

        foreach ($ingredients as $ingredient) {
            $ingName = mb_strtolower($ingredient->name);

            // 1. Точное совпадение
            if ($name === $ingName) {
                return ['ingredient' => $ingredient, 'score' => 100];
            }

            // 2. Одно содержит другое
            if (str_contains($ingName, $name) || str_contains($name, $ingName)) {
                $score = 85;
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestIngredient = $ingredient;
                }
                continue;
            }

            // 3. Similarity (похожесть строк)
            similar_text($name, $ingName, $percent);

            // 4. Совпадение по словам
            $nameWords = preg_split('/\s+/', $name);
            $ingWords = preg_split('/\s+/', $ingName);
            $commonWords = array_intersect($nameWords, $ingWords);
            $wordScore = count($commonWords) / max(count($nameWords), count($ingWords)) * 100;

            $score = max($percent, $wordScore);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIngredient = $ingredient;
            }
        }

        return [
            'ingredient' => $bestIngredient,
            'score' => round($bestScore)
        ];
    }

    /**
     * Проверить настройки API
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->folderId);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'loyalty_level_id',
        'name',
        'gender',
        'phone',
        'email',
        'birth_date',
        'source',
        'notes',
        'preferences',
        'tags',
        'bonus_balance',
        'total_orders',
        'total_spent',
        'last_order_at',
        'is_blacklisted',
        'sms_consent',
        'email_consent',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'tags' => 'array',
        'bonus_balance' => 'integer',
        'total_orders' => 'integer',
        'total_spent' => 'decimal:2',
        'last_order_at' => 'datetime',
        'is_blacklisted' => 'boolean',
        'sms_consent' => 'boolean',
        'email_consent' => 'boolean',
    ];

    // Добавляем в JSON автоматически
    protected $appends = ['current_loyalty_level'];

    // Accessor: текущий уровень лояльности (рассчитывается по сумме покупок)
    public function getCurrentLoyaltyLevelAttribute(): ?array
    {
        // Сначала пробуем получить из связи
        if ($this->relationLoaded('loyaltyLevel') && $this->loyaltyLevel) {
            return [
                'id' => $this->loyaltyLevel->id,
                'name' => $this->loyaltyLevel->name,
                'icon' => $this->loyaltyLevel->icon,
                'color' => $this->loyaltyLevel->color,
                'discount_percent' => $this->loyaltyLevel->discount_percent,
                'cashback_percent' => $this->loyaltyLevel->cashback_percent,
            ];
        }

        // Иначе рассчитываем по сумме покупок
        $level = LoyaltyLevel::getLevelForTotal($this->total_spent ?? 0, $this->restaurant_id ?? 1);

        if ($level) {
            return [
                'id' => $level->id,
                'name' => $level->name,
                'icon' => $level->icon,
                'color' => $level->color,
                'discount_percent' => $level->discount_percent,
                'cashback_percent' => $level->cashback_percent,
            ];
        }

        return null;
    }

    // Источники привлечения
    public const SOURCES = [
        'recommendation' => 'Рекомендация',
        'instagram' => 'Instagram',
        'vk' => 'ВКонтакте',
        'telegram' => 'Telegram',
        '2gis' => '2ГИС',
        'yandex_maps' => 'Яндекс Карты',
        'website' => 'Сайт',
        'walk_in' => 'Проходил мимо',
        'corporate' => 'Корпоративный',
        'other' => 'Другое',
    ];

    // Предустановленные теги
    public const TAGS = [
        'vip' => 'VIP',
        'corporate' => 'Корпоративный',
        'blogger' => 'Блогер',
        'regular' => 'Постоянный',
        'problem' => 'Проблемный',
    ];

    // ===== RELATIONSHIPS =====

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function defaultAddress()
    {
        return $this->hasOne(CustomerAddress::class)->where('is_default', true);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function bonusTransactions(): HasMany
    {
        return $this->hasMany(BonusTransaction::class);
    }

    public function loyaltyLevel(): BelongsTo
    {
        return $this->belongsTo(LoyaltyLevel::class);
    }

    // ===== SCOPES =====

    public function scopeActive($query)
    {
        return $query->where('is_blacklisted', false);
    }

    public function scopeBlacklisted($query)
    {
        return $query->where('is_blacklisted', true);
    }

    public function scopeByPhone($query, string $phone)
    {
        // Нормализуем телефон для поиска (только цифры)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        // Точное совпадение по нормализованному телефону
        return $query->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') = ?", [$phone]);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }

    public function scopeTopCustomers($query, int $limit = 10)
    {
        return $query->orderByDesc('total_spent')->limit($limit);
    }

    // ===== HELPERS =====

    /**
     * Форматирование имени: первая буква каждого слова заглавная
     */
    public static function formatName(?string $name): ?string
    {
        if (!$name) {
            return null;
        }

        // Убираем лишние пробелы
        $name = trim(preg_replace('/\s+/', ' ', $name));

        // Разбиваем на слова
        $words = explode(' ', $name);

        // Форматируем каждое слово: первая буква заглавная, остальные строчные
        $formattedWords = array_map(function ($word) {
            return mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');
        }, $words);

        return implode(' ', $formattedWords);
    }

    /**
     * Нормализация телефона (только цифры, формат 7XXXXXXXXXX)
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        // Убираем всё кроме цифр
        $digits = preg_replace('/[^0-9]/', '', $phone);

        // Если начинается с 8 - заменяем на 7
        if (strlen($digits) >= 11 && $digits[0] === '8') {
            $digits = '7' . substr($digits, 1);
        }

        // Если 10 цифр - добавляем 7 в начало
        if (strlen($digits) === 10) {
            $digits = '7' . $digits;
        }

        return $digits;
    }

    /**
     * Проверка что телефон полный (11 цифр)
     */
    public static function isPhoneComplete(?string $phone): bool
    {
        if (!$phone) {
            return false;
        }

        $digits = preg_replace('/[^0-9]/', '', $phone);
        return strlen($digits) >= 10;
    }

    public function getDisplayName(): string
    {
        return $this->name ?: $this->phone;
    }

    /**
     * @deprecated Используйте BonusService::earn() для начисления бонусов
     */
    public function addBonusPoints(int $points): void
    {
        $this->increment('bonus_balance', $points);
    }

    /**
     * @deprecated Используйте BonusService::spend() для списания бонусов
     */
    public function useBonusPoints(int $points): bool
    {
        if ($this->bonus_balance < $points) {
            return false;
        }
        $this->decrement('bonus_balance', $points);
        return true;
    }

    public function updateStats(): void
    {
        $totalSpent = $this->orders()->whereIn('status', ['completed'])->sum('total');

        $this->update([
            'total_orders' => $this->orders()->whereIn('status', ['completed'])->count(),
            'total_spent' => $totalSpent,
            'last_order_at' => $this->orders()->latest()->value('created_at'),
        ]);

        // Автоматическое обновление уровня лояльности
        $this->updateLoyaltyLevel($totalSpent);
    }

    /**
     * Обновление уровня лояльности на основе суммы покупок
     */
    public function updateLoyaltyLevel(float $totalSpent = null): void
    {
        // Проверяем включены ли уровни
        $levelsEnabled = LoyaltySetting::get('levels_enabled', '1', $this->restaurant_id);
        if ($levelsEnabled === '0' || $levelsEnabled === false) {
            return;
        }

        $totalSpent = $totalSpent ?? $this->total_spent;

        // Получаем подходящий уровень
        $level = LoyaltyLevel::getLevelForTotal($totalSpent, $this->restaurant_id);

        // Обновляем только если уровень изменился
        if ($level && $this->loyalty_level_id !== $level->id) {
            $this->update(['loyalty_level_id' => $level->id]);
        }
    }

    public function blacklist(): void
    {
        $this->update(['is_blacklisted' => true]);
    }

    public function unblacklist(): void
    {
        $this->update(['is_blacklisted' => false]);
    }

    // Определение категории клиента по сумме заказов
    public function getCategory(): string
    {
        return match(true) {
            $this->total_spent >= 50000 => 'VIP',
            $this->total_spent >= 20000 => 'Постоянный',
            $this->total_spent >= 5000 => 'Активный',
            $this->total_orders >= 1 => 'Новый',
            default => 'Потенциальный',
        };
    }

    public function getCategoryColor(): string
    {
        return match($this->getCategory()) {
            'VIP' => '#F59E0B',
            'Постоянный' => '#8B5CF6',
            'Активный' => '#3B82F6',
            'Новый' => '#10B981',
            default => '#6B7280',
        };
    }

    // Дни рождения
    public function hasBirthdaySoon(int $days = 7): bool
    {
        if (!$this->birth_date) {
            return false;
        }
        
        $birthday = $this->birth_date->setYear(now()->year);
        if ($birthday->isPast()) {
            $birthday->addYear();
        }
        
        return $birthday->diffInDays(now()) <= $days;
    }

    public function isBirthdayToday(): bool
    {
        if (!$this->birth_date) {
            return false;
        }
        return $this->birth_date->isBirthday();
    }
}

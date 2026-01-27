<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Dish extends Model
{
    use HasFactory;

    protected static function boot()
    {
        parent::boot();

        // Auto-generate api_external_id on creation
        static::created(function ($dish) {
            if (empty($dish->api_external_id)) {
                $prefix = $dish->product_type === 'variant' ? 'V' : 'D';
                $dish->api_external_id = $prefix . '-' . $dish->id;
                $dish->saveQuietly(); // Save without triggering events
            }
        });
    }

    protected $fillable = [
        'restaurant_id',
        'category_id',
        'kitchen_station_id',
        'product_type',
        'parent_id',
        'name',
        'variant_name',
        'slug',
        'description',
        'image',
        'price',
        'old_price',
        'cost_price',
        'weight',
        'calories',
        'proteins',
        'fats',
        'carbs',
        'cooking_time',
        'sku',
        'api_external_id',
        'is_available',
        'is_popular',
        'is_new',
        'is_spicy',
        'is_vegetarian',
        'is_vegan',
        'sort_order',
        'variant_sort',
    ];

    protected $appends = ['image_url'];

    protected $casts = [
        'price' => 'decimal:2',
        'old_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'weight' => 'integer',
        'calories' => 'integer',
        'proteins' => 'decimal:2',
        'fats' => 'decimal:2',
        'carbs' => 'decimal:2',
        'cooking_time' => 'integer',
        'is_available' => 'boolean',
        'is_popular' => 'boolean',
        'is_new' => 'boolean',
        'is_spicy' => 'boolean',
        'is_vegetarian' => 'boolean',
        'is_vegan' => 'boolean',
        'variant_sort' => 'integer',
    ];

    // ===== RELATIONSHIPS =====

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function kitchenStation(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class);
    }

    // Родительский товар (для вариантов)
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Dish::class, 'parent_id');
    }

    // Варианты товара (для родителей)
    public function variants(): HasMany
    {
        return $this->hasMany(Dish::class, 'parent_id')->orderBy('variant_sort');
    }

    // Активные варианты
    public function activeVariants(): HasMany
    {
        return $this->hasMany(Dish::class, 'parent_id')
            ->where('is_available', true)
            ->orderBy('variant_sort');
    }

    public function modifiers(): BelongsToMany
    {
        return $this->belongsToMany(Modifier::class, 'dish_modifier')
            ->orderBy('sort_order');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function stopListEntry(): HasOne
    {
        return $this->hasOne(StopList::class);
    }

    // ===== SCOPES =====

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopePopular($query)
    {
        return $query->where('is_popular', true);
    }

    public function scopeNew($query)
    {
        return $query->where('is_new', true);
    }

    public function scopeVegetarian($query)
    {
        return $query->where('is_vegetarian', true);
    }

    public function scopeInCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('sku', 'like', "%{$search}%");
        });
    }

    // Только простые товары (без вариантов)
    public function scopeSimple($query)
    {
        return $query->where('product_type', 'simple');
    }

    // Только родительские товары (с вариантами)
    public function scopeParents($query)
    {
        return $query->where('product_type', 'parent');
    }

    // Только варианты
    public function scopeVariants($query)
    {
        return $query->where('product_type', 'variant');
    }

    // Товары верхнего уровня (simple + parent, без variants)
    public function scopeTopLevel($query)
    {
        return $query->whereIn('product_type', ['simple', 'parent']);
    }

    // Продаваемые товары (simple + variants, без parent)
    public function scopeSellable($query)
    {
        return $query->whereIn('product_type', ['simple', 'variant']);
    }

    // ===== ACCESSORS =====

    public function getImageUrlAttribute(): ?string
    {
        return $this->image;
    }

    // ===== HELPERS =====

    public function isSimple(): bool
    {
        return $this->product_type === 'simple';
    }

    public function isParent(): bool
    {
        return $this->product_type === 'parent';
    }

    public function isVariant(): bool
    {
        return $this->product_type === 'variant';
    }

    public function hasVariants(): bool
    {
        return $this->isParent() && $this->variants()->exists();
    }

    // Получить минимальную цену (для родителя — мин. цена из вариантов)
    public function getMinPrice(): ?float
    {
        if ($this->isParent()) {
            return $this->variants()->min('price');
        }
        return $this->price;
    }

    // Получить полное название (для варианта: "Маргарита 25 см")
    public function getFullName(): string
    {
        if ($this->isVariant() && $this->variant_name) {
            return $this->parent?->name . ' ' . $this->variant_name;
        }
        return $this->name;
    }

    public function isOnSale(): bool
    {
        return !is_null($this->old_price) && $this->old_price > $this->price;
    }

    public function getDiscountPercent(): ?int
    {
        if (!$this->isOnSale()) {
            return null;
        }
        return (int) round((1 - $this->price / $this->old_price) * 100);
    }

    public function isInStopList(): bool
    {
        return $this->stopListEntry()->exists();
    }

    public function addToStopList(string $reason = null, $resumeAt = null): void
    {
        StopList::updateOrCreate(
            [
                'restaurant_id' => $this->restaurant_id,
                'dish_id' => $this->id,
            ],
            [
                'reason' => $reason,
                'stopped_at' => now(),
                'resume_at' => $resumeAt,
            ]
        );
    }

    public function removeFromStopList(): void
    {
        $this->stopListEntry()->delete();
    }

    // Рассчитать маржу
    public function getMargin(): ?float
    {
        if (!$this->cost_price || $this->cost_price <= 0) {
            return null;
        }
        return round((($this->price - $this->cost_price) / $this->price) * 100, 1);
    }

    // Получить теги для UI
    public function getTags(): array
    {
        $tags = [];
        if ($this->is_popular) $tags[] = ['label' => 'Хит', 'color' => '#EF4444'];
        if ($this->is_new) $tags[] = ['label' => 'Новинка', 'color' => '#10B981'];
        if ($this->is_spicy) $tags[] = ['label' => '🌶️', 'color' => '#F97316'];
        if ($this->is_vegetarian) $tags[] = ['label' => '🌱', 'color' => '#22C55E'];
        if ($this->is_vegan) $tags[] = ['label' => 'Веган', 'color' => '#14B8A6'];
        if ($this->isOnSale()) $tags[] = ['label' => "-{$this->getDiscountPercent()}%", 'color' => '#8B5CF6'];
        return $tags;
    }
}

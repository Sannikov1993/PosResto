<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TableQrCode extends Model
{
    protected $fillable = [
        'restaurant_id',
        'table_id',
        'code',
        'short_url',
        'is_active',
        'last_scanned_at',
        'scan_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_scanned_at' => 'datetime',
    ];

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public static function generateCode(): string
    {
        do {
            $code = Str::random(8);
        } while (self::where('code', $code)->exists());
        
        return $code;
    }

    public static function createForTable($tableId, $restaurantId = 1): self
    {
        return self::create([
            'restaurant_id' => $restaurantId,
            'table_id' => $tableId,
            'code' => self::generateCode(),
        ]);
    }

    public function recordScan(): void
    {
        $this->increment('scan_count');
        $this->update(['last_scanned_at' => now()]);
    }

    public function getUrlAttribute(): string
    {
        return url("/menu/{$this->code}");
    }
}

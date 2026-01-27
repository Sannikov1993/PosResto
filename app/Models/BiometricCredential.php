<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricCredential extends Model
{
    protected $fillable = [
        'user_id',
        'credential_id',
        'public_key',
        'name',
        'device_type',
        'sign_count',
        'aaguid',
        'last_used_at',
    ];

    protected $casts = [
        'sign_count' => 'integer',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'public_key',
    ];

    // ==================== RELATIONSHIPS ====================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ==================== SCOPES ====================

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ==================== METHODS ====================

    /**
     * Mark credential as used
     */
    public function markAsUsed(): void
    {
        $this->increment('sign_count');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Update sign count after verification
     */
    public function updateSignCount(int $newCount): void
    {
        if ($newCount > $this->sign_count) {
            $this->update([
                'sign_count' => $newCount,
                'last_used_at' => now(),
            ]);
        }
    }

    /**
     * Get device type label
     */
    public function getDeviceTypeLabelAttribute(): string
    {
        return match($this->device_type) {
            'fingerprint' => 'Отпечаток пальца',
            'face' => 'Face ID',
            'pin' => 'PIN',
            'pattern' => 'Графический ключ',
            'security_key' => 'Ключ безопасности',
            default => $this->device_type ?? 'Биометрия',
        };
    }

    /**
     * Get credential info for display
     */
    public function getInfoAttribute(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name ?? $this->device_type_label,
            'device_type' => $this->device_type,
            'device_type_label' => $this->device_type_label,
            'created_at' => $this->created_at->format('d.m.Y H:i'),
            'last_used_at' => $this->last_used_at?->format('d.m.Y H:i'),
        ];
    }

    /**
     * Create credential from WebAuthn registration response
     */
    public static function createFromWebAuthn(
        int $userId,
        string $credentialId,
        string $publicKey,
        int $signCount = 0,
        ?string $aaguid = null,
        ?string $name = null,
        ?string $deviceType = null
    ): self {
        return static::create([
            'user_id' => $userId,
            'credential_id' => $credentialId,
            'public_key' => $publicKey,
            'sign_count' => $signCount,
            'aaguid' => $aaguid,
            'name' => $name,
            'device_type' => $deviceType,
        ]);
    }

    /**
     * Find credential by credential ID
     */
    public static function findByCredentialId(string $credentialId): ?self
    {
        return static::where('credential_id', $credentialId)->first();
    }

    /**
     * Get all credentials for user
     */
    public static function getForUser(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return static::forUser($userId)
            ->orderByDesc('last_used_at')
            ->get();
    }
}

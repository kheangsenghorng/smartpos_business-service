<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid',
        'business_id',
        'code',
        'name',
        'phone',
        'email',
        'address',
        'city',
        'province',
        'postal_code',
        'country_code',
        'latitude',
        'longitude',
        'is_main_outlet',
        'receipt_header',
        'receipt_footer',
        'tax_rate',
        'timezone',
        'is_active',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_main_outlet' => 'boolean',
            'is_active' => 'boolean',
            'tax_rate' => 'decimal:2',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function registers(): HasMany
    {
        return $this->hasMany(Register::class);
    }

    public function businessUsers(): HasMany
    {
        return $this->hasMany(BusinessUser::class);
    }

    public function businessUserOutlets(): HasMany
    {
        return $this->hasMany(BusinessUserOutlet::class);
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(BusinessUser::class, 'business_user_outlets')
            ->withPivot(['uuid', 'is_primary', 'is_active', 'assigned_at'])
            ->withTimestamps();
    }

    public function posDevices(): HasMany
    {
        return $this->hasMany(PosDevice::class);
    }

    public function cashierSessions(): HasMany
    {
        return $this->hasMany(CashierSession::class);
    }

    public function registerSessions(): HasMany
    {
        return $this->hasMany(RegisterSession::class);
    }

    public function cashDrawerSessions(): HasMany
    {
        return $this->hasMany(CashDrawerSession::class);
    }

    public function cashDrawerMovements(): HasMany
    {
        return $this->hasMany(CashDrawerMovement::class);
    }
}

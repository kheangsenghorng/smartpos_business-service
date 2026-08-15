<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_main_outlet' => 'boolean',
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

    public function posDevices(): HasMany
    {
        return $this->hasMany(PosDevice::class);
    }
}

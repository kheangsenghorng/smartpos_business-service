<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Warehouse extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => 'active',
    ];

    protected $fillable = [
        'uuid',
        'business_id',
        'outlet_id',
        'code',
        'name',
        'address',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (Warehouse $warehouse) {
            $warehouse->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class);
    }
}

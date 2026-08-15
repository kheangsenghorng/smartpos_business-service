<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Register extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid',
        'business_id',
        'outlet_id',
        'code',
        'name',
        'description',
        'default_cash_amount',
        'receipt_printer_name',
        'is_cash_drawer_connected',
        'is_active',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_cash_drawer_connected' => 'boolean',
            'default_cash_amount' => 'decimal:2',
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

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function posDevices(): HasMany
    {
        return $this->hasMany(PosDevice::class);
    }
}

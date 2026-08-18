<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RegisterSession extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid',
        'business_id',
        'outlet_id',
        'register_id',
        'pos_device_id',
        'opened_by_user_uuid',
        'closed_by_user_uuid',
        'opening_cash',
        'expected_cash',
        'closing_cash',
        'difference_amount',
        'status',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'opening_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'closing_cash' => 'decimal:2',
            'difference_amount' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
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

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class);
    }

    public function cashDrawerSession(): HasOne
    {
        return $this->hasOne(CashDrawerSession::class);
    }

    public function cashDrawerSessions(): HasMany
    {
        return $this->hasMany(CashDrawerSession::class);
    }
}

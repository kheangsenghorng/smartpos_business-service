<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashDrawerSession extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid',
        'register_session_id',
        'business_id',
        'outlet_id',
        'register_id',
        'opening_amount',
        'expected_amount',
        'counted_amount',
        'difference_amount',
        'status',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'opening_amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'counted_amount' => 'decimal:2',
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

    public function registerSession(): BelongsTo
    {
        return $this->belongsTo(RegisterSession::class);
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

    public function movements(): HasMany
    {
        return $this->hasMany(CashDrawerMovement::class);
    }
}

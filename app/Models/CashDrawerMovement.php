<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashDrawerMovement extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid',
        'cash_drawer_session_id',
        'business_id',
        'outlet_id',
        'register_id',
        'user_uuid',
        'type',
        'amount',
        'reference_type',
        'reference_uuid',
        'reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
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

    public function cashDrawerSession(): BelongsTo
    {
        return $this->belongsTo(CashDrawerSession::class);
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
}

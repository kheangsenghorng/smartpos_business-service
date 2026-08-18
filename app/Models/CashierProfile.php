<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashierProfile extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid',
        'business_user_id',
        'display_name',
        'avatar_url',
        'can_sell',
        'can_refund',
        'can_void',
        'can_discount',
        'max_discount_percent',
        'is_active',
        'last_pos_login_at',
    ];

    protected function casts(): array
    {
        return [
            'can_sell' => 'boolean',
            'can_refund' => 'boolean',
            'can_void' => 'boolean',
            'can_discount' => 'boolean',
            'max_discount_percent' => 'decimal:2',
            'is_active' => 'boolean',
            'last_pos_login_at' => 'datetime',
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

    public function businessUser(): BelongsTo
    {
        return $this->belongsTo(BusinessUser::class);
    }
}

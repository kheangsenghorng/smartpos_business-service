<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessUser extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid',
        'business_id',
        'outlet_id',
        'user_uuid',
        'role',
        'is_owner',
        'pin_code_hash',
        'phone',
        'notes',
        'status',
        'joined_at',
    ];

    protected $hidden = [
        'pin_code_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_owner' => 'boolean',
            'joined_at' => 'datetime',
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
}

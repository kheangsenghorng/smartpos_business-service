<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BusinessUser extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid',
        'business_id',
        'outlet_id',
        'user_uuid',
        'employee_code',
        'job_title',
        'role',
        'is_owner',
        'is_active',
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
            'is_active' => 'boolean',
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

    public function cashierProfile(): HasOne
    {
        return $this->hasOne(CashierProfile::class);
    }

    public function businessUserOutlets(): HasMany
    {
        return $this->hasMany(BusinessUserOutlet::class);
    }

    public function assignedOutlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'business_user_outlets')
            ->withPivot(['uuid', 'is_primary', 'is_active', 'assigned_at'])
            ->withTimestamps();
    }

    public function cashierSessions(): HasMany
    {
        return $this->hasMany(CashierSession::class);
    }
}

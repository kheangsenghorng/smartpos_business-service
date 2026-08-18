<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PosDevice extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid',
        'business_id',
        'outlet_id',
        'register_id',
        'machine_id',
        'device_code',
        'name',
        'device_name',
        'device_type',
        'platform',
        'device_model',
        'os_version',
        'app_version',
        'serial_number',
        'ip_address',
        'mac_address',
        'machine_password_hash',
        'status',
        'registered_at',
        'activated_at',
        'paired_at',
        'last_seen_at',
        'last_sync_at',
        'revoked_at',
    ];

    protected $hidden = [
        'machine_password_hash',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'activated_at' => 'datetime',
            'paired_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'revoked_at' => 'datetime',
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

    public function credentials(): HasMany
    {
        return $this->hasMany(PosDeviceCredential::class);
    }

    public function activeCredential(): HasOne
    {
        return $this->hasOne(PosDeviceCredential::class)->where('is_active', true);
    }

    public function deviceSessions(): HasMany
    {
        return $this->hasMany(DeviceSession::class);
    }

    public function cashierSessions(): HasMany
    {
        return $this->hasMany(CashierSession::class);
    }

    public function registerSessions(): HasMany
    {
        return $this->hasMany(RegisterSession::class);
    }
}

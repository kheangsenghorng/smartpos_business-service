<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosDeviceCredential extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid',
        'pos_device_id',
        'secret_hash',
        'is_active',
        'last_rotated_at',
        'expires_at',
        'revoked_at',
    ];

    protected $hidden = [
        'secret_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_rotated_at' => 'datetime',
            'expires_at' => 'datetime',
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

    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class);
    }
}

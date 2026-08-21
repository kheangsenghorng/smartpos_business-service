<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WarehouseLocation extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => 'active',
    ];

    protected $fillable = [
        'uuid',
        'warehouse_id',
        'code',
        'zone',
        'aisle',
        'rack',
        'shelf',
        'bin',
        'description',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (WarehouseLocation $location) {
            $location->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}

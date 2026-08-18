<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Business extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'legal_name',
        'phone',
        'email',
        'tax_number',
        'registration_number',
        'logo_path',
        'logo_url',
        'website',
        'description',
        'address',
        'city',
        'province',
        'postal_code',
        'country_code',
        'currency_code',
        'default_currency',
        'currency_symbol',
        'receipt_header',
        'receipt_footer',
        'tax_rate',
        'is_tax_inclusive',
        'timezone',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_tax_inclusive' => 'boolean',
            'tax_rate' => 'decimal:2',
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

    public function settings(): HasOne
    {
        return $this->hasOne(BusinessSetting::class);
    }

    public function businessUsers(): HasMany
    {
        return $this->hasMany(BusinessUser::class);
    }

    public function outlets(): HasMany
    {
        return $this->hasMany(Outlet::class);
    }

    public function registers(): HasMany
    {
        return $this->hasMany(Register::class);
    }

    public function posDevices(): HasMany
    {
        return $this->hasMany(PosDevice::class);
    }

    public function cashierSessions(): HasMany
    {
        return $this->hasMany(CashierSession::class);
    }

    public function registerSessions(): HasMany
    {
        return $this->hasMany(RegisterSession::class);
    }

    public function cashDrawerSessions(): HasMany
    {
        return $this->hasMany(CashDrawerSession::class);
    }

    public function cashDrawerMovements(): HasMany
    {
        return $this->hasMany(CashDrawerMovement::class);
    }
}

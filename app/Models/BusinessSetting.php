<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'receipt_prefix',
        'currency_code',
        'timezone',
        'tax_enabled',
        'default_tax_percent',
        'allow_negative_stock',
        'allow_discount',
        'max_discount_percent',
        'auto_lock_minutes',
        'receipt_footer',
    ];

    protected function casts(): array
    {
        return [
            'tax_enabled' => 'boolean',
            'default_tax_percent' => 'decimal:2',
            'allow_negative_stock' => 'boolean',
            'allow_discount' => 'boolean',
            'max_discount_percent' => 'decimal:2',
            'auto_lock_minutes' => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}

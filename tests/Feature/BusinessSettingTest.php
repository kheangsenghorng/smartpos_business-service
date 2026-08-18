<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessSetting;
use App\Models\BusinessUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_business_settings(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Setting Biz', 'code' => 'SET-01']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $userUuid, 'is_owner' => true, 'status' => 'active']);

        $response = $this->withJwtAuth($userUuid, ['businesses.view'])
            ->getJson("/api/v1/businesses/{$business->uuid}/settings");

        $response->assertStatus(200)
            ->assertJsonPath('data.receipt_prefix', 'REC')
            ->assertJsonPath('data.auto_lock_minutes', 15);
    }

    public function test_can_update_business_settings(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Setting Biz 2', 'code' => 'SET-02']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $userUuid, 'is_owner' => true, 'status' => 'active']);

        $response = $this->withJwtAuth($userUuid, ['businesses.update'])
            ->putJson("/api/v1/businesses/{$business->uuid}/settings", [
                'receipt_prefix' => 'INV-PP',
                'currency_code' => 'KHR',
                'tax_enabled' => true,
                'default_tax_percent' => 10.00,
                'allow_negative_stock' => false,
                'allow_discount' => true,
                'max_discount_percent' => 30.00,
                'auto_lock_minutes' => 10,
                'receipt_footer' => 'Thank you for visiting!',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.receipt_prefix', 'INV-PP')
            ->assertJsonPath('data.currency_code', 'KHR')
            ->assertJsonPath('data.tax_enabled', true)
            ->assertJsonPath('data.auto_lock_minutes', 10);

        $this->assertDatabaseHas('business_settings', [
            'business_id' => $business->id,
            'receipt_prefix' => 'INV-PP',
            'currency_code' => 'KHR',
            'auto_lock_minutes' => 10,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\CashierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashierProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_cashier_profile(): void
    {
        $ownerUuid = (string) Str::uuid();
        $staffUuid = (string) Str::uuid();

        $business = Business::create(['name' => 'Cashier Profile Biz', 'code' => 'CPB-01']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $ownerUuid, 'is_owner' => true, 'status' => 'active']);
        $staff = BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $staffUuid, 'is_owner' => false, 'status' => 'active']);

        $response = $this->withJwtAuth($ownerUuid, ['business_users.view'])
            ->getJson("/api/v1/businesses/{$business->uuid}/users/{$staff->uuid}/cashier-profile");

        $response->assertStatus(200)
            ->assertJsonPath('data.can_sell', true)
            ->assertJsonPath('data.can_refund', false);
    }

    public function test_can_update_cashier_profile_permissions(): void
    {
        $ownerUuid = (string) Str::uuid();
        $staffUuid = (string) Str::uuid();

        $business = Business::create(['name' => 'Cashier Profile Biz 2', 'code' => 'CPB-02']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $ownerUuid, 'is_owner' => true, 'status' => 'active']);
        $staff = BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $staffUuid, 'is_owner' => false, 'status' => 'active']);

        $response = $this->withJwtAuth($ownerUuid, ['business_users.manage'])
            ->putJson("/api/v1/businesses/{$business->uuid}/users/{$staff->uuid}/cashier-profile", [
                'display_name' => 'Sokha POS Lead',
                'can_sell' => true,
                'can_refund' => true,
                'can_void' => true,
                'can_discount' => true,
                'max_discount_percent' => 20.00,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.display_name', 'Sokha POS Lead')
            ->assertJsonPath('data.can_refund', true)
            ->assertJsonPath('data.can_void', true)
            ->assertJsonPath('data.can_discount', true);

        $this->assertDatabaseHas('cashier_profiles', [
            'business_user_id' => $staff->id,
            'display_name' => 'Sokha POS Lead',
            'can_refund' => true,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\BusinessUserOutlet;
use App\Models\Outlet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessUserOutletTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_assign_outlet_to_business_user(): void
    {
        $ownerUuid = (string) Str::uuid();
        $staffUuid = (string) Str::uuid();

        $business = Business::create(['name' => 'Outlet Assign Biz', 'code' => 'OAB-01']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $ownerUuid, 'is_owner' => true, 'status' => 'active']);
        $staff = BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $staffUuid, 'is_owner' => false, 'status' => 'active']);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-A1', 'name' => 'Outlet Alpha']);

        $response = $this->withJwtAuth($ownerUuid, ['business_users.manage'])
            ->postJson("/api/v1/businesses/{$business->uuid}/users/{$staff->uuid}/outlets", [
                'outlet_uuid' => $outlet->uuid,
                'is_primary' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_primary', true)
            ->assertJsonPath('data.outlet.code', 'OUT-A1');

        $this->assertDatabaseHas('business_user_outlets', [
            'business_user_id' => $staff->id,
            'outlet_id' => $outlet->id,
            'is_primary' => true,
        ]);
    }

    public function test_can_list_and_remove_assigned_outlets(): void
    {
        $ownerUuid = (string) Str::uuid();
        $staffUuid = (string) Str::uuid();

        $business = Business::create(['name' => 'Outlet Assign Biz 2', 'code' => 'OAB-02']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $ownerUuid, 'is_owner' => true, 'status' => 'active']);
        $staff = BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $staffUuid, 'is_owner' => false, 'status' => 'active']);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-A2', 'name' => 'Outlet Beta']);

        BusinessUserOutlet::create([
            'business_user_id' => $staff->id,
            'outlet_id' => $outlet->id,
            'is_primary' => true,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        // List
        $listResponse = $this->withJwtAuth($ownerUuid, ['business_users.view'])
            ->getJson("/api/v1/businesses/{$business->uuid}/users/{$staff->uuid}/outlets");

        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Delete
        $deleteResponse = $this->withJwtAuth($ownerUuid, ['business_users.manage'])
            ->deleteJson("/api/v1/businesses/{$business->uuid}/users/{$staff->uuid}/outlets/{$outlet->uuid}");

        $deleteResponse->assertStatus(200);

        $this->assertDatabaseMissing('business_user_outlets', [
            'business_user_id' => $staff->id,
            'outlet_id' => $outlet->id,
        ]);
    }
}

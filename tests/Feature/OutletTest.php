<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Outlet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OutletTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_outlet(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Business A', 'code' => 'BIZ-OUT-1']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $userUuid, 'is_owner' => true, 'status' => 'active']);

        $response = $this->withJwtAuth($userUuid, ['outlets.create'])
            ->postJson("/api/v1/businesses/{$business->uuid}/outlets", [
                'code' => 'OUT-01',
                'name' => 'Main Outlet',
                'city' => 'Jakarta',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'OUT-01')
            ->assertJsonPath('data.name', 'Main Outlet');

        $this->assertDatabaseHas('outlets', [
            'business_id' => $business->id,
            'code' => 'OUT-01',
        ]);
    }

    public function test_duplicate_outlet_code_rejected(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Business A', 'code' => 'BIZ-OUT-2']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $userUuid, 'is_owner' => true, 'status' => 'active']);

        Outlet::create(['business_id' => $business->id, 'code' => 'OUT-DUP', 'name' => 'Outlet 1']);

        $response = $this->withJwtAuth($userUuid, ['outlets.create'])
            ->postJson("/api/v1/businesses/{$business->uuid}/outlets", [
                'code' => 'OUT-DUP',
                'name' => 'Outlet 2',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_cannot_access_outlet_of_another_business(): void
    {
        $userUuidA = (string) Str::uuid();
        $userUuidB = (string) Str::uuid();

        $businessA = Business::create(['name' => 'Business A', 'code' => 'BIZ-A']);
        BusinessUser::create(['business_id' => $businessA->id, 'user_uuid' => $userUuidA, 'is_owner' => true, 'status' => 'active']);

        $businessB = Business::create(['name' => 'Business B', 'code' => 'BIZ-B']);
        BusinessUser::create(['business_id' => $businessB->id, 'user_uuid' => $userUuidB, 'is_owner' => true, 'status' => 'active']);

        $outletB = Outlet::create(['business_id' => $businessB->id, 'code' => 'OUT-B', 'name' => 'Outlet B']);

        // User A tries to view Outlet B
        $response = $this->withJwtAuth($userUuidA, ['outlets.view'])
            ->getJson("/api/v1/outlets/{$outletB->uuid}");

        $response->assertStatus(403);
    }
}

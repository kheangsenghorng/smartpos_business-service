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

    public function test_can_create_outlet_with_enhanced_schema_fields(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Business Outlet Test', 'code' => 'BIZ-OUT-ENH']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $userUuid, 'is_owner' => true, 'status' => 'active']);

        $response = $this->withJwtAuth($userUuid, ['outlets.create'])
            ->postJson("/api/v1/businesses/{$business->uuid}/outlets", [
                'code' => 'OUT-ENH-01',
                'name' => 'Downtown Flagship',
                'phone' => '+62215551234',
                'email' => 'downtown@smartretail.id',
                'address' => 'Mall Grand Indonesia Lt. 3',
                'city' => 'Jakarta Pusat',
                'province' => 'DKI Jakarta',
                'postal_code' => '10310',
                'country_code' => 'ID',
                'latitude' => -6.19541200,
                'longitude' => 106.82088000,
                'is_main_outlet' => true,
                'receipt_header' => 'Grand Indonesia Store',
                'receipt_footer' => 'No refunds without receipt',
                'tax_rate' => 11.00,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'OUT-ENH-01')
            ->assertJsonPath('data.postal_code', '10310')
            ->assertJsonPath('data.is_main_outlet', true);

        $this->assertDatabaseHas('outlets', [
            'business_id' => $business->id,
            'code' => 'OUT-ENH-01',
            'postal_code' => '10310',
            'is_main_outlet' => true,
        ]);
    }
}

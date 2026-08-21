<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Outlet;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WarehouseApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_warehouses(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Tech Logistics', 'code' => 'TL-01']);
        BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $userUuid,
            'is_owner' => true,
            'status' => 'active',
        ]);

        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-1', 'name' => 'Outlet 1']);

        $wh1 = Warehouse::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'code' => 'WH-01',
            'name' => 'Outlet Warehouse',
            'status' => 'active',
        ]);

        WarehouseLocation::create(['warehouse_id' => $wh1->id, 'code' => 'LOC-01']);

        $wh2 = Warehouse::create([
            'business_id' => $business->id,
            'outlet_id' => null,
            'code' => 'WH-02',
            'name' => 'Central Hub',
            'status' => 'inactive',
        ]);

        // List all
        $response = $this->withJwtAuth($userUuid, ['warehouses.view'])
            ->getJson("/api/v1/businesses/{$business->uuid}/warehouses");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.locations_count', 1);

        // Filter by status=active
        $activeResponse = $this->withJwtAuth($userUuid, ['warehouses.view'])
            ->getJson("/api/v1/businesses/{$business->uuid}/warehouses?status=active");

        $activeResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'WH-01');

        // Filter by central warehouse (outlet_id=null)
        $centralResponse = $this->withJwtAuth($userUuid, ['warehouses.view'])
            ->getJson("/api/v1/businesses/{$business->uuid}/warehouses?outlet_id=null");

        $centralResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'WH-02');
    }

    public function test_authenticated_user_can_view_one_warehouse(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Tech Logistics', 'code' => 'TL-02']);
        BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $userUuid,
            'is_owner' => true,
            'status' => 'active',
        ]);

        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-PHN', 'name' => 'Phnom Penh Outlet']);

        $warehouse = Warehouse::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'code' => 'WH-PHN-01',
            'name' => 'Phnom Penh Main Depot',
            'address' => 'St 271, Phnom Penh',
        ]);

        WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'A-01',
            'zone' => 'A',
            'aisle' => '01',
        ]);

        $response = $this->withJwtAuth($userUuid, ['warehouses.view'])
            ->getJson("/api/v1/warehouses/{$warehouse->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.code', 'WH-PHN-01')
            ->assertJsonPath('data.name', 'Phnom Penh Main Depot')
            ->assertJsonPath('data.business.id', $business->id)
            ->assertJsonPath('data.outlet.id', $outlet->id)
            ->assertJsonCount(1, 'data.locations');
    }

    public function test_authorized_user_can_create_a_warehouse_with_outlet(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Tech Logistics', 'code' => 'TL-03']);
        BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $userUuid,
            'is_owner' => true,
            'status' => 'active',
        ]);

        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-SR', 'name' => 'Siem Reap Outlet']);

        $response = $this->withJwtAuth($userUuid, ['warehouses.create'])
            ->postJson("/api/v1/businesses/{$business->uuid}/warehouses", [
                'outlet_id' => $outlet->id,
                'code' => 'WH-SR-01',
                'name' => 'Siem Reap Warehouse',
                'address' => 'National Road 6, Siem Reap',
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'WH-SR-01')
            ->assertJsonPath('data.name', 'Siem Reap Warehouse')
            ->assertJsonPath('data.outlet.id', $outlet->id);

        $this->assertDatabaseHas('warehouses', [
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'code' => 'WH-SR-01',
        ]);
    }

    public function test_authorized_user_can_create_a_central_warehouse_without_outlet(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Tech Logistics', 'code' => 'TL-04']);
        BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $userUuid,
            'is_owner' => true,
            'status' => 'active',
        ]);

        $response = $this->withJwtAuth($userUuid, ['warehouses.create'])
            ->postJson("/api/v1/businesses/{$business->uuid}/warehouses", [
                'code' => 'WH-CENTRAL-01',
                'name' => 'Main Distribution Center',
                'address' => 'Veng Sreng Blvd, Phnom Penh',
                'outlet_id' => null,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'WH-CENTRAL-01')
            ->assertJsonPath('data.outlet_id', null);

        $this->assertDatabaseHas('warehouses', [
            'business_id' => $business->id,
            'outlet_id' => null,
            'code' => 'WH-CENTRAL-01',
        ]);
    }

    public function test_authorized_user_can_update_a_warehouse(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Tech Logistics', 'code' => 'TL-05']);
        BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $userUuid,
            'is_owner' => true,
            'status' => 'active',
        ]);

        $warehouse = Warehouse::create([
            'business_id' => $business->id,
            'code' => 'WH-OLD-CODE',
            'name' => 'Old Name',
        ]);

        $response = $this->withJwtAuth($userUuid, ['warehouses.update'])
            ->putJson("/api/v1/warehouses/{$warehouse->uuid}", [
                'code' => 'WH-NEW-CODE',
                'name' => 'Updated Warehouse Name',
                'address' => 'Updated Address 123',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'WH-NEW-CODE')
            ->assertJsonPath('data.name', 'Updated Warehouse Name')
            ->assertJsonPath('data.address', 'Updated Address 123');

        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouse->id,
            'code' => 'WH-NEW-CODE',
            'name' => 'Updated Warehouse Name',
        ]);
    }

    public function test_authorized_user_can_deactivate_a_warehouse(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Tech Logistics', 'code' => 'TL-06']);
        BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $userUuid,
            'is_owner' => true,
            'status' => 'active',
        ]);

        $warehouse = Warehouse::create([
            'business_id' => $business->id,
            'code' => 'WH-DEACTIVATE',
            'name' => 'Warehouse To Deactivate',
            'status' => 'active',
        ]);

        $response = $this->withJwtAuth($userUuid, ['warehouses.update'])
            ->putJson("/api/v1/warehouses/{$warehouse->uuid}", [
                'status' => 'inactive',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouse->id,
            'status' => 'inactive',
        ]);
    }

    public function test_authorized_owner_can_delete_a_warehouse(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Tech Logistics', 'code' => 'TL-07']);
        BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $userUuid,
            'is_owner' => true,
            'status' => 'active',
        ]);

        $warehouse = Warehouse::create([
            'business_id' => $business->id,
            'code' => 'WH-DELETE',
            'name' => 'Warehouse To Delete',
        ]);

        $response = $this->withJwtAuth($userUuid, ['warehouses.delete'])
            ->deleteJson("/api/v1/warehouses/{$warehouse->uuid}");

        $response->assertOk()
            ->assertJsonPath('message', 'Warehouse deleted successfully.');

        $this->assertDatabaseMissing('warehouses', ['id' => $warehouse->id]);
    }

    public function test_warehouse_validation_rules(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Tech Logistics', 'code' => 'TL-08']);
        BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $userUuid,
            'is_owner' => true,
            'status' => 'active',
        ]);

        Warehouse::create([
            'business_id' => $business->id,
            'code' => 'EXISTING-WH',
            'name' => 'Existing WH',
        ]);

        // Code and Name are required
        $res1 = $this->withJwtAuth($userUuid, ['warehouses.create'])
            ->postJson("/api/v1/businesses/{$business->uuid}/warehouses", []);
        $res1->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name']);

        // Duplicate code in same business rejected
        $res2 = $this->withJwtAuth($userUuid, ['warehouses.create'])
            ->postJson("/api/v1/businesses/{$business->uuid}/warehouses", [
                'code' => 'EXISTING-WH',
                'name' => 'Another Name',
            ]);
        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        // Invalid status value rejected
        $res3 = $this->withJwtAuth($userUuid, ['warehouses.create'])
            ->postJson("/api/v1/businesses/{$business->uuid}/warehouses", [
                'code' => 'WH-BAD-STATUS',
                'name' => 'Bad Status WH',
                'status' => 'invalid_status_value',
            ]);
        $res3->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        // Invalid outlet_id rejected (non-existent)
        $res4 = $this->withJwtAuth($userUuid, ['warehouses.create'])
            ->postJson("/api/v1/businesses/{$business->uuid}/warehouses", [
                'code' => 'WH-BAD-OUTLET',
                'name' => 'Bad Outlet WH',
                'outlet_id' => 999999,
            ]);
        $res4->assertStatus(422)
            ->assertJsonValidationErrors(['outlet_id']);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $business = Business::create(['name' => 'Acme Corp', 'code' => 'ACME-UNAUTH']);
        $warehouse = Warehouse::create([
            'business_id' => $business->id,
            'code' => 'WH-UNAUTH',
            'name' => 'Unauth Warehouse',
        ]);

        $response = $this->getJson("/api/v1/warehouses/{$warehouse->uuid}");
        $response->assertUnauthorized();
    }

    public function test_request_without_permission_returns_403(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Tech Logistics', 'code' => 'TL-09']);
        BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $userUuid,
            'is_owner' => true,
            'status' => 'active',
        ]);

        $warehouse = Warehouse::create([
            'business_id' => $business->id,
            'code' => 'WH-PERM-TEST',
            'name' => 'Perm Test',
        ]);

        // Token without warehouses.view permission
        $response = $this->withJwtAuth($userUuid, ['other.permission'])
            ->getJson("/api/v1/warehouses/{$warehouse->uuid}");

        $response->assertForbidden();
    }

    public function test_request_for_non_existent_warehouse_returns_404(): void
    {
        $userUuid = (string) Str::uuid();
        $fakeUuid = (string) Str::uuid();

        $response = $this->withJwtAuth($userUuid, ['warehouses.view'])
            ->getJson("/api/v1/warehouses/{$fakeUuid}");

        $response->assertNotFound();
    }
}

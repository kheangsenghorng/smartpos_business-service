<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WarehouseLocationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_warehouse_locations(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Mega Depot', 'code' => 'MD-01']);
        BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $userUuid,
            'is_owner' => true,
            'status' => 'active',
        ]);

        $warehouse = Warehouse::create([
            'business_id' => $business->id,
            'code' => 'WH-LOC-01',
            'name' => 'Depot 1',
        ]);

        WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'A-01',
            'zone' => 'Zone A',
            'aisle' => '01',
            'rack' => 'R1',
            'shelf' => 'S1',
            'bin' => 'B1',
            'status' => 'active',
        ]);

        WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'B-01',
            'zone' => 'Zone B',
            'aisle' => '01',
            'status' => 'inactive',
        ]);

        // List all locations
        $response = $this->withJwtAuth($userUuid, ['warehouses.view'])
            ->getJson("/api/v1/warehouses/{$warehouse->uuid}/locations");

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        // Filter by zone
        $zoneResponse = $this->withJwtAuth($userUuid, ['warehouses.view'])
            ->getJson("/api/v1/warehouses/{$warehouse->uuid}/locations?zone=Zone A");

        $zoneResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'A-01');

        // Filter by status
        $statusResponse = $this->withJwtAuth($userUuid, ['warehouses.view'])
            ->getJson("/api/v1/warehouses/{$warehouse->uuid}/locations?status=active");

        $statusResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'A-01');
    }

    public function test_authorized_user_can_create_a_warehouse_location(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Mega Depot', 'code' => 'MD-02']);
        BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $userUuid,
            'is_owner' => true,
            'status' => 'active',
        ]);

        $warehouse = Warehouse::create([
            'business_id' => $business->id,
            'code' => 'WH-LOC-02',
            'name' => 'Depot 2',
        ]);

        $response = $this->withJwtAuth($userUuid, ['warehouses.create'])
            ->postJson("/api/v1/warehouses/{$warehouse->uuid}/locations", [
                'code' => 'A-01-R1-S1-B1',
                'zone' => 'A',
                'aisle' => '01',
                'rack' => 'R01',
                'shelf' => 'S01',
                'bin' => 'B01',
                'description' => 'Fast moving goods pallet rack',
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'A-01-R1-S1-B1')
            ->assertJsonPath('data.zone', 'A')
            ->assertJsonPath('data.rack', 'R01')
            ->assertJsonPath('data.shelf', 'S01')
            ->assertJsonPath('data.bin', 'B01');

        $this->assertDatabaseHas('warehouse_locations', [
            'warehouse_id' => $warehouse->id,
            'code' => 'A-01-R1-S1-B1',
            'zone' => 'A',
        ]);
    }

    public function test_authorized_user_can_view_a_warehouse_location(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Mega Depot', 'code' => 'MD-03']);
        BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $userUuid,
            'is_owner' => true,
            'status' => 'active',
        ]);

        $warehouse = Warehouse::create([
            'business_id' => $business->id,
            'code' => 'WH-LOC-03',
            'name' => 'Depot 3',
        ]);

        $location = WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'LOC-SHOW',
            'zone' => 'Z1',
        ]);

        $response = $this->withJwtAuth($userUuid, ['warehouses.view'])
            ->getJson("/api/v1/warehouse-locations/{$location->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.code', 'LOC-SHOW')
            ->assertJsonPath('data.warehouse.id', $warehouse->id);
    }

    public function test_authorized_user_can_update_a_warehouse_location(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Mega Depot', 'code' => 'MD-04']);
        BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $userUuid,
            'is_owner' => true,
            'status' => 'active',
        ]);

        $warehouse = Warehouse::create([
            'business_id' => $business->id,
            'code' => 'WH-LOC-04',
            'name' => 'Depot 4',
        ]);

        $location = WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'LOC-ORIG',
            'zone' => 'Zone 1',
        ]);

        $response = $this->withJwtAuth($userUuid, ['warehouses.update'])
            ->putJson("/api/v1/warehouse-locations/{$location->uuid}", [
                'code' => 'LOC-UPDATED',
                'zone' => 'Zone 2',
                'aisle' => 'Aisle 5',
                'rack' => 'Rack 3',
                'shelf' => 'Shelf 2',
                'bin' => 'Bin 1',
                'description' => 'Updated storage bin',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'LOC-UPDATED')
            ->assertJsonPath('data.zone', 'Zone 2')
            ->assertJsonPath('data.aisle', 'Aisle 5');

        $this->assertDatabaseHas('warehouse_locations', [
            'id' => $location->id,
            'code' => 'LOC-UPDATED',
            'zone' => 'Zone 2',
        ]);
    }

    public function test_authorized_user_can_delete_a_warehouse_location(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Mega Depot', 'code' => 'MD-05']);
        BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $userUuid,
            'is_owner' => true,
            'status' => 'active',
        ]);

        $warehouse = Warehouse::create([
            'business_id' => $business->id,
            'code' => 'WH-LOC-05',
            'name' => 'Depot 5',
        ]);

        $location = WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'LOC-TO-DELETE',
        ]);

        $response = $this->withJwtAuth($userUuid, ['warehouses.delete'])
            ->deleteJson("/api/v1/warehouse-locations/{$location->uuid}");

        $response->assertOk()
            ->assertJsonPath('message', 'Warehouse location deleted successfully.');

        $this->assertDatabaseMissing('warehouse_locations', ['id' => $location->id]);
    }

    public function test_warehouse_location_validation_rules(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Mega Depot', 'code' => 'MD-06']);
        BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $userUuid,
            'is_owner' => true,
            'status' => 'active',
        ]);

        $warehouse = Warehouse::create([
            'business_id' => $business->id,
            'code' => 'WH-LOC-06',
            'name' => 'Depot 6',
        ]);

        WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'EXISTING-LOC',
        ]);

        // Code is required
        $res1 = $this->withJwtAuth($userUuid, ['warehouses.create'])
            ->postJson("/api/v1/warehouses/{$warehouse->uuid}/locations", []);
        $res1->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        // Duplicate code in same warehouse is rejected
        $res2 = $this->withJwtAuth($userUuid, ['warehouses.create'])
            ->postJson("/api/v1/warehouses/{$warehouse->uuid}/locations", [
                'code' => 'EXISTING-LOC',
            ]);
        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        // Invalid status value rejected
        $res3 = $this->withJwtAuth($userUuid, ['warehouses.create'])
            ->postJson("/api/v1/warehouses/{$warehouse->uuid}/locations", [
                'code' => 'LOC-NEW',
                'status' => 'invalid_status',
            ]);
        $res3->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_location_unauthenticated_request_returns_401(): void
    {
        $business = Business::create(['name' => 'Mega Depot', 'code' => 'MD-UNAUTH']);
        $warehouse = Warehouse::create([
            'business_id' => $business->id,
            'code' => 'WH-LOC-UNAUTH',
            'name' => 'Unauth Warehouse',
        ]);
        $location = WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'LOC-UNAUTH',
        ]);

        $response = $this->getJson("/api/v1/warehouse-locations/{$location->uuid}");
        $response->assertUnauthorized();
    }

    public function test_location_request_without_permission_returns_403(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Mega Depot', 'code' => 'MD-07']);
        BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $userUuid,
            'is_owner' => true,
            'status' => 'active',
        ]);

        $warehouse = Warehouse::create([
            'business_id' => $business->id,
            'code' => 'WH-LOC-07',
            'name' => 'Depot 7',
        ]);

        $location = WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'LOC-PERM',
        ]);

        $response = $this->withJwtAuth($userUuid, ['other.permission'])
            ->getJson("/api/v1/warehouse-locations/{$location->uuid}");

        $response->assertForbidden();
    }

    public function test_location_request_for_non_existent_location_returns_404(): void
    {
        $userUuid = (string) Str::uuid();
        $fakeUuid = (string) Str::uuid();

        $response = $this->withJwtAuth($userUuid, ['warehouses.view'])
            ->getJson("/api/v1/warehouse-locations/{$fakeUuid}");

        $response->assertNotFound();
    }
}

<?php

namespace Tests\Feature\Security;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\BusinessUserOutlet;
use App\Models\Outlet;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WarehouseSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_bola_user_cannot_view_another_business_warehouse(): void
    {
        $userA = (string) Str::uuid();
        $userB = (string) Str::uuid();

        $businessA = Business::create(['name' => 'Business A', 'code' => 'BIZ-A']);
        BusinessUser::create(['business_id' => $businessA->id, 'user_uuid' => $userA, 'is_owner' => true, 'status' => 'active']);

        $businessB = Business::create(['name' => 'Business B', 'code' => 'BIZ-B']);
        BusinessUser::create(['business_id' => $businessB->id, 'user_uuid' => $userB, 'is_owner' => true, 'status' => 'active']);

        $warehouseB = Warehouse::create([
            'business_id' => $businessB->id,
            'code' => 'WH-B',
            'name' => 'Business B Warehouse',
        ]);

        // User A attempts to access Warehouse B
        $response = $this->withJwtAuth($userA, ['warehouses.view'])
            ->getJson("/api/v1/warehouses/{$warehouseB->uuid}");

        $response->assertForbidden();
    }

    public function test_bola_user_cannot_update_another_business_warehouse(): void
    {
        $userA = (string) Str::uuid();
        $userB = (string) Str::uuid();

        $businessA = Business::create(['name' => 'Business A', 'code' => 'BIZ-A']);
        BusinessUser::create(['business_id' => $businessA->id, 'user_uuid' => $userA, 'is_owner' => true, 'status' => 'active']);

        $businessB = Business::create(['name' => 'Business B', 'code' => 'BIZ-B']);
        BusinessUser::create(['business_id' => $businessB->id, 'user_uuid' => $userB, 'is_owner' => true, 'status' => 'active']);

        $warehouseB = Warehouse::create([
            'business_id' => $businessB->id,
            'code' => 'WH-B',
            'name' => 'Business B Warehouse',
        ]);

        // User A attempts to update Warehouse B
        $response = $this->withJwtAuth($userA, ['warehouses.update'])
            ->putJson("/api/v1/warehouses/{$warehouseB->uuid}", [
                'name' => 'Hacked Name',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('warehouses', [
            'id' => $warehouseB->id,
            'name' => 'Hacked Name',
        ]);
    }

    public function test_bola_user_cannot_delete_another_business_warehouse(): void
    {
        $userA = (string) Str::uuid();
        $userB = (string) Str::uuid();

        $businessA = Business::create(['name' => 'Business A', 'code' => 'BIZ-A']);
        BusinessUser::create(['business_id' => $businessA->id, 'user_uuid' => $userA, 'is_owner' => true, 'status' => 'active']);

        $businessB = Business::create(['name' => 'Business B', 'code' => 'BIZ-B']);
        BusinessUser::create(['business_id' => $businessB->id, 'user_uuid' => $userB, 'is_owner' => true, 'status' => 'active']);

        $warehouseB = Warehouse::create([
            'business_id' => $businessB->id,
            'code' => 'WH-B',
            'name' => 'Business B Warehouse',
        ]);

        // User A attempts to delete Warehouse B
        $response = $this->withJwtAuth($userA, ['warehouses.delete'])
            ->deleteJson("/api/v1/warehouses/{$warehouseB->uuid}");

        $response->assertForbidden();

        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouseB->id,
        ]);
    }

    public function test_bola_user_cannot_create_location_in_another_business_warehouse(): void
    {
        $userA = (string) Str::uuid();
        $userB = (string) Str::uuid();

        $businessA = Business::create(['name' => 'Business A', 'code' => 'BIZ-A']);
        BusinessUser::create(['business_id' => $businessA->id, 'user_uuid' => $userA, 'is_owner' => true, 'status' => 'active']);

        $businessB = Business::create(['name' => 'Business B', 'code' => 'BIZ-B']);
        BusinessUser::create(['business_id' => $businessB->id, 'user_uuid' => $userB, 'is_owner' => true, 'status' => 'active']);

        $warehouseB = Warehouse::create([
            'business_id' => $businessB->id,
            'code' => 'WH-B',
            'name' => 'Business B Warehouse',
        ]);

        // User A attempts to add location to Warehouse B
        $response = $this->withJwtAuth($userA, ['warehouses.create'])
            ->postJson("/api/v1/warehouses/{$warehouseB->uuid}/locations", [
                'code' => 'ROGUE-LOC-1',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('warehouse_locations', [
            'code' => 'ROGUE-LOC-1',
        ]);
    }

    public function test_bola_user_cannot_view_or_modify_another_business_warehouse_location(): void
    {
        $userA = (string) Str::uuid();
        $userB = (string) Str::uuid();

        $businessA = Business::create(['name' => 'Business A', 'code' => 'BIZ-A']);
        BusinessUser::create(['business_id' => $businessA->id, 'user_uuid' => $userA, 'is_owner' => true, 'status' => 'active']);

        $businessB = Business::create(['name' => 'Business B', 'code' => 'BIZ-B']);
        BusinessUser::create(['business_id' => $businessB->id, 'user_uuid' => $userB, 'is_owner' => true, 'status' => 'active']);

        $warehouseB = Warehouse::create([
            'business_id' => $businessB->id,
            'code' => 'WH-B',
            'name' => 'Business B Warehouse',
        ]);

        $locB = WarehouseLocation::create([
            'warehouse_id' => $warehouseB->id,
            'code' => 'LOC-B-1',
        ]);

        // View attempt
        $resView = $this->withJwtAuth($userA, ['warehouses.view'])
            ->getJson("/api/v1/warehouse-locations/{$locB->uuid}");
        $resView->assertForbidden();

        // Update attempt
        $resUpdate = $this->withJwtAuth($userA, ['warehouses.update'])
            ->putJson("/api/v1/warehouse-locations/{$locB->uuid}", [
                'description' => 'Tampered description',
            ]);
        $resUpdate->assertForbidden();

        // Delete attempt
        $resDelete = $this->withJwtAuth($userA, ['warehouses.delete'])
            ->deleteJson("/api/v1/warehouse-locations/{$locB->uuid}");
        $resDelete->assertForbidden();
    }

    public function test_cross_tenant_outlet_association_is_rejected(): void
    {
        $userA = (string) Str::uuid();
        $businessA = Business::create(['name' => 'Business A', 'code' => 'BIZ-A']);
        BusinessUser::create(['business_id' => $businessA->id, 'user_uuid' => $userA, 'is_owner' => true, 'status' => 'active']);

        $businessB = Business::create(['name' => 'Business B', 'code' => 'BIZ-B']);
        $outletB = Outlet::create(['business_id' => $businessB->id, 'code' => 'OUT-B', 'name' => 'Outlet B']);

        // User A tries to create warehouse in Business A referencing Outlet B from Business B
        $response = $this->withJwtAuth($userA, ['warehouses.create'])
            ->postJson("/api/v1/businesses/{$businessA->uuid}/warehouses", [
                'code' => 'WH-A-1',
                'name' => 'Warehouse A',
                'outlet_id' => $outletB->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['outlet_id']);
    }

    public function test_multi_branch_isolation_blocks_unassigned_staff(): void
    {
        $ownerUuid = (string) Str::uuid();
        $staffUuid = (string) Str::uuid();

        $business = Business::create(['name' => 'Multi Branch Biz', 'code' => 'MB-01']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $ownerUuid, 'is_owner' => true, 'status' => 'active']);

        $staffUser = BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $staffUuid,
            'is_owner' => false,
            'role' => 'cashier',
            'status' => 'active',
        ]);

        $outlet1 = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-1', 'name' => 'Branch 1']);
        $outlet2 = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-2', 'name' => 'Branch 2']);

        // Staff assigned only to Branch 1
        BusinessUserOutlet::create([
            'business_user_id' => $staffUser->id,
            'outlet_id' => $outlet1->id,
            'is_primary' => true,
            'is_active' => true,
        ]);

        $whBranch1 = Warehouse::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet1->id,
            'code' => 'WH-B1',
            'name' => 'Branch 1 Warehouse',
        ]);

        $whBranch2 = Warehouse::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet2->id,
            'code' => 'WH-B2',
            'name' => 'Branch 2 Warehouse',
        ]);

        // Staff can access Branch 1 warehouse
        $resAllowed = $this->withJwtAuth($staffUuid, ['warehouses.view'])
            ->getJson("/api/v1/warehouses/{$whBranch1->uuid}");
        $resAllowed->assertOk();

        // Staff is forbidden from accessing Branch 2 warehouse
        $resBlocked = $this->withJwtAuth($staffUuid, ['warehouses.view'])
            ->getJson("/api/v1/warehouses/{$whBranch2->uuid}");
        $resBlocked->assertForbidden();
    }

    public function test_mass_assignment_protection_cannot_overwrite_id_or_uuid(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Acme Corp', 'code' => 'ACME-MA']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $userUuid, 'is_owner' => true, 'status' => 'active']);

        $originalUuid = (string) Str::uuid();
        $warehouse = Warehouse::create([
            'uuid' => $originalUuid,
            'business_id' => $business->id,
            'code' => 'WH-MA-1',
            'name' => 'Original WH',
        ]);

        $tamperedUuid = (string) Str::uuid();

        $response = $this->withJwtAuth($userUuid, ['warehouses.update'])
            ->putJson("/api/v1/warehouses/{$warehouse->uuid}", [
                'id' => 99999,
                'uuid' => $tamperedUuid,
                'business_id' => 99999,
                'name' => 'Updated WH Name',
            ]);

        $response->assertOk();

        $warehouse->refresh();
        $this->assertEquals($originalUuid, $warehouse->uuid);
        $this->assertEquals($business->id, $warehouse->business_id);
    }

    public function test_sql_injection_payloads_in_filter_and_route_params(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Acme Corp', 'code' => 'ACME-SQLI']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $userUuid, 'is_owner' => true, 'status' => 'active']);

        $sqliFilter = "' OR '1'='1";

        $response = $this->withJwtAuth($userUuid, ['warehouses.view'])
            ->getJson("/api/v1/businesses/{$business->uuid}/warehouses?status=" . urlencode($sqliFilter));

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }
}

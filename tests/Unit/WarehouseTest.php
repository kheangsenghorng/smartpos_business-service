<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_uuid_is_generated_automatically(): void
    {
        $business = Business::create([
            'name' => 'Acme Corp',
            'code' => 'ACME',
        ]);

        $warehouse = Warehouse::create([
            'business_id' => $business->id,
            'code' => 'WH-01',
            'name' => 'Main Warehouse',
        ]);

        $this->assertNotNull($warehouse->uuid);
        $this->assertIsString($warehouse->uuid);
        $this->assertEquals(36, strlen($warehouse->uuid));
    }

    public function test_warehouse_belongs_to_a_business(): void
    {
        $business = Business::create([
            'name' => 'Acme Corp',
            'code' => 'ACME',
        ]);

        $warehouse = Warehouse::create([
            'business_id' => $business->id,
            'code' => 'WH-01',
            'name' => 'Main Warehouse',
        ]);

        $this->assertInstanceOf(Business::class, $warehouse->business);
        $this->assertEquals($business->id, $warehouse->business->id);
        $this->assertTrue($business->warehouses->contains($warehouse));
    }

    public function test_warehouse_can_belong_to_an_outlet(): void
    {
        $business = Business::create([
            'name' => 'Acme Corp',
            'code' => 'ACME',
        ]);

        $outlet = Outlet::create([
            'business_id' => $business->id,
            'code' => 'OUT-01',
            'name' => 'Phnom Penh Branch',
        ]);

        $warehouse = Warehouse::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'code' => 'WH-PHN-01',
            'name' => 'Phnom Penh Outlet Warehouse',
        ]);

        $this->assertInstanceOf(Outlet::class, $warehouse->outlet);
        $this->assertEquals($outlet->id, $warehouse->outlet->id);
        $this->assertTrue($outlet->warehouses->contains($warehouse));
    }

    public function test_central_warehouse_supports_outlet_id_null(): void
    {
        $business = Business::create([
            'name' => 'Acme Corp',
            'code' => 'ACME',
        ]);

        $warehouse = Warehouse::create([
            'business_id' => $business->id,
            'outlet_id' => null,
            'code' => 'WH-CENTRAL',
            'name' => 'Central Hub',
        ]);

        $this->assertNull($warehouse->outlet_id);
        $this->assertNull($warehouse->outlet);
    }

    public function test_warehouse_has_many_warehouse_locations(): void
    {
        $business = Business::create([
            'name' => 'Acme Corp',
            'code' => 'ACME',
        ]);

        $warehouse = Warehouse::create([
            'business_id' => $business->id,
            'code' => 'WH-01',
            'name' => 'Main Warehouse',
        ]);

        $loc1 = WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'LOC-A1',
            'zone' => 'Zone A',
            'aisle' => 'Aisle 1',
        ]);

        $loc2 = WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'LOC-A2',
            'zone' => 'Zone A',
            'aisle' => 'Aisle 2',
        ]);

        $this->assertCount(2, $warehouse->locations);
        $this->assertTrue($warehouse->locations->contains($loc1));
        $this->assertTrue($warehouse->locations->contains($loc2));
    }

    public function test_warehouse_status_defaults_to_active(): void
    {
        $business = Business::create([
            'name' => 'Acme Corp',
            'code' => 'ACME',
        ]);

        $warehouse = Warehouse::create([
            'business_id' => $business->id,
            'code' => 'WH-DEFAULT',
            'name' => 'Default Status Warehouse',
        ]);

        $this->assertEquals('active', $warehouse->status);
    }

    public function test_duplicate_warehouse_code_is_rejected_inside_same_business(): void
    {
        $business = Business::create([
            'name' => 'Acme Corp',
            'code' => 'ACME',
        ]);

        Warehouse::create([
            'business_id' => $business->id,
            'code' => 'WH-DUP',
            'name' => 'Warehouse 1',
        ]);

        $this->expectException(QueryException::class);

        Warehouse::create([
            'business_id' => $business->id,
            'code' => 'WH-DUP',
            'name' => 'Warehouse 2',
        ]);
    }

    public function test_same_warehouse_code_can_be_used_by_different_businesses(): void
    {
        $businessA = Business::create([
            'name' => 'Business A',
            'code' => 'BIZ-A',
        ]);

        $businessB = Business::create([
            'name' => 'Business B',
            'code' => 'BIZ-B',
        ]);

        $warehouseA = Warehouse::create([
            'business_id' => $businessA->id,
            'code' => 'WH-COMMON',
            'name' => 'Biz A Warehouse',
        ]);

        $warehouseB = Warehouse::create([
            'business_id' => $businessB->id,
            'code' => 'WH-COMMON',
            'name' => 'Biz B Warehouse',
        ]);

        $this->assertEquals('WH-COMMON', $warehouseA->code);
        $this->assertEquals('WH-COMMON', $warehouseB->code);
        $this->assertNotEquals($warehouseA->business_id, $warehouseB->business_id);
    }

    public function test_deleting_a_warehouse_removes_its_warehouse_locations(): void
    {
        $business = Business::create([
            'name' => 'Acme Corp',
            'code' => 'ACME',
        ]);

        $warehouse = Warehouse::create([
            'business_id' => $business->id,
            'code' => 'WH-CASCADE',
            'name' => 'Cascade Warehouse',
        ]);

        $location = WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'LOC-DEL',
        ]);

        $this->assertDatabaseHas('warehouse_locations', ['id' => $location->id]);

        $warehouse->delete();

        $this->assertDatabaseMissing('warehouses', ['id' => $warehouse->id]);
        $this->assertDatabaseMissing('warehouse_locations', ['id' => $location->id]);
    }

    public function test_deleting_an_outlet_sets_warehouses_outlet_id_to_null(): void
    {
        $business = Business::create([
            'name' => 'Acme Corp',
            'code' => 'ACME',
        ]);

        $outlet = Outlet::create([
            'business_id' => $business->id,
            'code' => 'OUT-TO-DELETE',
            'name' => 'Temporary Outlet',
        ]);

        $warehouse = Warehouse::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'code' => 'WH-NULL-ON-DELETE',
            'name' => 'Preserved Warehouse',
        ]);

        $this->assertEquals($outlet->id, $warehouse->outlet_id);

        $outlet->delete();

        $warehouse->refresh();
        $this->assertNull($warehouse->outlet_id);
        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id]);
    }
}

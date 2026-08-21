<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_location_uuid_is_generated_automatically(): void
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

        $location = WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'LOC-01',
            'zone' => 'Zone A',
            'aisle' => 'Aisle 01',
            'rack' => 'Rack R01',
            'shelf' => 'Shelf S01',
            'bin' => 'Bin B01',
        ]);

        $this->assertNotNull($location->uuid);
        $this->assertIsString($location->uuid);
        $this->assertEquals(36, strlen($location->uuid));
    }

    public function test_warehouse_location_belongs_to_a_warehouse(): void
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

        $location = WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'LOC-01',
        ]);

        $this->assertInstanceOf(Warehouse::class, $location->warehouse);
        $this->assertEquals($warehouse->id, $location->warehouse->id);
    }

    public function test_warehouse_location_status_defaults_to_active(): void
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

        $location = WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'LOC-DEF',
        ]);

        $this->assertEquals('active', $location->status);
    }

    public function test_duplicate_location_code_is_rejected_inside_same_warehouse(): void
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

        WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'LOC-DUP',
        ]);

        $this->expectException(QueryException::class);

        WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'LOC-DUP',
        ]);
    }

    public function test_same_location_code_can_be_used_in_different_warehouses(): void
    {
        $business = Business::create([
            'name' => 'Acme Corp',
            'code' => 'ACME',
        ]);

        $wh1 = Warehouse::create([
            'business_id' => $business->id,
            'code' => 'WH-1',
            'name' => 'Warehouse 1',
        ]);

        $wh2 = Warehouse::create([
            'business_id' => $business->id,
            'code' => 'WH-2',
            'name' => 'Warehouse 2',
        ]);

        $loc1 = WarehouseLocation::create([
            'warehouse_id' => $wh1->id,
            'code' => 'A-01',
        ]);

        $loc2 = WarehouseLocation::create([
            'warehouse_id' => $wh2->id,
            'code' => 'A-01',
        ]);

        $this->assertEquals('A-01', $loc1->code);
        $this->assertEquals('A-01', $loc2->code);
        $this->assertNotEquals($loc1->warehouse_id, $loc2->warehouse_id);
    }
}

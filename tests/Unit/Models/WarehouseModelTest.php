<?php

namespace Tests\Unit\Models;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_attributes_and_relationships(): void
    {
        $business = Business::create([
            'name' => 'Tech Retailer',
            'code' => 'TR-01',
        ]);

        $outlet = Outlet::create([
            'business_id' => $business->id,
            'code' => 'OUT-01',
            'name' => 'Main Outlet',
        ]);

        $warehouse = Warehouse::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'code' => 'WH-TR-01',
            'name' => 'Main Depot',
            'address' => 'Building 4, Logistics Park',
            'status' => 'active',
        ]);

        $this->assertEquals('uuid', $warehouse->getRouteKeyName());
        $this->assertNotNull($warehouse->uuid);
        $this->assertEquals($business->id, $warehouse->business->id);
        $this->assertEquals($outlet->id, $warehouse->outlet->id);

        $location = WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'LOC-Z1-A1',
            'zone' => 'Z1',
            'aisle' => 'A1',
            'rack' => 'R1',
            'shelf' => 'S1',
            'bin' => 'B1',
            'description' => 'Top shelf bin 1',
        ]);

        $this->assertEquals('uuid', $location->getRouteKeyName());
        $this->assertNotNull($location->uuid);
        $this->assertEquals($warehouse->id, $location->warehouse->id);
        $this->assertTrue($warehouse->locations->contains($location));
    }
}

<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\StoreBusinessRequest;
use App\Http\Requests\StoreOutletRequest;
use App\Http\Requests\UpdateBusinessRequest;
use App\Http\Requests\UpdateOutletRequest;
use App\Models\Business;
use App\Models\Outlet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class BusinessAndOutletRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_business_request_validation(): void
    {
        $request = new StoreBusinessRequest();
        $this->assertTrue($request->authorize());

        // 1. Missing required fields (name, code)
        $validator = Validator::make([], $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
        $this->assertArrayHasKey('code', $validator->errors()->toArray());

        // 2. Valid payload
        $validData = [
            'name' => 'Acme Mart',
            'code' => 'ACME-01',
            'email' => 'contact@acme.test',
            'tax_rate' => 10.0,
            'is_tax_inclusive' => true,
            'status' => 'active',
        ];
        $validator = Validator::make($validData, $request->rules());
        $this->assertFalse($validator->fails());

        // 3. Duplicate code fails unique validation
        Business::create(['name' => 'Existing', 'code' => 'ACME-01']);
        $validator = Validator::make($validData, $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('code', $validator->errors()->toArray());

        // 4. Invalid tax_rate > 100 or < 0
        $invalidTax = array_merge($validData, ['code' => 'ACME-02', 'tax_rate' => 150]);
        $validator = Validator::make($invalidTax, $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('tax_rate', $validator->errors()->toArray());

        // 5. Invalid status
        $invalidStatus = array_merge($validData, ['code' => 'ACME-03', 'status' => 'unknown_status']);
        $validator = Validator::make($invalidStatus, $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('status', $validator->errors()->toArray());
    }

    public function test_update_business_request_validation(): void
    {
        $business = Business::create(['name' => 'Original', 'code' => 'ORIG-01']);

        $request = new UpdateBusinessRequest();
        $request->setRouteResolver(function () use ($business) {
            $route = new \Illuminate\Routing\Route('PUT', 'api/v1/businesses/{business}', []);
            $route->parameters = ['business' => $business];
            return $route;
        });

        // 1. Updating name without changing code should pass (unique ignores self)
        $validator = Validator::make([
            'name' => 'Updated Name',
            'code' => 'ORIG-01',
        ], $request->rules());
        $this->assertFalse($validator->fails());

        // 2. Partial updates (sometimes rule)
        $validator = Validator::make([
            'description' => 'Updated Description',
        ], $request->rules());
        $this->assertFalse($validator->fails());
    }

    public function test_store_outlet_request_validation(): void
    {
        $business = Business::create(['name' => 'Biz Outlet', 'code' => 'BIZ-OUT']);

        $request = new StoreOutletRequest();
        $request->setRouteResolver(function () use ($business) {
            $route = new \Illuminate\Routing\Route('POST', 'api/v1/businesses/{business}/outlets', []);
            $route->parameters = ['business' => $business];
            return $route;
        });

        $this->assertTrue($request->authorize());

        // 1. Missing required fields (code, name)
        $validator = Validator::make([], $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('code', $validator->errors()->toArray());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());

        // 2. Valid payload
        $validData = [
            'code' => 'OUT-101',
            'name' => 'Downtown Outlet',
            'tax_rate' => 5.00,
            'is_main_outlet' => false,
            'status' => 'active',
        ];
        $validator = Validator::make($validData, $request->rules());
        $this->assertFalse($validator->fails());

        // 3. Duplicate code in same business fails
        Outlet::create([
            'business_id' => $business->id,
            'code' => 'OUT-101',
            'name' => 'Existing Outlet',
        ]);
        $validator = Validator::make($validData, $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('code', $validator->errors()->toArray());
    }

    public function test_update_outlet_request_validation(): void
    {
        $business = Business::create(['name' => 'Biz Outlet Upd', 'code' => 'BIZ-OUPD']);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-UP1', 'name' => 'Branch 1']);

        $request = new UpdateOutletRequest();
        $request->setRouteResolver(function () use ($outlet) {
            $route = new \Illuminate\Routing\Route('PUT', 'api/v1/outlets/{outlet}', []);
            $route->parameters = ['outlet' => $outlet];
            return $route;
        });

        $this->assertTrue($request->authorize());

        // Update name keeping existing code
        $validator = Validator::make([
            'name' => 'Updated Branch Name',
            'code' => 'OUT-UP1',
        ], $request->rules());
        $this->assertFalse($validator->fails());
    }
}

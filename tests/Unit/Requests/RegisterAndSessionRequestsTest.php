<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\CloseRegisterSessionRequest;
use App\Http\Requests\OpenRegisterSessionRequest;
use App\Http\Requests\RecordCashMovementRequest;
use App\Http\Requests\StartCashierSessionRequest;
use App\Http\Requests\StorePosDeviceRequest;
use App\Http\Requests\StoreRegisterRequest;
use App\Models\Business;
use App\Models\Outlet;
use App\Models\PosDevice;
use App\Models\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegisterAndSessionRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_register_request_validation(): void
    {
        $business = Business::create(['name' => 'Biz Reg Req', 'code' => 'BR-REQ']);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-REQ1', 'name' => 'Outlet 1']);

        $request = new StoreRegisterRequest();
        $request->setRouteResolver(function () use ($outlet) {
            $route = new \Illuminate\Routing\Route('POST', 'api/v1/outlets/{outlet}/registers', []);
            $route->parameters = ['outlet' => $outlet];
            return $route;
        });

        $this->assertTrue($request->authorize());

        // 1. Missing code/name fails
        $validator = Validator::make([], $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('code', $validator->errors()->toArray());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());

        // 2. Valid data passes
        $validData = [
            'code' => 'REG-101',
            'name' => 'Front Counter',
            'default_cash_amount' => 100.00,
            'is_cash_drawer_connected' => true,
            'status' => 'active',
        ];
        $validator = Validator::make($validData, $request->rules());
        $this->assertFalse($validator->fails());

        // 3. Duplicate code in same outlet fails
        Register::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'code' => 'REG-101',
            'name' => 'Existing Reg',
        ]);
        $validator = Validator::make($validData, $request->rules());
        $this->assertTrue($validator->fails());
    }

    public function test_store_pos_device_request_validation(): void
    {
        $business = Business::create(['name' => 'Biz Dev Req', 'code' => 'BD-REQ']);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-DEV1', 'name' => 'Outlet 1']);
        $register = Register::create(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'code' => 'REG-D1', 'name' => 'Reg']);

        $request = new StorePosDeviceRequest();
        $this->assertTrue($request->authorize());

        // 1. Missing machine_id / device_name fails
        $validator = Validator::make([], $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('machine_id', $validator->errors()->toArray());
        $this->assertArrayHasKey('device_name', $validator->errors()->toArray());

        // 2. Valid data
        $validData = [
            'machine_id' => 'MACH-DEV-101',
            'device_name' => 'Main Tablet',
            'device_type' => 'pos_terminal',
            'platform' => 'android',
            'register_uuid' => $register->uuid,
        ];
        $validator = Validator::make($validData, $request->rules());
        $this->assertFalse($validator->fails());

        // 3. Duplicate machine_id fails
        PosDevice::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'register_id' => $register->id,
            'machine_id' => 'MACH-DEV-101',
            'device_code' => 'DEV-EXISTING',
        ]);
        $validator = Validator::make($validData, $request->rules());
        $this->assertTrue($validator->fails());
    }

    public function test_start_cashier_session_request_validation(): void
    {
        $business = Business::create(['name' => 'Biz CS Req', 'code' => 'BCS-REQ']);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-CS1', 'name' => 'Outlet']);
        $register = Register::create(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'code' => 'REG-CS1', 'name' => 'Reg']);
        $device = PosDevice::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'register_id' => $register->id,
            'device_code' => 'DEV-CS1',
            'machine_id' => 'MACH-CS1',
        ]);

        $request = new StartCashierSessionRequest();
        $this->assertTrue($request->authorize());

        // Missing fields fail
        $validator = Validator::make([], $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('register_uuid', $validator->errors()->toArray());
        $this->assertArrayHasKey('pos_device_uuid', $validator->errors()->toArray());
        $this->assertArrayHasKey('user_uuid', $validator->errors()->toArray());

        // Valid payload
        $validData = [
            'register_uuid' => $register->uuid,
            'pos_device_uuid' => $device->uuid,
            'user_uuid' => (string) Str::uuid(),
        ];
        $validator = Validator::make($validData, $request->rules());
        $this->assertFalse($validator->fails());
    }

    public function test_register_session_and_cash_movement_requests_validation(): void
    {
        // OpenRegisterSessionRequest
        $openReq = new OpenRegisterSessionRequest();
        $this->assertTrue($openReq->authorize());

        $valOpen = Validator::make(['opening_cash' => 50.00], $openReq->rules());
        $this->assertFalse($valOpen->fails());

        $valOpenInvalid = Validator::make(['opening_cash' => -10], $openReq->rules());
        $this->assertTrue($valOpenInvalid->fails());

        // CloseRegisterSessionRequest
        $closeReq = new CloseRegisterSessionRequest();
        $this->assertTrue($closeReq->authorize());

        $valClose = Validator::make(['closing_cash' => 120.00], $closeReq->rules());
        $this->assertFalse($valClose->fails());

        // RecordCashMovementRequest
        $moveReq = new RecordCashMovementRequest();
        $this->assertTrue($moveReq->authorize());

        $valMove = Validator::make([
            'type' => 'cash_in',
            'amount' => 50.00,
            'reason' => 'Initial float addition',
        ], $moveReq->rules());
        $this->assertFalse($valMove->fails());

        $valMoveInvalidType = Validator::make([
            'type' => 'invalid_cash_type',
            'amount' => 50.00,
        ], $moveReq->rules());
        $this->assertTrue($valMoveInvalidType->fails());
    }
}

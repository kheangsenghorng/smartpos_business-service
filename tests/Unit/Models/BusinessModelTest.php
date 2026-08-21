<?php

namespace Tests\Unit\Models;

use App\Models\Business;
use App\Models\BusinessSetting;
use App\Models\BusinessUser;
use App\Models\CashDrawerMovement;
use App\Models\CashDrawerSession;
use App\Models\CashierSession;
use App\Models\Outlet;
use App\Models\PosDevice;
use App\Models\Register;
use App\Models\RegisterSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_attributes_casting_and_route_key(): void
    {
        $business = Business::create([
            'name' => 'Tech Corp',
            'code' => 'TC-01',
            'is_tax_inclusive' => 1,
            'tax_rate' => '7.50',
        ]);

        $this->assertIsBool($business->is_tax_inclusive);
        $this->assertTrue($business->is_tax_inclusive);
        $this->assertEquals('7.50', (string) $business->tax_rate);
        $this->assertEquals('uuid', $business->getRouteKeyName());
        $this->assertNotNull($business->uuid);
    }

    public function test_business_relationships(): void
    {
        $business = Business::create([
            'name' => 'Mega Retail',
            'code' => 'MR-01',
        ]);

        // settings
        BusinessSetting::create(['business_id' => $business->id, 'currency_code' => 'USD']);
        $this->assertInstanceOf(BusinessSetting::class, $business->settings);

        // businessUsers
        $user = BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => 'user-uuid-1',
            'role' => 'admin',
        ]);
        $this->assertTrue($business->businessUsers->contains($user));

        // outlets
        $outlet = Outlet::create([
            'business_id' => $business->id,
            'code' => 'OUT-1',
            'name' => 'Downtown Branch',
        ]);
        $this->assertTrue($business->outlets->contains($outlet));

        // registers
        $register = Register::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'code' => 'REG-1',
            'name' => 'Counter 1',
        ]);
        $this->assertTrue($business->registers->contains($register));

        // posDevices
        $device = PosDevice::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'register_id' => $register->id,
            'device_code' => 'DEV-1',
            'machine_id' => 'MACH-1',
        ]);
        $this->assertTrue($business->posDevices->contains($device));

        // cashierSessions
        $cashierSession = CashierSession::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'register_id' => $register->id,
            'pos_device_id' => $device->id,
            'business_user_id' => $user->id,
            'user_uuid' => 'user-uuid-1',
            'status' => 'active',
            'started_at' => now(),
        ]);
        $this->assertTrue($business->cashierSessions->contains($cashierSession));

        // registerSessions
        $regSession = RegisterSession::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'register_id' => $register->id,
            'pos_device_id' => $device->id,
            'opened_by_user_uuid' => 'user-uuid-1',
            'opening_cash' => 50.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);
        $this->assertTrue($business->registerSessions->contains($regSession));

        // cashDrawerSessions
        $drawerSession = CashDrawerSession::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'register_id' => $register->id,
            'register_session_id' => $regSession->id,
            'opening_amount' => 50.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);
        $this->assertTrue($business->cashDrawerSessions->contains($drawerSession));

        // cashDrawerMovements
        $movement = CashDrawerMovement::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'register_id' => $register->id,
            'cash_drawer_session_id' => $drawerSession->id,
            'user_uuid' => 'user-uuid-1',
            'type' => 'pay_in',
            'amount' => 20.00,
            'created_at' => now(),
        ]);
        $this->assertTrue($business->cashDrawerMovements->contains($movement));
    }
}

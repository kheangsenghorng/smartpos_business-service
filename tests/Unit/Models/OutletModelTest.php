<?php

namespace Tests\Unit\Models;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\BusinessUserOutlet;
use App\Models\CashDrawerMovement;
use App\Models\CashDrawerSession;
use App\Models\CashierSession;
use App\Models\Outlet;
use App\Models\PosDevice;
use App\Models\Register;
use App\Models\RegisterSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutletModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_outlet_casts_and_route_key(): void
    {
        $business = Business::create(['name' => 'Store', 'code' => 'ST-01']);

        $outlet = Outlet::create([
            'business_id' => $business->id,
            'code' => 'OUT-1',
            'name' => 'Branch 1',
            'is_main_outlet' => 1,
            'is_active' => 1,
            'tax_rate' => '10.50',
            'latitude' => '11.55637380',
            'longitude' => '104.92820990',
        ]);

        $this->assertIsBool($outlet->is_main_outlet);
        $this->assertTrue($outlet->is_main_outlet);
        $this->assertIsBool($outlet->is_active);
        $this->assertTrue($outlet->is_active);
        $this->assertEquals('10.50', (string) $outlet->tax_rate);
        $this->assertEquals('uuid', $outlet->getRouteKeyName());
        $this->assertNotNull($outlet->uuid);
    }

    public function test_outlet_relationships(): void
    {
        $business = Business::create(['name' => 'Store B', 'code' => 'STB-01']);

        $outlet = Outlet::create([
            'business_id' => $business->id,
            'code' => 'OUT-B',
            'name' => 'North Branch',
        ]);

        $this->assertEquals($business->id, $outlet->business->id);

        $user = BusinessUser::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'user_uuid' => 'user-outlet-1',
            'role' => 'cashier',
        ]);

        $userOutlet = BusinessUserOutlet::create([
            'business_user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'is_primary' => true,
        ]);

        $this->assertTrue($outlet->businessUsers->contains($user));
        $this->assertTrue($outlet->businessUserOutlets->contains($userOutlet));
        $this->assertTrue($outlet->assignedUsers->contains($user));

        $register = Register::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'code' => 'REG-B1',
            'name' => 'Checkout B1',
        ]);
        $this->assertTrue($outlet->registers->contains($register));

        $posDevice = PosDevice::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'register_id' => $register->id,
            'device_code' => 'DEV-B1',
            'machine_id' => 'MACH-B1',
        ]);
        $this->assertTrue($outlet->posDevices->contains($posDevice));

        $cashierSession = CashierSession::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'register_id' => $register->id,
            'pos_device_id' => $posDevice->id,
            'business_user_id' => $user->id,
            'user_uuid' => 'user-outlet-1',
            'status' => 'active',
            'started_at' => now(),
        ]);
        $this->assertTrue($outlet->cashierSessions->contains($cashierSession));

        $regSession = RegisterSession::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'register_id' => $register->id,
            'pos_device_id' => $posDevice->id,
            'opened_by_user_uuid' => 'user-outlet-1',
            'opening_cash' => 100.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);
        $this->assertTrue($outlet->registerSessions->contains($regSession));

        $drawerSession = CashDrawerSession::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'register_id' => $register->id,
            'register_session_id' => $regSession->id,
            'opening_amount' => 100.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);
        $this->assertTrue($outlet->cashDrawerSessions->contains($drawerSession));

        $movement = CashDrawerMovement::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'register_id' => $register->id,
            'cash_drawer_session_id' => $drawerSession->id,
            'user_uuid' => 'user-outlet-1',
            'type' => 'pay_out',
            'amount' => 15.00,
            'created_at' => now(),
        ]);
        $this->assertTrue($outlet->cashDrawerMovements->contains($movement));
    }
}

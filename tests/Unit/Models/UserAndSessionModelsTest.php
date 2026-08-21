<?php

namespace Tests\Unit\Models;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\CashDrawerMovement;
use App\Models\CashDrawerSession;
use App\Models\CashierProfile;
use App\Models\CashierSession;
use App\Models\Outlet;
use App\Models\PosDevice;
use App\Models\Register;
use App\Models\RegisterSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAndSessionModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_user_casts_and_hidden_attributes(): void
    {
        $business = Business::create(['name' => 'Biz User Test', 'code' => 'BUT-01']);

        $user = BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => 'user-uuid-xyz',
            'role' => 'manager',
            'is_owner' => 0,
            'is_active' => 1,
            'pin_code_hash' => 'secret_pin_hash',
            'joined_at' => now(),
        ]);

        $this->assertIsBool($user->is_owner);
        $this->assertFalse($user->is_owner);
        $this->assertIsBool($user->is_active);
        $this->assertTrue($user->is_active);
        $this->assertArrayNotHasKey('pin_code_hash', $user->toArray());
        $this->assertEquals('uuid', $user->getRouteKeyName());

        $profile = CashierProfile::create([
            'business_user_id' => $user->id,
            'display_name' => 'Manager John',
            'can_sell' => true,
            'can_refund' => true,
            'can_discount' => true,
            'max_discount_percent' => 25.00,
        ]);

        $this->assertInstanceOf(CashierProfile::class, $user->cashierProfile);
        $this->assertEquals($user->id, $profile->businessUser->id);
    }

    public function test_session_lifecycle_models_and_casts(): void
    {
        $business = Business::create(['name' => 'Session Biz', 'code' => 'SESS-BIZ']);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-SESS', 'name' => 'Session Branch']);
        $register = Register::create(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'code' => 'REG-SESS', 'name' => 'Station']);
        $device = PosDevice::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'register_id' => $register->id,
            'device_code' => 'DEV-SESS',
            'machine_id' => 'MACH-SESS',
        ]);
        $user = BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => 'cashier-uuid-1',
            'role' => 'cashier',
        ]);

        // CashierSession
        $cashierSession = CashierSession::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'register_id' => $register->id,
            'pos_device_id' => $device->id,
            'business_user_id' => $user->id,
            'user_uuid' => 'cashier-uuid-1',
            'status' => 'active',
            'started_at' => now(),
            'last_activity_at' => now(),
            'locked_at' => now(),
            'ended_at' => now(),
        ]);

        $this->assertEquals($business->id, $cashierSession->business->id);
        $this->assertEquals($outlet->id, $cashierSession->outlet->id);
        $this->assertEquals($register->id, $cashierSession->register->id);
        $this->assertEquals($device->id, $cashierSession->posDevice->id);
        $this->assertEquals($user->id, $cashierSession->businessUser->id);

        // RegisterSession
        $regSession = RegisterSession::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'register_id' => $register->id,
            'pos_device_id' => $device->id,
            'opened_by_user_uuid' => 'cashier-uuid-1',
            'closed_by_user_uuid' => 'cashier-uuid-1',
            'opening_cash' => 100.00,
            'expected_cash' => 150.00,
            'closing_cash' => 150.00,
            'difference_amount' => 0.00,
            'status' => 'closed',
            'opened_at' => now(),
            'closed_at' => now(),
        ]);

        $this->assertEquals('100.00', (string) $regSession->opening_cash);
        $this->assertEquals('150.00', (string) $regSession->expected_cash);
        $this->assertEquals('0.00', (string) $regSession->difference_amount);
        $this->assertEquals($business->id, $regSession->business->id);

        // CashDrawerSession
        $drawerSession = CashDrawerSession::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'register_id' => $register->id,
            'register_session_id' => $regSession->id,
            'opening_amount' => 100.00,
            'expected_amount' => 120.00,
            'counted_amount' => 120.00,
            'difference_amount' => 0.00,
            'status' => 'closed',
            'opened_at' => now(),
            'closed_at' => now(),
        ]);

        $this->assertEquals($regSession->id, $drawerSession->registerSession->id);
        $this->assertEquals($business->id, $drawerSession->business->id);
        $this->assertEquals($outlet->id, $drawerSession->outlet->id);
        $this->assertEquals($register->id, $drawerSession->register->id);

        // CashDrawerMovement
        $movement = CashDrawerMovement::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'register_id' => $register->id,
            'cash_drawer_session_id' => $drawerSession->id,
            'user_uuid' => 'cashier-uuid-1',
            'type' => 'pay_in',
            'amount' => 20.00,
            'reason' => 'Change replenishment',
        ]);

        $this->assertEquals($drawerSession->id, $movement->cashDrawerSession->id);
        $this->assertEquals($business->id, $movement->business->id);
        $this->assertEquals($outlet->id, $movement->outlet->id);
        $this->assertEquals($register->id, $movement->register->id);
    }
}

<?php

namespace Tests\Unit\Models;

use App\Models\Business;
use App\Models\DeviceSession;
use App\Models\Outlet;
use App\Models\PosDevice;
use App\Models\PosDeviceCredential;
use App\Models\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosAndRegisterModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_attributes_and_relationships(): void
    {
        $business = Business::create(['name' => 'Biz', 'code' => 'BIZ-REG']);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-REG', 'name' => 'Reg Branch']);

        $register = Register::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'code' => 'REG-101',
            'name' => 'Station 101',
            'default_cash_amount' => '150.00',
            'is_cash_drawer_connected' => 1,
            'is_active' => 1,
        ]);

        $this->assertIsBool($register->is_active);
        $this->assertTrue($register->is_active);
        $this->assertIsBool($register->is_cash_drawer_connected);
        $this->assertTrue($register->is_cash_drawer_connected);
        $this->assertEquals('150.00', (string) $register->default_cash_amount);
        $this->assertEquals('uuid', $register->getRouteKeyName());

        $this->assertEquals($business->id, $register->business->id);
        $this->assertEquals($outlet->id, $register->outlet->id);
    }

    public function test_pos_device_attributes_credentials_and_relationships(): void
    {
        $business = Business::create(['name' => 'Biz POS', 'code' => 'BIZ-POS']);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-POS', 'name' => 'POS Branch']);
        $register = Register::create(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'code' => 'REG-POS', 'name' => 'Station']);

        $device = PosDevice::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'register_id' => $register->id,
            'device_code' => 'DEV-POS-1',
            'machine_id' => 'MACH-POS-1',
            'name' => 'Counter Terminal',
            'machine_password_hash' => 'secret_hash_value',
            'registered_at' => now(),
            'activated_at' => now(),
        ]);

        $this->assertEquals('uuid', $device->getRouteKeyName());
        $this->assertArrayNotHasKey('machine_password_hash', $device->toArray());
        $this->assertNotNull($device->registered_at);
        $this->assertNotNull($device->activated_at);

        $this->assertEquals($business->id, $device->business->id);
        $this->assertEquals($outlet->id, $device->outlet->id);
        $this->assertEquals($register->id, $device->register->id);

        // PosDeviceCredential
        $cred1 = PosDeviceCredential::create([
            'pos_device_id' => $device->id,
            'secret_hash' => 'hash_1',
            'is_active' => false,
        ]);

        $cred2 = PosDeviceCredential::create([
            'pos_device_id' => $device->id,
            'secret_hash' => 'hash_2',
            'is_active' => true,
        ]);

        $this->assertCount(2, $device->credentials);
        $this->assertEquals($cred2->id, $device->activeCredential->id);
        $this->assertEquals($device->id, $cred2->posDevice->id);

        // DeviceSession
        $session = DeviceSession::create([
            'pos_device_id' => $device->id,
            'token_hash' => 'hash_token_123',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'SmartPOS-Client',
            'started_at' => now(),
            'last_activity_at' => now(),
            'expires_at' => now()->addHours(8),
        ]);

        $this->assertTrue($device->deviceSessions->contains($session));
        $this->assertEquals($device->id, $session->posDevice->id);
    }
}

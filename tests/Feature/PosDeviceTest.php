<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Outlet;
use App\Models\PosDevice;
use App\Models\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosDeviceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_register_pos_device_and_returns_password_once(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Business POS', 'code' => 'BIZ-POS-1']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $userUuid, 'is_owner' => true, 'status' => 'active']);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-P1', 'name' => 'Outlet P1']);
        $register = Register::create(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'code' => 'REG-P1', 'name' => 'Reg P1']);

        $response = $this->withJwtAuth($userUuid, ['pos_devices.create'])
            ->postJson("/api/v1/outlets/{$outlet->uuid}/pos-devices", [
                'machine_id' => 'POS-PP-001',
                'device_name' => 'Main POS Terminal',
                'register_uuid' => $register->uuid,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.machine_id', 'POS-PP-001')
            ->assertJsonPath('data.status', 'pending');

        $this->assertNotEmpty($response->json('machine_password'));

        $device = PosDevice::where('machine_id', 'POS-PP-001')->first();
        $this->assertTrue(Hash::check($response->json('machine_password'), $device->machine_password_hash));
    }

    public function test_pos_device_authentication_flow(): void
    {
        $business = Business::create(['name' => 'Business POS Auth', 'code' => 'BIZ-POS-2']);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-P2', 'name' => 'Outlet P2']);

        $rawPassword = 'SuperSecretMachinePassword123!';

        $device = PosDevice::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'machine_id' => 'POS-PP-002',
            'device_name' => 'Terminal 2',
            'machine_password_hash' => Hash::make($rawPassword),
            'status' => 'active',
        ]);

        // Valid authentication
        $response = $this->postJson('/api/v1/pos-devices/auth', [
            'machine_id' => 'POS-PP-002',
            'machine_password' => $rawPassword,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('context.pos_device.machine_id', 'POS-PP-002')
            ->assertJsonPath('context.outlet.code', 'OUT-P2');

        // Verify last_seen_at was updated
        $this->assertNotNull($device->fresh()->last_seen_at);
    }

    public function test_invalid_pos_password_rejected(): void
    {
        $business = Business::create(['name' => 'Business POS Invalid', 'code' => 'BIZ-POS-3']);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-P3', 'name' => 'Outlet P3']);

        PosDevice::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'machine_id' => 'POS-PP-003',
            'device_name' => 'Terminal 3',
            'machine_password_hash' => Hash::make('CorrectPassword'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/pos-devices/auth', [
            'machine_id' => 'POS-PP-003',
            'machine_password' => 'WrongPassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Invalid POS machine ID or password.');
    }

    public function test_revoked_pos_device_rejected(): void
    {
        $business = Business::create(['name' => 'Business POS Revoked', 'code' => 'BIZ-POS-4']);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-P4', 'name' => 'Outlet P4']);

        PosDevice::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'machine_id' => 'POS-PP-004',
            'device_name' => 'Terminal 4',
            'machine_password_hash' => Hash::make('CorrectPassword'),
            'status' => 'revoked',
        ]);

        $response = $this->postJson('/api/v1/pos-devices/auth', [
            'machine_id' => 'POS-PP-004',
            'machine_password' => 'CorrectPassword',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'POS device access has been revoked.');
    }

    public function test_locked_pos_device_rejected(): void
    {
        $business = Business::create(['name' => 'Business POS Locked', 'code' => 'BIZ-POS-5']);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-P5', 'name' => 'Outlet P5']);

        PosDevice::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'machine_id' => 'POS-PP-005',
            'device_name' => 'Terminal 5',
            'machine_password_hash' => Hash::make('CorrectPassword'),
            'status' => 'locked',
        ]);

        $response = $this->postJson('/api/v1/pos-devices/auth', [
            'machine_id' => 'POS-PP-005',
            'machine_password' => 'CorrectPassword',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'POS device is currently locked.');
    }

    public function test_can_rotate_pos_device_credentials(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Business POS Rotate', 'code' => 'BIZ-POS-6']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $userUuid, 'is_owner' => true, 'status' => 'active']);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-P6', 'name' => 'Outlet P6']);

        $oldPassword = 'OldInitialPassword123';
        $device = PosDevice::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'machine_id' => 'POS-PP-006',
            'device_name' => 'Terminal 6',
            'machine_password_hash' => Hash::make($oldPassword),
            'status' => 'active',
        ]);

        $response = $this->withJwtAuth($userUuid, ['pos_devices.manage'])
            ->postJson("/api/v1/pos-devices/{$device->uuid}/rotate-secret");

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('machine_password'));
        $newPassword = $response->json('machine_password');
        $this->assertNotEquals($oldPassword, $newPassword);

        // Verify old password no longer works
        $oldAuth = $this->postJson('/api/v1/pos-devices/auth', [
            'machine_id' => 'POS-PP-006',
            'machine_password' => $oldPassword,
        ]);
        $oldAuth->assertStatus(401);

        // Verify non-owner cannot rotate secret
        $nonOwnerUuid = (string) Str::uuid();
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $nonOwnerUuid, 'is_owner' => false, 'status' => 'active']);
        
        $this->withJwtAuth($nonOwnerUuid, ['pos_devices.manage'])
            ->postJson("/api/v1/pos-devices/{$device->uuid}/rotate-secret")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden. Only business owners or administrators can rotate hardware credentials.');

        // Verify new password authenticates successfully
        $newAuth = $this->postJson('/api/v1/pos-devices/auth', [
            'machine_id' => 'POS-PP-006',
            'machine_password' => $newPassword,
        ]);
        $newAuth->assertStatus(200);
    }

    public function test_can_register_pos_device_with_hardware_metadata(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Business POS Meta', 'code' => 'BIZ-POS-7']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $userUuid, 'is_owner' => true, 'status' => 'active']);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-P7', 'name' => 'Outlet P7']);

        $response = $this->withJwtAuth($userUuid, ['pos_devices.create'])
            ->postJson("/api/v1/outlets/{$outlet->uuid}/pos-devices", [
                'machine_id' => 'POS-SUNMI-T2',
                'device_name' => 'Sunmi T2 Counter POS',
                'device_type' => 'counter_terminal',
                'platform' => 'android',
                'os_version' => 'Android 11 SUNMI-OS',
                'app_version' => 'v2.4.0',
                'ip_address' => '192.168.1.150',
                'mac_address' => '00:1B:44:11:3A:B7',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.machine_id', 'POS-SUNMI-T2')
            ->assertJsonPath('data.os_version', 'Android 11 SUNMI-OS')
            ->assertJsonPath('data.app_version', 'v2.4.0')
            ->assertJsonPath('data.ip_address', '192.168.1.150');

        $this->assertDatabaseHas('pos_devices', [
            'machine_id' => 'POS-SUNMI-T2',
            'os_version' => 'Android 11 SUNMI-OS',
            'app_version' => 'v2.4.0',
            'ip_address' => '192.168.1.150',
            'mac_address' => '00:1B:44:11:3A:B7',
        ]);
    }
}

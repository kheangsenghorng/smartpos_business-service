<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\DeviceSession;
use App\Models\Outlet;
use App\Models\PosDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_and_revoke_device_sessions(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Device Session Biz', 'code' => 'DSB-01']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $userUuid, 'is_owner' => true, 'status' => 'active']);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-DS1', 'name' => 'Outlet DS1']);
        $posDevice = PosDevice::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'machine_id' => 'MACHINE-DS-1',
            'device_name' => 'Terminal DS1',
            'status' => 'active',
        ]);

        $session = DeviceSession::create([
            'pos_device_id' => $posDevice->id,
            'token_hash' => hash('sha256', 'dummy-token'),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'SmartPOS Device Agent',
            'started_at' => now(),
            'last_activity_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        // 1. List sessions
        $listResponse = $this->withJwtAuth($userUuid, ['pos_devices.view'])
            ->getJson("/api/v1/pos-devices/{$posDevice->uuid}/sessions");

        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // 2. Revoke session
        $revokeResponse = $this->withJwtAuth($userUuid, ['pos_devices.manage'])
            ->postJson("/api/v1/pos-devices/{$posDevice->uuid}/sessions/{$session->uuid}/revoke");

        $revokeResponse->assertStatus(200)
            ->assertJsonPath('data.uuid', $session->uuid);

        $this->assertNotNull($session->fresh()->revoked_at);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\CashierSession;
use App\Models\Outlet;
use App\Models\PosDevice;
use App\Models\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashierSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_session_lifecycle(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Session Biz', 'code' => 'CSB-01']);
        $businessUser = BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $userUuid,
            'is_owner' => true,
            'status' => 'active',
        ]);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-CS1', 'name' => 'Outlet CS1']);
        $register = Register::create(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'code' => 'REG-CS1', 'name' => 'Register CS1']);
        $posDevice = PosDevice::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'register_id' => $register->id,
            'machine_id' => 'MACHINE-CS-1',
            'device_name' => 'Terminal CS1',
            'status' => 'active',
        ]);

        // 1. Start Session
        $startResponse = $this->withJwtAuth($userUuid, ['pos_devices.use'])
            ->postJson("/api/v1/outlets/{$outlet->uuid}/cashier-sessions/start", [
                'register_uuid' => $register->uuid,
                'pos_device_uuid' => $posDevice->uuid,
                'user_uuid' => $userUuid,
            ]);

        $startResponse->assertStatus(201)
            ->assertJsonPath('data.status', 'active');

        $sessionUuid = $startResponse->json('data.uuid');

        // 2. Get Current Session
        $currentResponse = $this->withJwtAuth($userUuid, ['pos_devices.use'])
            ->getJson("/api/v1/outlets/{$outlet->uuid}/cashier-sessions/current?register_uuid={$register->uuid}");

        $currentResponse->assertStatus(200)
            ->assertJsonPath('data.uuid', $sessionUuid)
            ->assertJsonPath('data.status', 'active');

        // 3. Lock Session
        $lockResponse = $this->withJwtAuth($userUuid, ['pos_devices.use'])
            ->postJson("/api/v1/outlets/{$outlet->uuid}/cashier-sessions/{$sessionUuid}/lock");

        $lockResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'locked');

        // 4. SEC-02 FIX: Unlock without PIN when user has no PIN set must be BLOCKED (403)
        $unlockNoPinResponse = $this->withJwtAuth($userUuid, ['pos_devices.use'])
            ->postJson("/api/v1/outlets/{$outlet->uuid}/cashier-sessions/{$sessionUuid}/unlock");

        $unlockNoPinResponse->assertStatus(403)
            ->assertJsonStructure(['message']);

        // 4b. Admin role can override and unlock a PIN-less session
        $adminUnlockResponse = $this->withJwtAuth($userUuid, ['pos_devices.use'], ['admin'])
            ->postJson("/api/v1/outlets/{$outlet->uuid}/cashier-sessions/{$sessionUuid}/unlock");

        $adminUnlockResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        // 5. Set a PIN on user and verify wrong PIN is rejected on unlock
        $businessUser->update(['pin_code_hash' => Hash::make('1234')]);

        // Re-lock
        $this->withJwtAuth($userUuid, ['pos_devices.use'])
            ->postJson("/api/v1/outlets/{$outlet->uuid}/cashier-sessions/{$sessionUuid}/lock")
            ->assertStatus(200);

        // Attempt unlock with wrong PIN
        $this->withJwtAuth($userUuid, ['pos_devices.use'])
            ->postJson("/api/v1/outlets/{$outlet->uuid}/cashier-sessions/{$sessionUuid}/unlock", [
                'pin_code' => '9999',
            ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid cashier PIN code.');

        // Unlock with correct PIN
        $this->withJwtAuth($userUuid, ['pos_devices.use'])
            ->postJson("/api/v1/outlets/{$outlet->uuid}/cashier-sessions/{$sessionUuid}/unlock", [
                'pin_code' => '1234',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        // 6. End Session
        $endResponse = $this->withJwtAuth($userUuid, ['pos_devices.use'])
            ->postJson("/api/v1/outlets/{$outlet->uuid}/cashier-sessions/{$sessionUuid}/end");

        $endResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'ended');
    }

    public function test_cashier_pin_unlock_is_rate_limited(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Rate Limit Biz', 'code' => 'RLB-01']);
        $businessUser = BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $userUuid,
            'is_owner' => true,
            'pin_code_hash' => Hash::make('5555'),
            'status' => 'active',
        ]);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-RL1', 'name' => 'Outlet RL1']);
        $register = Register::create(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'code' => 'REG-RL1', 'name' => 'Register RL1']);
        $posDevice = PosDevice::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'register_id' => $register->id,
            'machine_id' => 'MACHINE-RL-1',
            'device_name' => 'Terminal RL1',
            'status' => 'active',
        ]);

        $session = CashierSession::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'register_id' => $register->id,
            'pos_device_id' => $posDevice->id,
            'business_user_id' => $businessUser->id,
            'user_uuid' => $userUuid,
            'status' => 'locked',
            'started_at' => now(),
            'locked_at' => now(),
            'last_activity_at' => now(),
        ]);

        // Attempt 5 incorrect PINs
        for ($i = 0; $i < 5; $i++) {
            $this->withJwtAuth($userUuid, ['pos_devices.use'])
                ->postJson("/api/v1/outlets/{$outlet->uuid}/cashier-sessions/{$session->uuid}/unlock", [
                    'pin_code' => '0000',
                ])
                ->assertStatus(401);
        }

        // 6th attempt must be throttled with 429
        $throttledResponse = $this->withJwtAuth($userUuid, ['pos_devices.use'])
            ->postJson("/api/v1/outlets/{$outlet->uuid}/cashier-sessions/{$session->uuid}/unlock", [
                'pin_code' => '5555',
            ]);

        $throttledResponse->assertStatus(429)
            ->assertJsonPath('error', 'TOO_MANY_PIN_ATTEMPTS');
    }
}

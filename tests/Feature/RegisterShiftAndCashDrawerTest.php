<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Outlet;
use App\Models\PosDevice;
use App\Models\Register;
use App\Models\RegisterSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegisterShiftAndCashDrawerTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_shift_and_cash_drawer_full_lifecycle(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Shift Biz', 'code' => 'SFT-01']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $userUuid, 'is_owner' => true, 'status' => 'active']);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-S1', 'name' => 'Outlet S1']);
        $register = Register::create(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'code' => 'REG-S1', 'name' => 'Register S1']);
        $posDevice = PosDevice::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'register_id' => $register->id,
            'machine_id' => 'MACHINE-S1',
            'device_name' => 'Terminal S1',
            'status' => 'active',
        ]);

        // 1. Open Shift with float $100
        $openResponse = $this->withJwtAuth($userUuid, ['registers.manage'])
            ->postJson("/api/v1/outlets/{$outlet->uuid}/registers/{$register->uuid}/shifts/open", [
                'pos_device_uuid' => $posDevice->uuid,
                'opening_cash' => 100.00,
                'notes' => 'Shift 1 Morning',
            ]);

        $openResponse->assertStatus(201)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.opening_cash', '100.00');

        $shiftUuid = $openResponse->json('data.uuid');
        $drawerSessionUuid = $openResponse->json('data.cash_drawer_session.uuid');

        // 2. Query Current Open Shift
        $currentResponse = $this->withJwtAuth($userUuid, ['registers.view'])
            ->getJson("/api/v1/outlets/{$outlet->uuid}/registers/{$register->uuid}/shifts/current");

        $currentResponse->assertStatus(200)
            ->assertJsonPath('data.uuid', $shiftUuid)
            ->assertJsonPath('data.status', 'open');

        // 3. Reject Negative or Zero Cash Movement (Input Validation Security)
        $this->withJwtAuth($userUuid, ['registers.manage'])
            ->postJson("/api/v1/outlets/{$outlet->uuid}/registers/{$register->uuid}/drawers/{$drawerSessionUuid}/movements", [
                'type' => 'cash_in',
                'amount' => -50.00,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        $this->withJwtAuth($userUuid, ['registers.manage'])
            ->postJson("/api/v1/outlets/{$outlet->uuid}/registers/{$register->uuid}/drawers/{$drawerSessionUuid}/movements", [
                'type' => 'cash_in',
                'amount' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        // 4. Record Cash In ($50)
        $cashInResponse = $this->withJwtAuth($userUuid, ['registers.manage'])
            ->postJson("/api/v1/outlets/{$outlet->uuid}/registers/{$register->uuid}/drawers/{$drawerSessionUuid}/movements", [
                'type' => 'cash_in',
                'amount' => 50.00,
                'reason' => 'Add cash float',
            ]);

        $cashInResponse->assertStatus(201)
            ->assertJsonPath('current_balance', 150);

        // 4. Record Cash Sale ($30)
        $saleResponse = $this->withJwtAuth($userUuid, ['registers.manage'])
            ->postJson("/api/v1/outlets/{$outlet->uuid}/registers/{$register->uuid}/drawers/{$drawerSessionUuid}/movements", [
                'type' => 'cash_sale',
                'amount' => 30.00,
                'reason' => 'Order cash payment',
            ]);

        $saleResponse->assertStatus(201)
            ->assertJsonPath('current_balance', 180);

        // 5. Record Cash Refund ($10)
        $refundResponse = $this->withJwtAuth($userUuid, ['registers.manage'])
            ->postJson("/api/v1/outlets/{$outlet->uuid}/registers/{$register->uuid}/drawers/{$drawerSessionUuid}/movements", [
                'type' => 'cash_refund',
                'amount' => 10.00,
                'reason' => 'Customer returned item',
            ]);

        $refundResponse->assertStatus(201)
            ->assertJsonPath('current_balance', 170);

        // 6. View Cash Movements
        $movementsResponse = $this->withJwtAuth($userUuid, ['registers.view'])
            ->getJson("/api/v1/outlets/{$outlet->uuid}/registers/{$register->uuid}/drawers/{$drawerSessionUuid}/movements");

        $movementsResponse->assertStatus(200)
            ->assertJsonCount(4, 'data'); // opening, cash_in, cash_sale, cash_refund

        // 7. Close Shift (Actual counted cash = $168, difference = -$2)
        $closeResponse = $this->withJwtAuth($userUuid, ['registers.manage'])
            ->postJson("/api/v1/outlets/{$outlet->uuid}/registers/{$register->uuid}/shifts/{$shiftUuid}/close", [
                'closing_cash' => 168.00,
                'notes' => 'Evening reconciliation',
            ]);

        $closeResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.expected_cash', '170.00')
            ->assertJsonPath('data.closing_cash', '168.00')
            ->assertJsonPath('data.difference_amount', '-2.00');

        // 8. Verify No Active Shift remains
        $noShiftResponse = $this->withJwtAuth($userUuid, ['registers.view'])
            ->getJson("/api/v1/outlets/{$outlet->uuid}/registers/{$register->uuid}/shifts/current");

        $noShiftResponse->assertStatus(200)
            ->assertJsonPath('data', null);
    }
}

<?php

namespace Tests\Feature\Security;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\CashDrawerSession;
use App\Models\CashierSession;
use App\Models\Outlet;
use App\Models\PosDevice;
use App\Models\Register;
use App\Models\RegisterSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InputValidationSecurityTest extends TestCase
{
    use RefreshDatabase;

    private string $ownerUuid;

    private Business $business;

    private BusinessUser $ownerUser;

    private Outlet $outlet;

    private Register $register;

    private PosDevice $posDevice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerUuid = (string) Str::uuid();
        $this->business = Business::create(['name' => 'Validation Biz', 'code' => 'VAL-01']);
        $this->ownerUser = BusinessUser::create([
            'business_id' => $this->business->id,
            'user_uuid' => $this->ownerUuid,
            'is_owner' => true,
            'status' => 'active',
        ]);
        $this->outlet = Outlet::create(['business_id' => $this->business->id, 'code' => 'OUT-V1', 'name' => 'Outlet V1']);
        $this->register = Register::create(['business_id' => $this->business->id, 'outlet_id' => $this->outlet->id, 'code' => 'REG-V1', 'name' => 'Reg V1']);
        $this->posDevice = PosDevice::create([
            'business_id' => $this->business->id,
            'outlet_id' => $this->outlet->id,
            'register_id' => $this->register->id,
            'machine_id' => 'MACHINE-V1',
            'device_name' => 'Terminal V1',
            'status' => 'active',
        ]);
    }

    // =========================================================================
    // 1. Business Settings Validation
    // =========================================================================

    public function test_business_settings_rejects_invalid_timezone(): void
    {
        $response = $this->withJwtAuth($this->ownerUuid, ['businesses.update'])
            ->putJson("/api/v1/businesses/{$this->business->uuid}/settings", [
                'timezone' => 'Invalid/NonExistent_Timezone',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['timezone']);
    }

    public function test_business_settings_rejects_out_of_range_tax_and_discounts(): void
    {
        $response = $this->withJwtAuth($this->ownerUuid, ['businesses.update'])
            ->putJson("/api/v1/businesses/{$this->business->uuid}/settings", [
                'default_tax_percent' => -5.00,
                'max_discount_percent' => 150.00,
                'auto_lock_minutes' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['default_tax_percent', 'max_discount_percent', 'auto_lock_minutes']);
    }

    public function test_business_settings_rejects_invalid_currency_code(): void
    {
        $response = $this->withJwtAuth($this->ownerUuid, ['businesses.update'])
            ->putJson("/api/v1/businesses/{$this->business->uuid}/settings", [
                'currency_code' => 'USDD', // Must be 3 chars
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['currency_code']);
    }

    // =========================================================================
    // 2. Staff Outlet Assignment Validation
    // =========================================================================

    public function test_assign_outlet_rejects_non_uuid(): void
    {
        $response = $this->withJwtAuth($this->ownerUuid, ['business_users.manage'])
            ->postJson("/api/v1/businesses/{$this->business->uuid}/users/{$this->ownerUser->uuid}/outlets", [
                'outlet_uuid' => 'not-a-valid-uuid',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['outlet_uuid']);
    }

    public function test_assign_outlet_rejects_non_existent_outlet(): void
    {
        $randomUuid = (string) Str::uuid();

        $response = $this->withJwtAuth($this->ownerUuid, ['business_users.manage'])
            ->postJson("/api/v1/businesses/{$this->business->uuid}/users/{$this->ownerUser->uuid}/outlets", [
                'outlet_uuid' => $randomUuid,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['outlet_uuid']);
    }

    // =========================================================================
    // 3. Cashier Profile Validation
    // =========================================================================

    public function test_cashier_profile_rejects_invalid_avatar_url(): void
    {
        $response = $this->withJwtAuth($this->ownerUuid, ['business_users.manage'])
            ->putJson("/api/v1/businesses/{$this->business->uuid}/users/{$this->ownerUser->uuid}/cashier-profile", [
                'avatar_url' => 'javascript:alert("XSS")',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar_url']);
    }

    public function test_cashier_profile_rejects_negative_max_discount(): void
    {
        $response = $this->withJwtAuth($this->ownerUuid, ['business_users.manage'])
            ->putJson("/api/v1/businesses/{$this->business->uuid}/users/{$this->ownerUser->uuid}/cashier-profile", [
                'max_discount_percent' => -10.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['max_discount_percent']);
    }

    // =========================================================================
    // 4. Cashier Session Validation
    // =========================================================================

    public function test_start_cashier_session_requires_valid_uuids(): void
    {
        $response = $this->withJwtAuth($this->ownerUuid, ['pos_devices.use'])
            ->postJson("/api/v1/outlets/{$this->outlet->uuid}/cashier-sessions/start", [
                'register_uuid' => 'invalid',
                'pos_device_uuid' => 'invalid',
                'user_uuid' => 'invalid',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['register_uuid', 'pos_device_uuid', 'user_uuid']);
    }

    public function test_lock_cashier_session_rejects_already_locked_or_ended_session(): void
    {
        $session = CashierSession::create([
            'business_id' => $this->business->id,
            'outlet_id' => $this->outlet->id,
            'register_id' => $this->register->id,
            'pos_device_id' => $this->posDevice->id,
            'business_user_id' => $this->ownerUser->id,
            'user_uuid' => $this->ownerUuid,
            'status' => 'locked',
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        $response = $this->withJwtAuth($this->ownerUuid, ['pos_devices.use'])
            ->postJson("/api/v1/outlets/{$this->outlet->uuid}/cashier-sessions/{$session->uuid}/lock");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Only active sessions can be locked. Current status: locked');
    }

    // =========================================================================
    // 5. Register Shift Validation
    // =========================================================================

    public function test_open_shift_rejects_negative_opening_cash(): void
    {
        $response = $this->withJwtAuth($this->ownerUuid, ['registers.manage'])
            ->postJson("/api/v1/outlets/{$this->outlet->uuid}/registers/{$this->register->uuid}/shifts/open", [
                'opening_cash' => -50.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['opening_cash']);
    }

    public function test_open_shift_rejects_duplicate_active_shift(): void
    {
        RegisterSession::create([
            'business_id' => $this->business->id,
            'outlet_id' => $this->outlet->id,
            'register_id' => $this->register->id,
            'opened_by_user_uuid' => $this->ownerUuid,
            'opening_cash' => 100.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $response = $this->withJwtAuth($this->ownerUuid, ['registers.manage'])
            ->postJson("/api/v1/outlets/{$this->outlet->uuid}/registers/{$this->register->uuid}/shifts/open", [
                'opening_cash' => 100.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'There is already an active open shift on this register. Close it before opening a new one.');
    }

    public function test_close_shift_rejects_negative_closing_cash(): void
    {
        $shift = RegisterSession::create([
            'business_id' => $this->business->id,
            'outlet_id' => $this->outlet->id,
            'register_id' => $this->register->id,
            'opened_by_user_uuid' => $this->ownerUuid,
            'opening_cash' => 100.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $response = $this->withJwtAuth($this->ownerUuid, ['registers.manage'])
            ->postJson("/api/v1/outlets/{$this->outlet->uuid}/registers/{$this->register->uuid}/shifts/{$shift->uuid}/close", [
                'closing_cash' => -10.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['closing_cash']);
    }

    // =========================================================================
    // 6. Cash Drawer Movements Validation
    // =========================================================================

    public function test_record_movement_rejects_invalid_movement_type(): void
    {
        $shift = RegisterSession::create([
            'business_id' => $this->business->id,
            'outlet_id' => $this->outlet->id,
            'register_id' => $this->register->id,
            'opened_by_user_uuid' => $this->ownerUuid,
            'opening_cash' => 100.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $drawer = CashDrawerSession::create([
            'register_session_id' => $shift->id,
            'business_id' => $this->business->id,
            'outlet_id' => $this->outlet->id,
            'register_id' => $this->register->id,
            'opening_amount' => 100.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $response = $this->withJwtAuth($this->ownerUuid, ['registers.manage'])
            ->postJson("/api/v1/outlets/{$this->outlet->uuid}/registers/{$this->register->uuid}/drawers/{$drawer->uuid}/movements", [
                'type' => 'unauthorized_theft_movement',
                'amount' => 100.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_record_movement_rejects_zero_amount(): void
    {
        $shift = RegisterSession::create([
            'business_id' => $this->business->id,
            'outlet_id' => $this->outlet->id,
            'register_id' => $this->register->id,
            'opened_by_user_uuid' => $this->ownerUuid,
            'opening_cash' => 100.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $drawer = CashDrawerSession::create([
            'register_session_id' => $shift->id,
            'business_id' => $this->business->id,
            'outlet_id' => $this->outlet->id,
            'register_id' => $this->register->id,
            'opening_amount' => 100.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $response = $this->withJwtAuth($this->ownerUuid, ['registers.manage'])
            ->postJson("/api/v1/outlets/{$this->outlet->uuid}/registers/{$this->register->uuid}/drawers/{$drawer->uuid}/movements", [
                'type' => 'cash_in',
                'amount' => 0.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_record_movement_on_closed_drawer_is_rejected(): void
    {
        $shift = RegisterSession::create([
            'business_id' => $this->business->id,
            'outlet_id' => $this->outlet->id,
            'register_id' => $this->register->id,
            'opened_by_user_uuid' => $this->ownerUuid,
            'opening_cash' => 100.00,
            'status' => 'closed',
            'opened_at' => now(),
        ]);

        $drawer = CashDrawerSession::create([
            'register_session_id' => $shift->id,
            'business_id' => $this->business->id,
            'outlet_id' => $this->outlet->id,
            'register_id' => $this->register->id,
            'opening_amount' => 100.00,
            'status' => 'closed',
            'opened_at' => now(),
        ]);

        $response = $this->withJwtAuth($this->ownerUuid, ['registers.manage'])
            ->postJson("/api/v1/outlets/{$this->outlet->uuid}/registers/{$this->register->uuid}/drawers/{$drawer->uuid}/movements", [
                'type' => 'cash_in',
                'amount' => 50.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot record movement on a closed cash drawer session.');
    }
}

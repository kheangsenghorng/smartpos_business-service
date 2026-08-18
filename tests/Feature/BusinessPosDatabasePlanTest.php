<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessSetting;
use App\Models\BusinessUser;
use App\Models\BusinessUserOutlet;
use App\Models\CashDrawerMovement;
use App\Models\CashDrawerSession;
use App\Models\CashierProfile;
use App\Models\CashierSession;
use App\Models\DeviceSession;
use App\Models\Outlet;
use App\Models\PosDevice;
use App\Models\PosDeviceCredential;
use App\Models\Register;
use App\Models\RegisterSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessPosDatabasePlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_14_core_tables_exist(): void
    {
        $expectedTables = [
            'businesses',
            'business_users',
            'business_user_outlets',
            'business_settings',
            'outlets',
            'registers',
            'pos_devices',
            'pos_device_credentials',
            'device_sessions',
            'cashier_profiles',
            'cashier_sessions',
            'register_sessions',
            'cash_drawer_sessions',
            'cash_drawer_movements',
        ];

        foreach ($expectedTables as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Failed asserting that table [{$table}] exists in database."
            );
        }
    }

    public function test_businesses_table_has_expected_columns(): void
    {
        $columns = [
            'id', 'uuid', 'name', 'code', 'legal_name', 'phone', 'email',
            'logo_url', 'tax_number', 'currency_code', 'timezone', 'status',
            'created_at', 'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('businesses', $column),
                "Missing column [{$column}] on [businesses] table."
            );
        }
    }

    public function test_business_users_table_has_expected_columns(): void
    {
        $columns = [
            'id', 'uuid', 'business_id', 'user_uuid', 'employee_code',
            'job_title', 'is_active', 'joined_at', 'created_at', 'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('business_users', $column),
                "Missing column [{$column}] on [business_users] table."
            );
        }
    }

    public function test_business_user_outlets_table_has_expected_columns(): void
    {
        $columns = [
            'id', 'uuid', 'business_user_id', 'outlet_id', 'is_primary',
            'is_active', 'assigned_at', 'created_at', 'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('business_user_outlets', $column),
                "Missing column [{$column}] on [business_user_outlets] table."
            );
        }
    }

    public function test_business_settings_table_has_expected_columns(): void
    {
        $columns = [
            'id', 'business_id', 'receipt_prefix', 'currency_code',
            'timezone', 'tax_enabled', 'default_tax_percent', 'allow_negative_stock',
            'allow_discount', 'max_discount_percent', 'auto_lock_minutes',
            'receipt_footer', 'created_at', 'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('business_settings', $column),
                "Missing column [{$column}] on [business_settings] table."
            );
        }
    }

    public function test_outlets_table_has_expected_columns(): void
    {
        $columns = [
            'id', 'uuid', 'business_id', 'code', 'name', 'phone',
            'email', 'address', 'city', 'timezone', 'is_active',
            'created_at', 'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('outlets', $column),
                "Missing column [{$column}] on [outlets] table."
            );
        }
    }

    public function test_registers_table_has_expected_columns(): void
    {
        $columns = [
            'id', 'uuid', 'business_id', 'outlet_id', 'code', 'name',
            'status', 'is_active', 'created_at', 'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('registers', $column),
                "Missing column [{$column}] on [registers] table."
            );
        }
    }

    public function test_pos_devices_table_has_expected_columns(): void
    {
        $columns = [
            'id', 'uuid', 'business_id', 'outlet_id', 'register_id',
            'device_code', 'name', 'platform', 'device_model', 'os_version',
            'app_version', 'serial_number', 'status', 'last_seen_at',
            'activated_at', 'revoked_at', 'created_at', 'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('pos_devices', $column),
                "Missing column [{$column}] on [pos_devices] table."
            );
        }
    }

    public function test_pos_device_credentials_table_has_expected_columns(): void
    {
        $columns = [
            'id', 'uuid', 'pos_device_id', 'secret_hash', 'is_active',
            'last_rotated_at', 'expires_at', 'revoked_at', 'created_at', 'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('pos_device_credentials', $column),
                "Missing column [{$column}] on [pos_device_credentials] table."
            );
        }
    }

    public function test_device_sessions_table_has_expected_columns(): void
    {
        $columns = [
            'id', 'uuid', 'pos_device_id', 'token_hash', 'ip_address',
            'user_agent', 'started_at', 'last_activity_at', 'expires_at',
            'revoked_at', 'created_at', 'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('device_sessions', $column),
                "Missing column [{$column}] on [device_sessions] table."
            );
        }
    }

    public function test_cashier_profiles_table_has_expected_columns(): void
    {
        $columns = [
            'id', 'uuid', 'business_user_id', 'display_name', 'avatar_url',
            'can_sell', 'can_refund', 'can_void', 'can_discount',
            'max_discount_percent', 'is_active', 'last_pos_login_at',
            'created_at', 'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('cashier_profiles', $column),
                "Missing column [{$column}] on [cashier_profiles] table."
            );
        }
    }

    public function test_cashier_sessions_table_has_expected_columns(): void
    {
        $columns = [
            'id', 'uuid', 'business_id', 'outlet_id', 'register_id',
            'pos_device_id', 'business_user_id', 'user_uuid', 'status',
            'started_at', 'last_activity_at', 'locked_at', 'ended_at',
            'created_at', 'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('cashier_sessions', $column),
                "Missing column [{$column}] on [cashier_sessions] table."
            );
        }
    }

    public function test_register_sessions_table_has_expected_columns(): void
    {
        $columns = [
            'id', 'uuid', 'business_id', 'outlet_id', 'register_id',
            'pos_device_id', 'opened_by_user_uuid', 'closed_by_user_uuid',
            'opening_cash', 'expected_cash', 'closing_cash', 'difference_amount',
            'status', 'opened_at', 'closed_at', 'created_at', 'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('register_sessions', $column),
                "Missing column [{$column}] on [register_sessions] table."
            );
        }
    }

    public function test_cash_drawer_sessions_table_has_expected_columns(): void
    {
        $columns = [
            'id', 'uuid', 'register_session_id', 'business_id', 'outlet_id',
            'register_id', 'opening_amount', 'expected_amount', 'counted_amount',
            'difference_amount', 'status', 'opened_at', 'closed_at',
            'created_at', 'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('cash_drawer_sessions', $column),
                "Missing column [{$column}] on [cash_drawer_sessions] table."
            );
        }
    }

    public function test_cash_drawer_movements_table_has_expected_columns(): void
    {
        $columns = [
            'id', 'uuid', 'cash_drawer_session_id', 'business_id', 'outlet_id',
            'register_id', 'user_uuid', 'type', 'amount', 'reference_type',
            'reference_uuid', 'reason', 'notes', 'created_at', 'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('cash_drawer_movements', $column),
                "Missing column [{$column}] on [cash_drawer_movements] table."
            );
        }
    }

    public function test_end_to_end_pos_workflow_and_relationships(): void
    {
        // 1. Create Business
        $business = Business::create([
            'name' => 'Smart Retail Global',
            'code' => 'BUS-001',
            'legal_name' => 'Smart Retail Ltd.',
            'phone' => '+85512345678',
            'email' => 'admin@smartpos.com',
            'logo_url' => 'https://smartpos.com/logo.png',
            'tax_number' => 'VAT-123456',
            'currency_code' => 'USD',
            'timezone' => 'Asia/Phnom_Penh',
            'status' => 'active',
        ]);
        $this->assertNotEmpty($business->uuid);

        // 2. Business Settings
        $setting = BusinessSetting::create([
            'business_id' => $business->id,
            'receipt_prefix' => 'SRG',
            'currency_code' => 'USD',
            'timezone' => 'Asia/Phnom_Penh',
            'tax_enabled' => true,
            'default_tax_percent' => 10.00,
            'allow_negative_stock' => false,
            'allow_discount' => true,
            'max_discount_percent' => 20.00,
            'auto_lock_minutes' => 15,
            'receipt_footer' => 'Thank you for shopping with Smart Retail Global!',
        ]);
        $this->assertEquals('SRG', $business->settings->receipt_prefix);

        // 3. Create Outlets
        $outlet1 = Outlet::create([
            'business_id' => $business->id,
            'code' => 'OUT-001',
            'name' => 'Phnom Penh Main Store',
            'phone' => '+85512345001',
            'city' => 'Phnom Penh',
            'timezone' => 'Asia/Phnom_Penh',
            'is_active' => true,
        ]);

        $outlet2 = Outlet::create([
            'business_id' => $business->id,
            'code' => 'OUT-002',
            'name' => 'Toul Kork Store',
            'city' => 'Phnom Penh',
            'timezone' => 'Asia/Phnom_Penh',
            'is_active' => true,
        ]);

        $this->assertCount(2, $business->outlets);

        // 4. Create Register
        $register = Register::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet1->id,
            'code' => 'REG-001',
            'name' => 'Counter 1',
            'status' => 'active',
            'is_active' => true,
        ]);
        $this->assertEquals($outlet1->id, $register->outlet->id);

        // 5. Connect User from Identity Service (External Reference)
        $userIdentityUuid = (string) Str::uuid();
        $businessUser = BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $userIdentityUuid,
            'employee_code' => 'CASH-002',
            'job_title' => 'Senior Cashier',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        // 6. Assign Outlets to Staff (business_user_outlets)
        $assign1 = BusinessUserOutlet::create([
            'business_user_id' => $businessUser->id,
            'outlet_id' => $outlet1->id,
            'is_primary' => true,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $assign2 = BusinessUserOutlet::create([
            'business_user_id' => $businessUser->id,
            'outlet_id' => $outlet2->id,
            'is_primary' => false,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $this->assertCount(2, $businessUser->assignedOutlets);
        $this->assertCount(1, $outlet1->assignedUsers);

        // 7. Cashier Profile (No PIN here, PIN is in Identity Service)
        $cashierProfile = CashierProfile::create([
            'business_user_id' => $businessUser->id,
            'display_name' => 'Sokha Cashier',
            'avatar_url' => 'https://smartpos.com/avatars/cash002.png',
            'can_sell' => true,
            'can_refund' => true,
            'can_void' => false,
            'can_discount' => true,
            'max_discount_percent' => 15.00,
            'is_active' => true,
            'last_pos_login_at' => now(),
        ]);
        $this->assertEquals('Sokha Cashier', $businessUser->cashierProfile->display_name);

        // 8. Register POS Machine / Device
        $posDevice = PosDevice::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet1->id,
            'register_id' => $register->id,
            'device_code' => 'DEV-SUNMI-01',
            'name' => 'Main POS Sunmi T2',
            'machine_id' => 'MACHINE-UUID-001',
            'platform' => 'android',
            'device_model' => 'Sunmi T2',
            'os_version' => 'Android 11',
            'app_version' => '1.0.0',
            'serial_number' => 'SN-99887766',
            'status' => 'active',
            'activated_at' => now(),
        ]);

        // 9. Pos Device Credentials (Secure Hashed Credential)
        $machineSecret = 'MachineSecretPhrase123!';
        $deviceCred = PosDeviceCredential::create([
            'pos_device_id' => $posDevice->id,
            'secret_hash' => Hash::make($machineSecret),
            'is_active' => true,
            'last_rotated_at' => now(),
        ]);
        $this->assertTrue(Hash::check($machineSecret, $posDevice->activeCredential->secret_hash));

        // 10. Device Session (Machine Auth Session)
        $deviceSession = DeviceSession::create([
            'pos_device_id' => $posDevice->id,
            'token_hash' => hash('sha256', 'raw-pos-session-token'),
            'ip_address' => '192.168.1.50',
            'user_agent' => 'SmartPOS-Flutter/1.0.0 Android',
            'started_at' => now(),
            'last_activity_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);
        $this->assertCount(1, $posDevice->deviceSessions);

        // 11. Cashier Session (Cashier logged in via PIN)
        $cashierSession = CashierSession::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet1->id,
            'register_id' => $register->id,
            'pos_device_id' => $posDevice->id,
            'business_user_id' => $businessUser->id,
            'user_uuid' => $userIdentityUuid,
            'status' => 'active',
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);
        $this->assertEquals('active', $cashierSession->status);

        // 12. Register Session (Opening a Shift)
        $registerSession = RegisterSession::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet1->id,
            'register_id' => $register->id,
            'pos_device_id' => $posDevice->id,
            'opened_by_user_uuid' => $userIdentityUuid,
            'opening_cash' => 100.00,
            'expected_cash' => 730.00,
            'closing_cash' => 728.00,
            'difference_amount' => -2.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);
        $this->assertEquals(100.00, $registerSession->opening_cash);

        // 13. Cash Drawer Session
        $cashDrawerSession = CashDrawerSession::create([
            'register_session_id' => $registerSession->id,
            'business_id' => $business->id,
            'outlet_id' => $outlet1->id,
            'register_id' => $register->id,
            'opening_amount' => 100.00,
            'expected_amount' => 730.00,
            'counted_amount' => 728.00,
            'difference_amount' => -2.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);
        $this->assertEquals($registerSession->id, $cashDrawerSession->registerSession->id);

        // 14. Cash Drawer Movements
        $movements = [
            ['type' => 'opening', 'amount' => 100.00, 'reason' => 'Shift opening float'],
            ['type' => 'cash_sale', 'amount' => 25.00, 'reference_type' => 'order', 'reference_uuid' => (string) Str::uuid()],
            ['type' => 'cash_sale', 'amount' => 15.00, 'reference_type' => 'order', 'reference_uuid' => (string) Str::uuid()],
            ['type' => 'cash_refund', 'amount' => -5.00, 'reference_type' => 'refund', 'reference_uuid' => (string) Str::uuid()],
            ['type' => 'cash_in', 'amount' => 20.00, 'reason' => 'Add small change'],
            ['type' => 'cash_out', 'amount' => -10.00, 'reason' => 'Cleaning supplies'],
        ];

        foreach ($movements as $mov) {
            CashDrawerMovement::create([
                'cash_drawer_session_id' => $cashDrawerSession->id,
                'business_id' => $business->id,
                'outlet_id' => $outlet1->id,
                'register_id' => $register->id,
                'user_uuid' => $userIdentityUuid,
                'type' => $mov['type'],
                'amount' => $mov['amount'],
                'reference_type' => $mov['reference_type'] ?? null,
                'reference_uuid' => $mov['reference_uuid'] ?? null,
                'reason' => $mov['reason'] ?? null,
            ]);
        }

        $this->assertCount(6, $cashDrawerSession->movements);
    }
}

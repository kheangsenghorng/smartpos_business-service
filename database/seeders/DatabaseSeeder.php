<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\BusinessSetting;
use App\Models\BusinessUser;
use App\Models\BusinessUserOutlet;
use App\Models\CashDrawerMovement;
use App\Models\CashDrawerSession;
use App\Models\CashierProfile;
use App\Models\DeviceSession;
use App\Models\Outlet;
use App\Models\PosDevice;
use App\Models\PosDeviceCredential;
use App\Models\Register;
use App\Models\RegisterSession;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with complete POS business entities.
     */
    public function run(): void
    {
        // 1. BUSINESS (Company Profile)
        $business = Business::firstOrCreate(
            ['email' => 'owner1@example.com'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'SmartPOS Flagship Store',
                'code' => 'SPOS-001',
                'legal_name' => 'SmartPOS Retail & F&B LLC',
                'phone' => '+855 12 345 678',
                'tax_number' => 'K001-902348571',
                'registration_number' => 'CO-2026-88910',
                'website' => 'https://smartpos.example.com',
                'description' => 'SmartPOS flagship multi-outlet retail and cafe business.',
                'address' => 'No. 123, Monivong Blvd, Sangkat Boeung Keng Kang 1',
                'city' => 'Phnom Penh',
                'province' => 'Phnom Penh',
                'postal_code' => '120102',
                'country_code' => 'KH',
                'currency_code' => 'USD',
                'default_currency' => 'USD',
                'currency_symbol' => '$',
                'receipt_header' => "SmartPOS Retail & Cafe\nPhnom Penh, Cambodia\nTel: +855 12 345 678",
                'receipt_footer' => "Thank you for visiting!\nPlease come again.",
                'tax_rate' => 10.00,
                'is_tax_inclusive' => false,
                'timezone' => 'Asia/Phnom_Penh',
                'status' => 'active',
            ]
        );

        // 2. BUSINESS SETTINGS (Global POS Configuration)
        BusinessSetting::updateOrCreate(
            ['business_id' => $business->id],
            [
                'receipt_prefix' => 'REC',
                'currency_code' => 'USD',
                'timezone' => 'Asia/Phnom_Penh',
                'tax_enabled' => true,
                'default_tax_percent' => 10.00,
                'allow_negative_stock' => false,
                'allow_discount' => true,
                'max_discount_percent' => 50.00,
                'auto_lock_minutes' => 15,
                'receipt_footer' => 'Thank you for your purchase!',
            ]
        );

        // 3. OUTLETS (Store Locations)
        $outletDowntown = Outlet::firstOrCreate(
            ['business_id' => $business->id, 'code' => 'OUT-001'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Downtown Flagship Branch',
                'phone' => '+855 12 345 678',
                'email' => 'downtown@smartpos.com',
                'address' => 'No. 123, Monivong Blvd, BKK1',
                'city' => 'Phnom Penh',
                'province' => 'Phnom Penh',
                'postal_code' => '120102',
                'country_code' => 'KH',
                'is_main_outlet' => true,
                'tax_rate' => 10.00,
                'timezone' => 'Asia/Phnom_Penh',
                'status' => 'active',
                'is_active' => true,
            ]
        );

        $outletAirport = Outlet::firstOrCreate(
            ['business_id' => $business->id, 'code' => 'OUT-002'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Airport Express Outlet',
                'phone' => '+855 12 345 679',
                'email' => 'airport@smartpos.com',
                'address' => 'Phnom Penh Int Airport, Terminal 1, Gate 4',
                'city' => 'Phnom Penh',
                'province' => 'Phnom Penh',
                'postal_code' => '120999',
                'country_code' => 'KH',
                'is_main_outlet' => false,
                'tax_rate' => 10.00,
                'timezone' => 'Asia/Phnom_Penh',
                'status' => 'active',
                'is_active' => true,
            ]
        );

        // 4. CASH REGISTERS
        $regDowntown1 = Register::firstOrCreate(
            ['outlet_id' => $outletDowntown->id, 'code' => 'REG-001'],
            [
                'uuid' => (string) Str::uuid(),
                'business_id' => $business->id,
                'name' => 'Register 1 - Front Counter',
                'description' => 'Main checkout station for counter sales',
                'default_cash_amount' => 100.00,
                'receipt_printer_name' => 'Epson-TM-T88VI-01',
                'is_cash_drawer_connected' => true,
                'is_active' => true,
                'status' => 'active',
            ]
        );

        $regDowntown2 = Register::firstOrCreate(
            ['outlet_id' => $outletDowntown->id, 'code' => 'REG-002'],
            [
                'uuid' => (string) Str::uuid(),
                'business_id' => $business->id,
                'name' => 'Register 2 - Express Drive-Thru',
                'description' => 'Secondary fast-service cash drawer',
                'default_cash_amount' => 100.00,
                'receipt_printer_name' => 'Epson-TM-T88VI-02',
                'is_cash_drawer_connected' => true,
                'is_active' => true,
                'status' => 'active',
            ]
        );

        $regAirport1 = Register::firstOrCreate(
            ['outlet_id' => $outletAirport->id, 'code' => 'REG-003'],
            [
                'uuid' => (string) Str::uuid(),
                'business_id' => $business->id,
                'name' => 'Register 1 - Airport Departure',
                'description' => 'Airport terminal departure point-of-sale',
                'default_cash_amount' => 150.00,
                'receipt_printer_name' => 'Star-TSP143III',
                'is_cash_drawer_connected' => true,
                'is_active' => true,
                'status' => 'active',
            ]
        );

        // 5. BUSINESS USERS (Staff Members linked to Identity Service UUIDs)
        $ownerUser = BusinessUser::firstOrCreate(
            ['business_id' => $business->id, 'user_uuid' => '97f0693c-91dc-4b11-ab4a-cadcd6c09907'],
            [
                'uuid' => (string) Str::uuid(),
                'employee_code' => 'OWN-001',
                'job_title' => 'Business Owner',
                'role' => 'owner',
                'is_owner' => true,
                'is_active' => true,
                'pin_code_hash' => Hash::make('1234'),
                'phone' => '+855 12 345 678',
                'notes' => 'Primary store owner account',
                'status' => 'active',
                'joined_at' => now(),
            ]
        );

        $managerUser = BusinessUser::firstOrCreate(
            ['business_id' => $business->id, 'user_uuid' => '40690f41-3f7c-4f86-a321-8ec0e96af508'],
            [
                'uuid' => (string) Str::uuid(),
                'employee_code' => 'MGR-001',
                'job_title' => 'General Store Manager',
                'role' => 'manager',
                'is_owner' => false,
                'is_active' => true,
                'pin_code_hash' => Hash::make('1234'),
                'phone' => '+855 12 999 888',
                'notes' => 'Store operations manager',
                'status' => 'active',
                'joined_at' => now(),
            ]
        );

        $cashierDowntown = BusinessUser::firstOrCreate(
            ['business_id' => $business->id, 'user_uuid' => '0868a936-492f-4c9a-9bcd-bdbb7d36e88b'],
            [
                'uuid' => (string) Str::uuid(),
                'outlet_id' => $outletDowntown->id,
                'employee_code' => 'CASH-001',
                'job_title' => 'Senior Cashier',
                'role' => 'cashier',
                'is_owner' => false,
                'is_active' => true,
                'pin_code_hash' => Hash::make('1234'),
                'phone' => '+855 98 111 222',
                'notes' => 'Assigned to Downtown Branch counter',
                'status' => 'active',
                'joined_at' => now(),
            ]
        );

        $cashierAirport = BusinessUser::firstOrCreate(
            ['business_id' => $business->id, 'user_uuid' => '7c4d2d7d-8620-4fb3-967a-4a621082cf1f'],
            [
                'uuid' => (string) Str::uuid(),
                'outlet_id' => $outletAirport->id,
                'employee_code' => 'CASH-002',
                'job_title' => 'Airport Cashier',
                'role' => 'cashier',
                'is_owner' => false,
                'is_active' => true,
                'pin_code_hash' => Hash::make('1234'),
                'phone' => '+855 98 333 444',
                'notes' => 'Assigned to Airport Express counter',
                'status' => 'active',
                'joined_at' => now(),
            ]
        );

        // 6. BUSINESS USER OUTLETS (Multi-Branch Assignments)
        BusinessUserOutlet::firstOrCreate(
            ['business_user_id' => $ownerUser->id, 'outlet_id' => $outletDowntown->id],
            ['uuid' => (string) Str::uuid(), 'is_primary' => true, 'is_active' => true]
        );
        BusinessUserOutlet::firstOrCreate(
            ['business_user_id' => $ownerUser->id, 'outlet_id' => $outletAirport->id],
            ['uuid' => (string) Str::uuid(), 'is_primary' => false, 'is_active' => true]
        );

        BusinessUserOutlet::firstOrCreate(
            ['business_user_id' => $managerUser->id, 'outlet_id' => $outletDowntown->id],
            ['uuid' => (string) Str::uuid(), 'is_primary' => true, 'is_active' => true]
        );
        BusinessUserOutlet::firstOrCreate(
            ['business_user_id' => $managerUser->id, 'outlet_id' => $outletAirport->id],
            ['uuid' => (string) Str::uuid(), 'is_primary' => false, 'is_active' => true]
        );

        BusinessUserOutlet::firstOrCreate(
            ['business_user_id' => $cashierDowntown->id, 'outlet_id' => $outletDowntown->id],
            ['uuid' => (string) Str::uuid(), 'is_primary' => true, 'is_active' => true]
        );

        BusinessUserOutlet::firstOrCreate(
            ['business_user_id' => $cashierAirport->id, 'outlet_id' => $outletAirport->id],
            ['uuid' => (string) Str::uuid(), 'is_primary' => true, 'is_active' => true]
        );

        // 7. CASHIER PROFILES (Permissions & Limits)
        CashierProfile::updateOrCreate(
            ['business_user_id' => $cashierDowntown->id],
            [
                'uuid' => (string) Str::uuid(),
                'display_name' => 'Sokha (Downtown POS)',
                'can_sell' => true,
                'can_refund' => true,
                'can_void' => true,
                'can_discount' => true,
                'max_discount_percent' => 15.00,
                'is_active' => true,
            ]
        );

        CashierProfile::updateOrCreate(
            ['business_user_id' => $cashierAirport->id],
            [
                'uuid' => (string) Str::uuid(),
                'display_name' => 'Bopha (Airport POS)',
                'can_sell' => true,
                'can_refund' => false,
                'can_void' => true,
                'can_discount' => true,
                'max_discount_percent' => 10.00,
                'is_active' => true,
            ]
        );

        // 8. POS DEVICES & CREDENTIALS (Hardware Terminals)
        $devicePasswordHash = Hash::make('Password123!');

        $posDevice1 = PosDevice::firstOrCreate(
            ['machine_id' => 'POS-PP-001'],
            [
                'uuid' => (string) Str::uuid(),
                'business_id' => $business->id,
                'outlet_id' => $outletDowntown->id,
                'register_id' => $regDowntown1->id,
                'device_code' => 'POS-PP-001',
                'name' => 'Front Counter Terminal 1',
                'device_name' => 'Front Counter Terminal 1',
                'device_type' => 'pos_terminal',
                'device_model' => 'Sunmi T2s',
                'platform' => 'android',
                'os_version' => 'Android 13',
                'app_version' => '1.0.0',
                'serial_number' => 'SN-SUNMI-998811',
                'ip_address' => '192.168.1.101',
                'mac_address' => '00:1A:2B:3C:4D:5E',
                'machine_password_hash' => $devicePasswordHash,
                'status' => 'active',
                'registered_at' => now(),
                'activated_at' => now(),
                'last_seen_at' => now(),
            ]
        );

        PosDeviceCredential::updateOrCreate(
            ['pos_device_id' => $posDevice1->id],
            [
                'uuid' => (string) Str::uuid(),
                'secret_hash' => $devicePasswordHash,
                'is_active' => true,
                'last_rotated_at' => now(),
            ]
        );

        $posDevice2 = PosDevice::firstOrCreate(
            ['machine_id' => 'POS-AEON-002'],
            [
                'uuid' => (string) Str::uuid(),
                'business_id' => $business->id,
                'outlet_id' => $outletAirport->id,
                'register_id' => $regAirport1->id,
                'device_code' => 'POS-AEON-002',
                'name' => 'Airport Express Tablet',
                'device_name' => 'Airport Express Tablet',
                'device_type' => 'pos_tablet',
                'device_model' => 'iPad Pro 11-inch',
                'platform' => 'ios',
                'os_version' => 'iPadOS 17.5',
                'app_version' => '1.0.0',
                'serial_number' => 'SN-APPLE-443322',
                'ip_address' => '192.168.2.102',
                'mac_address' => 'A1:B2:C3:D4:E5:F6',
                'machine_password_hash' => $devicePasswordHash,
                'status' => 'active',
                'registered_at' => now(),
                'activated_at' => now(),
                'last_seen_at' => now(),
            ]
        );

        PosDeviceCredential::updateOrCreate(
            ['pos_device_id' => $posDevice2->id],
            [
                'uuid' => (string) Str::uuid(),
                'secret_hash' => $devicePasswordHash,
                'is_active' => true,
                'last_rotated_at' => now(),
            ]
        );

        // 9. REGISTER SESSIONS & CASH DRAWER SHIFTS
        $activeShift = RegisterSession::firstOrCreate(
            [
                'register_id' => $regDowntown1->id,
                'status' => 'open',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'business_id' => $business->id,
                'outlet_id' => $outletDowntown->id,
                'pos_device_id' => $posDevice1->id,
                'opened_by_user_uuid' => $cashierDowntown->user_uuid,
                'opening_cash' => 100.00,
                'expected_cash' => 100.00,
                'status' => 'open',
                'opened_at' => now()->subHours(2),
            ]
        );

        $drawerSession = CashDrawerSession::firstOrCreate(
            [
                'register_session_id' => $activeShift->id,
                'status' => 'open',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'business_id' => $business->id,
                'outlet_id' => $outletDowntown->id,
                'register_id' => $regDowntown1->id,
                'opening_amount' => 100.00,
                'expected_amount' => 100.00,
                'status' => 'open',
                'opened_at' => now()->subHours(2),
            ]
        );

        // 10. CASH DRAWER MOVEMENTS (Sample Cash In / Out)
        CashDrawerMovement::firstOrCreate(
            [
                'cash_drawer_session_id' => $drawerSession->id,
                'reason' => 'Initial morning cash float addition',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'business_id' => $business->id,
                'outlet_id' => $outletDowntown->id,
                'register_id' => $regDowntown1->id,
                'user_uuid' => $cashierDowntown->user_uuid,
                'type' => 'cash_in',
                'amount' => 50.00,
                'notes' => 'Added extra $50 in $1 and $5 small bills for change',
            ]
        );
    }
}

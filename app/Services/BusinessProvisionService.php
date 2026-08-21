<?php

namespace App\Services;

use App\Mail\PosDeviceCredentialsMail;
use App\Models\Business;
use App\Models\BusinessSetting;
use App\Models\BusinessUser;
use App\Models\BusinessUserOutlet;
use App\Models\Outlet;
use App\Models\PosDevice;
use App\Models\PosDeviceCredential;
use App\Models\Register;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BusinessProvisionService
{
    /**
     * Auto-provision default Outlet, Register, POS Settings, and POS Device credentials for a newly created business.
     */
    public function provisionDefaultPosSetup(Business $business, ?BusinessUser $ownerUser = null, ?string $recipientEmail = null): array
    {
        // 1. Default Business Settings
        $rawCurrency = $business->currency_code ?: $business->default_currency ?: 'USD';
        $currencyCode = strtoupper(substr(trim((string) $rawCurrency), 0, 3));
        if (strlen($currencyCode) !== 3) {
            $currencyCode = 'USD';
        }

        $timezone = $business->timezone ?: 'Asia/Phnom_Penh';
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            $timezone = 'Asia/Phnom_Penh';
        }

        $taxRate = is_numeric($business->tax_rate) ? min(max((float) $business->tax_rate, 0.00), 100.00) : 0.00;

        $settings = BusinessSetting::firstOrCreate(
            ['business_id' => $business->id],
            [
                'receipt_prefix' => 'REC',
                'currency_code' => $currencyCode,
                'timezone' => $timezone,
                'tax_enabled' => false,
                'default_tax_percent' => $taxRate,
                'allow_negative_stock' => false,
                'allow_discount' => true,
                'max_discount_percent' => 50.00,
                'auto_lock_minutes' => 15,
                'receipt_footer' => 'Thank you for your purchase!',
            ]
        );

        // 2. Default Main Outlet
        $outlet = Outlet::firstOrCreate(
            ['business_id' => $business->id, 'code' => 'OUT-001'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $business->name . ' - Main Branch',
                'phone' => $business->phone,
                'email' => $business->email,
                'address' => $business->address,
                'city' => $business->city,
                'province' => $business->province,
                'postal_code' => $business->postal_code,
                'country_code' => $business->country_code ?? 'KH',
                'is_main_outlet' => true,
                'tax_rate' => $business->tax_rate ?? 0.00,
                'timezone' => $business->timezone ?? 'Asia/Phnom_Penh',
                'status' => 'active',
                'is_active' => true,
            ]
        );

        // 3. Assign Owner to the Default Outlet
        if ($ownerUser) {
            BusinessUserOutlet::firstOrCreate(
                [
                    'business_user_id' => $ownerUser->id,
                    'outlet_id' => $outlet->id,
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'is_primary' => true,
                    'is_active' => true,
                    'assigned_at' => now(),
                ]
            );
        }

        // 4. Default Cash Register
        $register = Register::firstOrCreate(
            ['outlet_id' => $outlet->id, 'code' => 'REG-001'],
            [
                'uuid' => (string) Str::uuid(),
                'business_id' => $business->id,
                'name' => 'Register 1 - Front Counter',
                'description' => 'Main checkout point of sale station',
                'default_cash_amount' => 100.00,
                'is_cash_drawer_connected' => true,
                'is_active' => true,
                'status' => 'active',
            ]
        );

        // 5. Generate Secure Machine Credentials & POS Device
        $deviceCode = 'POS-' . strtoupper(Str::random(6));
        $plainPassword = Str::random(12);
        $passwordHash = Hash::make($plainPassword);

        $posDevice = PosDevice::firstOrCreate(
            [
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'register_id' => $register->id,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'machine_id' => $deviceCode,
                'device_code' => $deviceCode,
                'name' => 'Front Counter Terminal 1',
                'device_name' => 'Front Counter Terminal 1',
                'device_type' => 'pos_terminal',
                'platform' => 'android',
                'machine_password_hash' => $passwordHash,
                'status' => 'active',
                'registered_at' => now(),
                'activated_at' => now(),
            ]
        );

        PosDeviceCredential::updateOrCreate(
            ['pos_device_id' => $posDevice->id],
            [
                'uuid' => (string) Str::uuid(),
                'secret_hash' => $passwordHash,
                'is_active' => true,
                'last_rotated_at' => now(),
            ]
        );

        // 6. Send Email Notification with Credentials
        $targetEmail = $recipientEmail ?? $business->email;
        if ($targetEmail) {
            try {
                Mail::to($targetEmail)->send(
                    new PosDeviceCredentialsMail(
                        business: $business,
                        outlet: $outlet,
                        register: $register,
                        device: $posDevice,
                        plainPassword: $plainPassword
                    )
                );
                Log::info("[POS_CREDENTIALS_EMAIL_SENT] Successfully sent POS machine credentials to {$targetEmail} for business {$business->name}");
            } catch (\Throwable $e) {
                Log::warning("[POS_CREDENTIALS_EMAIL_FAILED] Failed to send email to {$targetEmail}: " . $e->getMessage());
            }
        }

        return [
            'settings' => $settings,
            'outlet' => $outlet,
            'register' => $register,
            'pos_device' => $posDevice,
            'credentials' => [
                'device_code' => $posDevice->device_code ?? $deviceCode,
                'machine_password' => $plainPassword,
            ],
        ];
    }
}

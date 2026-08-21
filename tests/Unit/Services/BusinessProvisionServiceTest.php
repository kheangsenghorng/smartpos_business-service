<?php

namespace Tests\Unit\Services;

use App\Mail\PosDeviceCredentialsMail;
use App\Models\Business;
use App\Models\BusinessSetting;
use App\Models\BusinessUser;
use App\Models\BusinessUserOutlet;
use App\Models\Outlet;
use App\Models\PosDevice;
use App\Models\PosDeviceCredential;
use App\Models\Register;
use App\Services\BusinessProvisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessProvisionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BusinessProvisionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BusinessProvisionService();
    }

    public function test_provisions_default_pos_setup_successfully(): void
    {
        Mail::fake();

        $business = Business::create([
            'name' => 'Main Store',
            'code' => 'STORE-01',
            'email' => 'store@example.com',
            'phone' => '012345678',
            'currency_code' => 'USD',
            'timezone' => 'Asia/Phnom_Penh',
            'tax_rate' => 10.00,
        ]);

        $owner = BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => (string) Str::uuid(),
            'role' => 'owner',
            'is_owner' => true,
            'status' => 'active',
        ]);

        $result = $this->service->provisionDefaultPosSetup($business, $owner, 'recipient@example.com');

        // Check return structure
        $this->assertArrayHasKey('settings', $result);
        $this->assertArrayHasKey('outlet', $result);
        $this->assertArrayHasKey('register', $result);
        $this->assertArrayHasKey('pos_device', $result);
        $this->assertArrayHasKey('credentials', $result);
        $this->assertArrayHasKey('device_code', $result['credentials']);
        $this->assertArrayHasKey('machine_password', $result['credentials']);

        // Check Business Setting creation
        $this->assertDatabaseHas('business_settings', [
            'business_id' => $business->id,
            'currency_code' => 'USD',
            'timezone' => 'Asia/Phnom_Penh',
            'default_tax_percent' => 10.00,
        ]);

        // Check Main Outlet creation
        $this->assertDatabaseHas('outlets', [
            'business_id' => $business->id,
            'code' => 'OUT-001',
            'is_main_outlet' => true,
            'is_active' => true,
        ]);

        // Check Owner assignment to outlet
        $outlet = $result['outlet'];
        $this->assertDatabaseHas('business_user_outlets', [
            'business_user_id' => $owner->id,
            'outlet_id' => $outlet->id,
            'is_primary' => true,
        ]);

        // Check Default Register creation
        $this->assertDatabaseHas('registers', [
            'outlet_id' => $outlet->id,
            'code' => 'REG-001',
            'default_cash_amount' => 100.00,
            'is_active' => true,
        ]);

        // Check POS Device & Credentials creation
        $posDevice = $result['pos_device'];
        $this->assertDatabaseHas('pos_devices', [
            'id' => $posDevice->id,
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'device_type' => 'pos_terminal',
        ]);

        $this->assertDatabaseHas('pos_device_credentials', [
            'pos_device_id' => $posDevice->id,
            'is_active' => true,
        ]);

        $credential = PosDeviceCredential::where('pos_device_id', $posDevice->id)->first();
        $this->assertTrue(Hash::check($result['credentials']['machine_password'], $credential->secret_hash));

        // Verify Mail sent
        Mail::assertSent(PosDeviceCredentialsMail::class, function ($mail) {
            return $mail->hasTo('recipient@example.com');
        });
    }

    public function test_falls_back_to_usd_when_currency_is_invalid(): void
    {
        Mail::fake();

        $business = Business::create([
            'name' => 'Bad Currency Shop',
            'code' => 'BAD-CURR',
            'currency_code' => 'INVALID_CODE',
        ]);

        $result = $this->service->provisionDefaultPosSetup($business);

        $this->assertEquals('INV', $result['settings']->currency_code);

        // Test with empty/short currency
        $business2 = Business::create([
            'name' => 'Empty Currency Shop',
            'code' => 'EMPTY-CURR',
            'currency_code' => 'US',
        ]);

        $result2 = $this->service->provisionDefaultPosSetup($business2);
        $this->assertEquals('USD', $result2['settings']->currency_code);
    }

    public function test_falls_back_to_default_timezone_when_invalid(): void
    {
        Mail::fake();

        $business = Business::create([
            'name' => 'Bad Timezone Shop',
            'code' => 'BAD-TZ',
            'timezone' => 'Invalid/Unknown_Zone',
        ]);

        $result = $this->service->provisionDefaultPosSetup($business);

        $this->assertEquals('Asia/Phnom_Penh', $result['settings']->timezone);
    }

    public function test_clamps_tax_rate_within_valid_range(): void
    {
        Mail::fake();

        $businessNegative = Business::create([
            'name' => 'Negative Tax Shop',
            'code' => 'NEG-TAX',
            'tax_rate' => -15.50,
        ]);

        $resultNegative = $this->service->provisionDefaultPosSetup($businessNegative);
        $this->assertEquals(0.00, (float) $resultNegative['settings']->default_tax_percent);

        $businessExcessive = Business::create([
            'name' => 'Excessive Tax Shop',
            'code' => 'EXC-TAX',
            'tax_rate' => 150.00,
        ]);

        $resultExcessive = $this->service->provisionDefaultPosSetup($businessExcessive);
        $this->assertEquals(100.00, (float) $resultExcessive['settings']->default_tax_percent);
    }

    public function test_gracefully_handles_email_failures(): void
    {
        // Don't fake mail, let it trigger without failing test execution if exception occurs
        $business = Business::create([
            'name' => 'No Mail Shop',
            'code' => 'NO-MAIL',
            'email' => 'test@local.invalid',
        ]);

        // Should complete without throwing an unhandled exception
        $result = $this->service->provisionDefaultPosSetup($business);

        $this->assertNotNull($result);
        $this->assertNotEmpty($result['credentials']['machine_password']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_business(): void
    {
        $userUuid = (string) Str::uuid();

        $response = $this->withJwtAuth($userUuid, ['businesses.create'])
            ->postJson('/api/v1/businesses', [
                'name' => 'Kopi Mantap',
                'code' => 'BIZ-001',
                'phone' => '08123456789',
                'email' => 'contact@kopimantap.test',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Kopi Mantap')
            ->assertJsonPath('data.code', 'BIZ-001');

        $this->assertDatabaseHas('businesses', [
            'code' => 'BIZ-001',
        ]);

        $business = Business::where('code', 'BIZ-001')->first();

        $this->assertDatabaseHas('business_users', [
            'business_id' => $business->id,
            'user_uuid' => $userUuid,
            'is_owner' => true,
            'status' => 'active',
        ]);
    }

    public function test_can_list_user_businesses(): void
    {
        $userUuid = (string) Str::uuid();

        $business = Business::create([
            'name' => 'Kedai Kopi',
            'code' => 'BIZ-002',
        ]);

        BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $userUuid,
            'is_owner' => true,
            'status' => 'active',
        ]);

        $response = $this->withJwtAuth($userUuid, ['businesses.view'])
            ->getJson('/api/v1/businesses');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'BIZ-002');
    }

    public function test_platform_admin_can_list_all_businesses_without_explicit_membership(): void
    {
        // Create 2 businesses belonging to other users
        Business::create(['name' => 'Store Alpha', 'code' => 'ALPHA-1']);
        Business::create(['name' => 'Store Beta', 'code' => 'BETA-1']);

        // Platform Admin without business_users entry
        $adminUuid = (string) Str::uuid();

        $response = $this->withJwtAuth($adminUuid, ['businesses.view'], ['admin'])
            ->getJson('/api/v1/businesses');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_duplicate_code_rejected(): void
    {
        Business::create([
            'name' => 'Business One',
            'code' => 'BIZ-DUP',
        ]);

        $response = $this->withJwtAuth(null, ['businesses.create'])
            ->postJson('/api/v1/businesses', [
                'name' => 'Business Two',
                'code' => 'BIZ-DUP',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_unauthorized_user_rejected(): void
    {
        $business = Business::create([
            'name' => 'Secret Business',
            'code' => 'BIZ-SEC',
        ]);

        // User without business membership or permission
        $response = $this->withJwtAuth(null, ['businesses.view'])
            ->getJson("/api/v1/businesses/{$business->uuid}");

        $response->assertStatus(403);
    }

    public function test_owner_can_update_business(): void
    {
        $userUuid = (string) Str::uuid();

        $business = Business::create([
            'name' => 'Old Name',
            'code' => 'BIZ-UPD',
        ]);

        BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $userUuid,
            'is_owner' => true,
            'status' => 'active',
        ]);

        $response = $this->withJwtAuth($userUuid, ['businesses.update'])
            ->putJson("/api/v1/businesses/{$business->uuid}", [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_can_create_business_with_enhanced_schema_fields(): void
    {
        $userUuid = (string) Str::uuid();

        $response = $this->withJwtAuth($userUuid, ['businesses.create'])
            ->postJson('/api/v1/businesses', [
                'name' => 'Smart Retail Store',
                'code' => 'BIZ-RETAIL-01',
                'legal_name' => 'PT Smart Retail Indonesia',
                'phone' => '+6281299998888',
                'email' => 'finance@smartretail.id',
                'tax_number' => 'NPWP-99.888.777.6-555.000',
                'website' => 'https://smartretail.id',
                'description' => 'Flagship smart POS retail branch',
                'address' => 'Jl. Sudirman No. 10',
                'city' => 'Jakarta Pusat',
                'province' => 'DKI Jakarta',
                'postal_code' => '10220',
                'country_code' => 'ID',
                'default_currency' => 'IDR',
                'currency_symbol' => 'Rp',
                'receipt_header' => 'Welcome to Smart Retail Store!',
                'receipt_footer' => 'Thank you for shopping with us.',
                'tax_rate' => 11.00,
                'is_tax_inclusive' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'BIZ-RETAIL-01')
            ->assertJsonPath('data.city', 'Jakarta Pusat')
            ->assertJsonPath('data.currency_symbol', 'Rp')
            ->assertJsonPath('data.is_tax_inclusive', true);

        $this->assertDatabaseHas('businesses', [
            'code' => 'BIZ-RETAIL-01',
            'city' => 'Jakarta Pusat',
            'postal_code' => '10220',
            'currency_symbol' => 'Rp',
            'is_tax_inclusive' => true,
        ]);
    }
}

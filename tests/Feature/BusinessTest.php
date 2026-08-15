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
}

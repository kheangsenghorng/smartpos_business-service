<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_add_user_to_business(): void
    {
        $ownerUuid = (string) Str::uuid();
        $newUserUuid = (string) Str::uuid();

        $business = Business::create(['name' => 'Test Business', 'code' => 'BIZ-U1']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $ownerUuid, 'is_owner' => true, 'status' => 'active']);

        $response = $this->withJwtAuth($ownerUuid, ['business_users.manage'])
            ->postJson("/api/v1/businesses/{$business->uuid}/users", [
                'user_uuid' => $newUserUuid,
                'is_owner' => false,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user_uuid', $newUserUuid);

        $this->assertDatabaseHas('business_users', [
            'business_id' => $business->id,
            'user_uuid' => $newUserUuid,
            'is_owner' => false,
        ]);
    }

    public function test_duplicate_user_rejected(): void
    {
        $ownerUuid = (string) Str::uuid();

        $business = Business::create(['name' => 'Test Business', 'code' => 'BIZ-U2']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $ownerUuid, 'is_owner' => true, 'status' => 'active']);

        $response = $this->withJwtAuth($ownerUuid, ['business_users.manage'])
            ->postJson("/api/v1/businesses/{$business->uuid}/users", [
                'user_uuid' => $ownerUuid,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_uuid']);
    }

    public function test_cannot_remove_sole_owner(): void
    {
        $ownerUuid = (string) Str::uuid();

        $business = Business::create(['name' => 'Test Business', 'code' => 'BIZ-U3']);
        $ownerUser = BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $ownerUuid, 'is_owner' => true, 'status' => 'active']);

        $response = $this->withJwtAuth($ownerUuid, ['business_users.manage'])
            ->deleteJson("/api/v1/businesses/{$business->uuid}/users/{$ownerUser->uuid}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot remove the sole owner of the business.');
    }

    public function test_can_suspend_user(): void
    {
        $ownerUuid = (string) Str::uuid();
        $staffUuid = (string) Str::uuid();

        $business = Business::create(['name' => 'Test Business', 'code' => 'BIZ-U4']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $ownerUuid, 'is_owner' => true, 'status' => 'active']);
        $staffUser = BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $staffUuid, 'is_owner' => false, 'status' => 'active']);

        $response = $this->withJwtAuth($ownerUuid, ['business_users.manage'])
            ->postJson("/api/v1/businesses/{$business->uuid}/users/{$staffUser->uuid}/suspend");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'suspended');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Outlet;
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

    public function test_can_add_cashier_user_with_pin_code_and_role(): void
    {
        $ownerUuid = (string) Str::uuid();
        $cashierUuid = (string) Str::uuid();

        $business = Business::create(['name' => 'Retail B5', 'code' => 'BIZ-U5']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $ownerUuid, 'is_owner' => true, 'status' => 'active']);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-U5', 'name' => 'Outlet U5']);

        $response = $this->withJwtAuth($ownerUuid, ['business_users.manage'])
            ->postJson("/api/v1/businesses/{$business->uuid}/users", [
                'user_uuid' => $cashierUuid,
                'outlet_id' => $outlet->id,
                'role' => 'cashier',
                'is_owner' => false,
                'pin_code' => '123456',
                'phone' => '+62811223344',
                'notes' => 'Day shift cashier',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user_uuid', $cashierUuid)
            ->assertJsonPath('data.role', 'cashier')
            ->assertJsonPath('data.outlet_id', $outlet->id)
            ->assertJsonMissingPath('data.pin_code_hash');

        $this->assertDatabaseHas('business_users', [
            'business_id' => $business->id,
            'user_uuid' => $cashierUuid,
            'role' => 'cashier',
            'outlet_id' => $outlet->id,
        ]);
    }

    public function test_cross_tenant_outlet_id_is_rejected(): void
    {
        $ownerUuid = (string) Str::uuid();
        $targetUserUuid = (string) Str::uuid();

        $businessA = Business::create(['name' => 'Tenant A', 'code' => 'BIZ-UA']);
        BusinessUser::create(['business_id' => $businessA->id, 'user_uuid' => $ownerUuid, 'is_owner' => true, 'status' => 'active']);

        $businessB = Business::create(['name' => 'Tenant B', 'code' => 'BIZ-UB']);
        $foreignOutlet = Outlet::create(['business_id' => $businessB->id, 'code' => 'OUT-UB', 'name' => 'Foreign Outlet']);

        // Attempt store with foreign outlet
        $this->withJwtAuth($ownerUuid, ['business_users.manage'])
            ->postJson("/api/v1/businesses/{$businessA->uuid}/users", [
                'user_uuid' => $targetUserUuid,
                'outlet_id' => $foreignOutlet->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['outlet_id']);
    }

    public function test_cannot_demote_or_suspend_sole_owner_via_update(): void
    {
        $ownerUuid = (string) Str::uuid();

        $business = Business::create(['name' => 'Sole Owner Biz', 'code' => 'BIZ-SO1']);
        $ownerUser = BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $ownerUuid, 'is_owner' => true, 'status' => 'active']);

        // Attempt demote sole owner
        $this->withJwtAuth($ownerUuid, ['business_users.manage'])
            ->putJson("/api/v1/businesses/{$business->uuid}/users/{$ownerUser->uuid}", [
                'is_owner' => false,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot demote or suspend the sole owner of the business.');

        // Attempt suspend sole owner via update
        $this->withJwtAuth($ownerUuid, ['business_users.manage'])
            ->putJson("/api/v1/businesses/{$business->uuid}/users/{$ownerUser->uuid}", [
                'status' => 'suspended',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot demote or suspend the sole owner of the business.');
    }
}

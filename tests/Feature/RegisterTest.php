<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Outlet;
use App\Models\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_register(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Business R', 'code' => 'BIZ-REG-1']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $userUuid, 'is_owner' => true, 'status' => 'active']);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-R', 'name' => 'Outlet R']);

        $response = $this->withJwtAuth($userUuid, ['registers.create'])
            ->postJson("/api/v1/outlets/{$outlet->uuid}/registers", [
                'code' => 'REG-01',
                'name' => 'Cashier 1',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'REG-01')
            ->assertJsonPath('data.name', 'Cashier 1');

        $this->assertDatabaseHas('registers', [
            'outlet_id' => $outlet->id,
            'code' => 'REG-01',
        ]);
    }

    public function test_duplicate_register_code_in_same_outlet_rejected(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Business R2', 'code' => 'BIZ-REG-2']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $userUuid, 'is_owner' => true, 'status' => 'active']);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-R2', 'name' => 'Outlet R2']);

        Register::create(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'code' => 'REG-DUP', 'name' => 'Reg 1']);

        $response = $this->withJwtAuth($userUuid, ['registers.create'])
            ->postJson("/api/v1/outlets/{$outlet->uuid}/registers", [
                'code' => 'REG-DUP',
                'name' => 'Reg 2',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_can_create_register_with_enhanced_schema_fields(): void
    {
        $userUuid = (string) Str::uuid();
        $business = Business::create(['name' => 'Business R3', 'code' => 'BIZ-REG-3']);
        BusinessUser::create(['business_id' => $business->id, 'user_uuid' => $userUuid, 'is_owner' => true, 'status' => 'active']);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-R3', 'name' => 'Outlet R3']);

        $response = $this->withJwtAuth($userUuid, ['registers.create'])
            ->postJson("/api/v1/outlets/{$outlet->uuid}/registers", [
                'code' => 'REG-MAIN-01',
                'name' => 'Main Front Counter',
                'description' => 'Fast checkout terminal 1',
                'default_cash_amount' => 500000.00,
                'receipt_printer_name' => 'EPSON TM-T88VI',
                'is_cash_drawer_connected' => true,
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'REG-MAIN-01')
            ->assertJsonPath('data.receipt_printer_name', 'EPSON TM-T88VI')
            ->assertJsonPath('data.is_cash_drawer_connected', true);

        $this->assertDatabaseHas('registers', [
            'outlet_id' => $outlet->id,
            'code' => 'REG-MAIN-01',
            'receipt_printer_name' => 'EPSON TM-T88VI',
            'is_cash_drawer_connected' => true,
        ]);
    }
}

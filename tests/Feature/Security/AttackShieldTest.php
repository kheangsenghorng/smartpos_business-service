<?php

namespace Tests\Feature\Security;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\PosDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttackShieldTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // 1. Scanner & Bot Blocking Tests
    // =========================================================================

    public function test_blocks_sqlmap_scanner_user_agent(): void
    {
        $response = $this->withHeaders([
            'User-Agent' => 'sqlmap/1.6.4#stable (https://sqlmap.org)',
        ])->getJson('/api/v1/business/health');

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'FORBIDDEN');
        $response->assertJsonPath('message', 'Automated scanning tools and malicious agents are blocked.');
    }

    public function test_blocks_nikto_scanner_user_agent(): void
    {
        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.00 (Nikto/2.1.6) (Evasions:None) (Test:Port Check)',
        ])->getJson('/api/v1/business/health');

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'FORBIDDEN');
    }

    public function test_blocks_dirbuster_and_gobuster_user_agents(): void
    {
        $dirbuster = $this->withHeaders(['User-Agent' => 'DirBuster-1.0-RC1'])
            ->getJson('/api/v1/business/health');
        $dirbuster->assertStatus(403);

        $gobuster = $this->withHeaders(['User-Agent' => 'gobuster 3.1.0'])
            ->getJson('/api/v1/business/health');
        $gobuster->assertStatus(403);
    }

    public function test_blocks_acunetix_and_nmap_user_agents(): void
    {
        $acunetix = $this->withHeaders(['User-Agent' => 'Acunetix-Product/1.0'])
            ->getJson('/api/v1/business/health');
        $acunetix->assertStatus(403);

        $nmap = $this->withHeaders(['User-Agent' => 'Nmap Scripting Engine'])
            ->getJson('/api/v1/business/health');
        $nmap->assertStatus(403);
    }

    // =========================================================================
    // 2. Reconnaissance Path Probing Tests
    // =========================================================================

    public function test_blocks_dot_env_reconnaissance_probe(): void
    {
        $response = $this->getJson('/api/v1/.env');

        $response->assertStatus(404);
        $response->assertJsonPath('error', 'NOT_FOUND');
    }

    public function test_blocks_dot_git_reconnaissance_probe(): void
    {
        $response = $this->getJson('/api/v1/.git/config');

        $response->assertStatus(404);
        $response->assertJsonPath('error', 'NOT_FOUND');
    }

    public function test_blocks_phpmyadmin_reconnaissance_probe(): void
    {
        $response = $this->getJson('/phpmyadmin/index.php');

        $response->assertStatus(404);
        $response->assertJsonPath('error', 'NOT_FOUND');
    }

    public function test_blocks_wordpress_probe(): void
    {
        $response = $this->getJson('/wp-admin/admin-ajax.php');

        $response->assertStatus(404);
        $response->assertJsonPath('error', 'NOT_FOUND');
    }

    // =========================================================================
    // 3. Path Traversal & Injection Pattern Tests
    // =========================================================================

    public function test_blocks_path_traversal_in_query_string(): void
    {
        $response = $this->getJson('/api/v1/business/health?file=../../../../etc/passwd');

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'BAD_REQUEST');
        $response->assertJsonPath('message', 'Invalid path structure detected.');
    }

    // =========================================================================
    // 4. Input Sanitization & Payload Protection Tests
    // =========================================================================

    public function test_sanitizes_null_byte_in_input(): void
    {
        $userUuid = (string) Str::uuid();

        $response = $this->withJwtAuth($userUuid, ['businesses.create'])
            ->postJson('/api/v1/businesses', [
                'name' => "Malicious\0Store Name",
                'code' => 'BIZ-NULL-1',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('businesses', [
            'name' => 'MaliciousStore Name',
            'code' => 'BIZ-NULL-1',
        ]);
    }

    public function test_rejects_payload_exceeding_2mb_size_limit(): void
    {
        $largeString = str_repeat('A', (int) (2.1 * 1024 * 1024)); // 2.1MB

        $response = $this->postJson('/api/v1/pos-devices/auth', [
            'machine_id' => 'POS-01',
            'machine_password' => $largeString,
        ]);

        $response->assertStatus(413);
        $response->assertJsonPath('error', 'PAYLOAD_TOO_LARGE');
        $response->assertJsonPath('message', 'Request payload exceeds the maximum allowed size of 2MB.');
    }

    // =========================================================================
    // 5. Rate Limiter Tests
    // =========================================================================

    public function test_auth_rate_limiter_blocks_excessive_attempts(): void
    {
        $business = Business::create(['name' => 'Rate Test BIZ', 'code' => 'BIZ-RT-1']);
        $outlet = Outlet::create(['business_id' => $business->id, 'code' => 'OUT-RT-1', 'name' => 'Outlet RT']);
        PosDevice::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'machine_id' => 'POS-RATE-01',
            'device_name' => 'Rate Terminal',
            'machine_password_hash' => Hash::make('correct-pwd'),
            'status' => 'active',
        ]);

        // Attempt 5 requests (within limit)
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/pos-devices/auth', [
                'machine_id' => 'POS-RATE-01',
                'machine_password' => 'wrong-pwd',
            ]);
        }

        // 6th attempt must be throttled with 429
        $response = $this->postJson('/api/v1/pos-devices/auth', [
            'machine_id' => 'POS-RATE-01',
            'machine_password' => 'wrong-pwd',
        ]);

        $response->assertStatus(429);
        $response->assertJsonPath('error', 'TOO_MANY_ATTEMPTS');
        $response->assertJsonStructure(['success', 'error', 'message', 'retry_after_seconds']);
    }

    // =========================================================================
    // 6. Database Error Masking Tests
    // =========================================================================

    public function test_database_query_exceptions_are_masked_in_api_responses(): void
    {
        $userUuid = (string) Str::uuid();

        // Trigger an intentional QueryException via route using invalid raw query
        \Illuminate\Support\Facades\Route::get('/api/v1/test-db-error', function () {
            \Illuminate\Support\Facades\DB::select('SELECT * FROM non_existent_table_xyz_123');
        });

        $response = $this->getJson('/api/v1/test-db-error');

        $response->assertStatus(500);
        $response->assertJsonPath('error', 'DATABASE_ERROR');
        $response->assertJsonPath('message', 'An unexpected database error occurred. Details have been logged.');
        // Ensure SQL query and table names are NOT leaked in response
        $response->assertJsonMissing(['non_existent_table_xyz_123']);
    }

    // =========================================================================
    // 7. CORS Configuration Tests
    // =========================================================================

    public function test_cors_preflight_allows_configured_origin(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type, Authorization',
        ])->options('/api/v1/business/health');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }
}

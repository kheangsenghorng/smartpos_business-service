<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuthenticatePosDeviceRequest;
use App\Http\Requests\StorePosDeviceRequest;
use App\Http\Requests\UpdatePosDeviceRequest;
use App\Models\BusinessUser;
use App\Models\DeviceSession;
use App\Models\Outlet;
use App\Models\PosDevice;
use App\Models\PosDeviceCredential;
use App\Models\Register;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PosDeviceController extends Controller
{
    /**
     * List all POS devices registered under the specified outlet.
     */
    public function index(Outlet $outlet): JsonResponse
    {
        $devices = $outlet->posDevices()->with(['register'])->get();

        return response()->json([
            'data' => $devices,
        ]);
    }

    /**
     * Register a new POS device under the specified outlet and generate machine credentials.
     */
    public function store(StorePosDeviceRequest $request, Outlet $outlet): JsonResponse
    {
        $data = $request->validated();

        $registerId = null;
        if (! empty($data['register_uuid'])) {
            $register = Register::where('uuid', $data['register_uuid'])
                ->where('outlet_id', $outlet->id)
                ->first();

            if (! $register) {
                return response()->json([
                    'message' => 'The selected register is invalid or does not belong to this outlet.',
                    'errors' => ['register_uuid' => ['The selected register does not belong to this outlet.']],
                ], 422);
            }

            $registerId = $register->id;
        }

        $plainPassword = Str::random(32);
        $passwordHash = Hash::make($plainPassword);

        $device = PosDevice::create([
            'business_id' => $outlet->business_id,
            'outlet_id' => $outlet->id,
            'register_id' => $registerId,
            'machine_id' => $data['machine_id'],
            'device_code' => $data['device_code'] ?? $data['machine_id'],
            'device_name' => $data['device_name'],
            'name' => $data['device_name'],
            'device_type' => $data['device_type'] ?? 'pos_terminal',
            'platform' => $data['platform'] ?? 'android',
            'os_version' => $data['os_version'] ?? null,
            'app_version' => $data['app_version'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'mac_address' => $data['mac_address'] ?? null,
            'machine_password_hash' => $passwordHash,
            'status' => 'pending',
            'registered_at' => now(),
        ]);

        PosDeviceCredential::create([
            'pos_device_id' => $device->id,
            'secret_hash' => $passwordHash,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'POS device registered successfully. Save the machine password now as it will not be shown again.',
            'machine_password' => $plainPassword,
            'data' => $device,
        ], 201);
    }

    /**
     * Display the specified POS device with business, outlet, and register details.
     */
    public function show(PosDevice $posDevice): JsonResponse
    {
        $posDevice->load(['business', 'outlet', 'register', 'credentials', 'deviceSessions']);

        return response()->json([
            'data' => $posDevice,
        ]);
    }

    /**
     * Update the specified POS device configuration and associations.
     */
    public function update(UpdatePosDeviceRequest $request, PosDevice $posDevice): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('outlet_uuid', $data)) {
            $targetOutlet = $data['outlet_uuid'] ? Outlet::where('uuid', $data['outlet_uuid'])->first() : null;
            if ($data['outlet_uuid'] && (! $targetOutlet || $targetOutlet->business_id !== $posDevice->business_id)) {
                return response()->json([
                    'message' => 'The selected outlet is invalid or does not belong to this business.',
                    'errors' => ['outlet_uuid' => ['The selected outlet does not belong to this business.']],
                ], 422);
            }
            $data['outlet_id'] = $targetOutlet?->id;
            unset($data['outlet_uuid']);
        }

        if (array_key_exists('register_uuid', $data)) {
            $targetRegister = $data['register_uuid'] ? Register::where('uuid', $data['register_uuid'])->first() : null;
            if ($data['register_uuid'] && (! $targetRegister || $targetRegister->business_id !== $posDevice->business_id)) {
                return response()->json([
                    'message' => 'The selected register is invalid or does not belong to this business.',
                    'errors' => ['register_uuid' => ['The selected register does not belong to this business.']],
                ], 422);
            }
            $data['register_id'] = $targetRegister?->id;
            unset($data['register_uuid']);
        }

        $posDevice->update($data);

        return response()->json([
            'message' => 'POS device updated successfully.',
            'data' => $posDevice->fresh(['business', 'outlet', 'register']),
        ]);
    }

    /**
     * Activate the specified POS device for operations.
     */
    public function activate(PosDevice $posDevice): JsonResponse
    {
        $posDevice->update([
            'status' => 'active',
            'activated_at' => now(),
        ]);

        return response()->json([
            'message' => 'POS device activated successfully.',
            'data' => $posDevice,
        ]);
    }

    /**
     * Revoke access for the specified POS device.
     */
    public function revoke(PosDevice $posDevice): JsonResponse
    {
        $posDevice->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);

        // Revoke credentials and active device sessions
        $posDevice->credentials()->update(['is_active' => false, 'revoked_at' => now()]);
        $posDevice->deviceSessions()->whereNull('revoked_at')->update(['revoked_at' => now()]);

        return response()->json([
            'message' => 'POS device revoked successfully.',
            'data' => $posDevice,
        ]);
    }

    /**
     * Lock the specified POS device to temporarily disable access.
     */
    public function lock(PosDevice $posDevice): JsonResponse
    {
        $posDevice->update(['status' => 'locked']);

        return response()->json([
            'message' => 'POS device locked successfully.',
            'data' => $posDevice,
        ]);
    }

    /**
     * Rotate credentials (machine password) for the specified POS device.
     */
    public function rotateSecret(Request $request, PosDevice $posDevice): JsonResponse
    {
        $userUuid = $request->attributes->get('user_uuid');
        $roles = $request->attributes->get('jwt_roles', []);

        if (! in_array('admin', $roles, true)) {
            $isOwner = BusinessUser::where('business_id', $posDevice->business_id)
                ->where('user_uuid', $userUuid)
                ->where('status', 'active')
                ->where('is_owner', true)
                ->exists();

            if (! $isOwner) {
                return response()->json([
                    'message' => 'Forbidden. Only business owners or administrators can rotate hardware credentials.',
                ], 403);
            }
        }

        $newPlainPassword = Str::random(32);
        $newHash = Hash::make($newPlainPassword);

        $posDevice->update([
            'machine_password_hash' => $newHash,
        ]);

        $posDevice->credentials()->update(['is_active' => false]);
        PosDeviceCredential::create([
            'pos_device_id' => $posDevice->id,
            'secret_hash' => $newHash,
            'is_active' => true,
            'last_rotated_at' => now(),
        ]);

        return response()->json([
            'message' => 'POS device credentials rotated successfully. Save the new machine password now as it will not be shown again.',
            'machine_password' => $newPlainPassword,
            'data' => $posDevice->fresh(['business', 'outlet', 'register']),
        ]);
    }

    /**
     * Authenticate a POS device using machine ID and machine password.
     */
    public function authenticate(AuthenticatePosDeviceRequest $request): JsonResponse
    {
        $data = $request->validated();

        $device = PosDevice::where('machine_id', $data['machine_id'])
            ->with(['business', 'outlet', 'register', 'credentials'])
            ->first();

        $isAuthenticated = false;
        if ($device) {
            if (Hash::check($data['machine_password'], $device->machine_password_hash)) {
                $isAuthenticated = true;
            } elseif ($device->activeCredential && Hash::check($data['machine_password'], $device->activeCredential->secret_hash)) {
                $isAuthenticated = true;
            }
        }

        if (! $device || ! $isAuthenticated) {
            Log::warning('[SECURITY_POS_AUTH_FAILED] Invalid POS device authentication attempt', [
                'machine_id' => $data['machine_id'] ?? null,
                'ip' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'timestamp' => now()->toIso8601String(),
            ]);

            return response()->json([
                'message' => 'Invalid POS machine ID or password.',
            ], 401);
        }

        if ($device->status === 'revoked') {
            Log::warning('[SECURITY_POS_AUTH_BLOCKED] Revoked POS device attempted authentication', [
                'machine_id' => $device->machine_id,
                'device_uuid' => $device->uuid,
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return response()->json([
                'message' => 'POS device access has been revoked.',
            ], 403);
        }

        if ($device->status === 'locked') {
            Log::warning('[SECURITY_POS_AUTH_BLOCKED] Locked POS device attempted authentication', [
                'machine_id' => $device->machine_id,
                'device_uuid' => $device->uuid,
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return response()->json([
                'message' => 'POS device is currently locked.',
            ], 403);
        }

        if ($device->status !== 'active') {
            return response()->json([
                'message' => 'POS device is not active. Current status: ' . $device->status,
            ], 403);
        }

        $rawSessionToken = Str::random(64);
        $deviceSession = DeviceSession::create([
            'pos_device_id' => $device->id,
            'token_hash' => hash('sha256', $rawSessionToken),
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'started_at' => now(),
            'last_activity_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        $device->update(['last_seen_at' => now()]);

        return response()->json([
            'message' => 'POS device authenticated successfully.',
            'session_token' => $rawSessionToken,
            'device_session_uuid' => $deviceSession->uuid,
            'context' => [
                'pos_device' => $device,
                'business' => $device->business,
                'outlet' => $device->outlet,
                'register' => $device->register,
            ],
        ]);
    }
}

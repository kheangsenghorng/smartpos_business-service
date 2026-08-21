<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartCashierSessionRequest;
use App\Http\Requests\UnlockCashierSessionRequest;
use App\Models\BusinessUser;
use App\Models\CashierSession;
use App\Models\Outlet;
use App\Models\PosDevice;
use App\Models\Register;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class CashierSessionController extends Controller
{
    /**
     * Start a new cashier session at the specified outlet.
     */
    public function store(StartCashierSessionRequest $request, Outlet $outlet): JsonResponse
    {
        $data = $request->validated();

        $register = Register::where('uuid', $data['register_uuid'])
            ->where('outlet_id', $outlet->id)
            ->first();

        if (! $register) {
            return response()->json([
                'message' => 'The selected register does not belong to this outlet.',
                'errors' => ['register_uuid' => ['Invalid register for this outlet.']],
            ], 422);
        }

        $posDevice = PosDevice::where('uuid', $data['pos_device_uuid'])
            ->where('outlet_id', $outlet->id)
            ->first();

        if (! $posDevice) {
            return response()->json([
                'message' => 'The selected POS device does not belong to this outlet.',
                'errors' => ['pos_device_uuid' => ['Invalid POS device for this outlet.']],
            ], 422);
        }

        $businessUser = BusinessUser::where('business_id', $outlet->business_id)
            ->where('user_uuid', $data['user_uuid'])
            ->where('status', 'active')
            ->first();

        if (! $businessUser) {
            return response()->json([
                'message' => 'Cashier user is not an active member of this business.',
                'errors' => ['user_uuid' => ['User is not an active staff member in this business.']],
            ], 422);
        }

        // End any existing active sessions on this POS device / register
        CashierSession::where('pos_device_id', $posDevice->id)
            ->whereIn('status', ['active', 'locked'])
            ->update([
                'status' => 'ended',
                'ended_at' => now(),
            ]);

        $session = CashierSession::create([
            'business_id' => $outlet->business_id,
            'outlet_id' => $outlet->id,
            'register_id' => $register->id,
            'pos_device_id' => $posDevice->id,
            'business_user_id' => $businessUser->id,
            'user_uuid' => $data['user_uuid'],
            'status' => 'active',
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        return response()->json([
            'message' => 'Cashier session started successfully.',
            'data' => $session->load(['businessUser.cashierProfile', 'register', 'posDevice']),
        ], 201);
    }

    /**
     * Get the current active/locked cashier session for a register or device in this outlet.
     */
    public function current(Request $request, Outlet $outlet): JsonResponse
    {
        $query = CashierSession::where('outlet_id', $outlet->id)
            ->whereIn('status', ['active', 'locked'])
            ->with(['businessUser.cashierProfile', 'register', 'posDevice']);

        if ($request->has('register_uuid')) {
            $register = Register::where('uuid', $request->query('register_uuid'))
                ->where('outlet_id', $outlet->id)
                ->first();
            if ($register) {
                $query->where('register_id', $register->id);
            }
        }

        if ($request->has('pos_device_uuid')) {
            $posDevice = PosDevice::where('uuid', $request->query('pos_device_uuid'))
                ->where('outlet_id', $outlet->id)
                ->first();
            if ($posDevice) {
                $query->where('pos_device_id', $posDevice->id);
            }
        }

        $session = $query->latest('started_at')->first();

        if (! $session) {
            return response()->json([
                'message' => 'No active cashier session found.',
                'data' => null,
            ], 200);
        }

        return response()->json([
            'data' => $session,
        ]);
    }

    /**
     * Lock the specified cashier session.
     */
    public function lock(Outlet $outlet, CashierSession $cashierSession): JsonResponse
    {
        if ($cashierSession->outlet_id !== $outlet->id) {
            return response()->json(['message' => 'Session does not belong to this outlet.'], 404);
        }

        if ($cashierSession->status !== 'active') {
            return response()->json([
                'message' => 'Only active sessions can be locked. Current status: '.$cashierSession->status,
            ], 422);
        }

        $cashierSession->update([
            'status' => 'locked',
            'locked_at' => now(),
            'last_activity_at' => now(),
        ]);

        return response()->json([
            'message' => 'Cashier session locked successfully.',
            'data' => $cashierSession,
        ]);
    }

    /**
     * Unlock the specified locked cashier session.
     *
     * SEC-02 FIX: A cashier session cannot be unlocked via PIN if the business
     * user has no pin_code_hash set. Only a platform admin (jwt role: admin)
     * may unlock a pin-less session, preventing unauthorized terminal access.
     */
    public function unlock(UnlockCashierSessionRequest $request, Outlet $outlet, CashierSession $cashierSession): JsonResponse
    {
        if ($cashierSession->outlet_id !== $outlet->id) {
            return response()->json(['message' => 'Session does not belong to this outlet.'], 404);
        }

        if ($cashierSession->status !== 'locked') {
            return response()->json([
                'message' => 'Session is not locked. Current status: '.$cashierSession->status,
            ], 422);
        }

        $cashierSession->load('businessUser');
        $businessUser = $cashierSession->businessUser;
        $isAdmin = in_array('admin', $request->attributes->get('jwt_roles', []), true);

        // SEC-02 FIX: Block unlock if the cashier has no PIN configured.
        // Platform admins are exempt from this check and may unlock any session.
        if (! $isAdmin) {
            if (! $businessUser || ! $businessUser->pin_code_hash) {
                Log::warning('[SECURITY_CASHIER_UNLOCK_BLOCKED] Unlock attempted on PIN-less cashier session', [
                    'cashier_session_uuid' => $cashierSession->uuid ?? $cashierSession->id,
                    'outlet_id' => $outlet->id,
                    'ip' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                    'timestamp' => now()->toIso8601String(),
                ]);

                return response()->json([
                    'message' => 'This cashier session cannot be unlocked: the cashier has no PIN code set. '.
                                 'Please ask a platform administrator to unlock, or set a PIN code via the Business User settings.',
                ], 403);
            }

            $pin = (string) $request->input('pin_code', '');
            if ($pin === '' || ! Hash::check($pin, $businessUser->pin_code_hash)) {
                Log::warning('[SECURITY_CASHIER_PIN_FAILED] Invalid cashier PIN unlock attempt', [
                    'cashier_session_uuid' => $cashierSession->uuid ?? $cashierSession->id,
                    'outlet_id' => $outlet->id,
                    'ip' => $request->ip(),
                    'timestamp' => now()->toIso8601String(),
                ]);

                return response()->json([
                    'message' => 'Invalid cashier PIN code.',
                ], 401);
            }
        }

        $cashierSession->update([
            'status' => 'active',
            'locked_at' => null,
            'last_activity_at' => now(),
        ]);

        return response()->json([
            'message' => 'Cashier session unlocked successfully.',
            'data' => $cashierSession,
        ]);
    }

    /**
     * End the specified cashier session.
     */
    public function end(Outlet $outlet, CashierSession $cashierSession): JsonResponse
    {
        if ($cashierSession->outlet_id !== $outlet->id) {
            return response()->json(['message' => 'Session does not belong to this outlet.'], 404);
        }

        if ($cashierSession->status === 'ended') {
            return response()->json(['message' => 'Session is already ended.'], 422);
        }

        $cashierSession->update([
            'status' => 'ended',
            'ended_at' => now(),
            'last_activity_at' => now(),
        ]);

        return response()->json([
            'message' => 'Cashier session ended successfully.',
            'data' => $cashierSession,
        ]);
    }
}

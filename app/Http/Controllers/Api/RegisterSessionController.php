<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseRegisterSessionRequest;
use App\Http\Requests\OpenRegisterSessionRequest;
use App\Models\CashDrawerMovement;
use App\Models\CashDrawerSession;
use App\Models\Outlet;
use App\Models\PosDevice;
use App\Models\Register;
use App\Models\RegisterSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RegisterSessionController extends Controller
{
    /**
     * List shift sessions for the specified register.
     */
    public function index(Outlet $outlet, Register $register): JsonResponse
    {
        if ($register->outlet_id !== $outlet->id) {
            return response()->json(['message' => 'Register does not belong to this outlet.'], 404);
        }

        $sessions = $register->registerSessions()
            ->with(['posDevice', 'cashDrawerSession'])
            ->latest('opened_at')
            ->paginate(20);

        return response()->json($sessions);
    }

    /**
     * Get the current open shift session for the register.
     */
    public function current(Outlet $outlet, Register $register): JsonResponse
    {
        if ($register->outlet_id !== $outlet->id) {
            return response()->json(['message' => 'Register does not belong to this outlet.'], 404);
        }

        $session = $register->registerSessions()
            ->where('status', 'open')
            ->with(['posDevice', 'cashDrawerSession.movements'])
            ->latest('opened_at')
            ->first();

        if (! $session) {
            return response()->json([
                'message' => 'No active open shift found for this register.',
                'data' => null,
            ], 200);
        }

        return response()->json([
            'data' => $session,
        ]);
    }

    /**
     * Open a new register shift and initialize the cash drawer session.
     */
    public function open(OpenRegisterSessionRequest $request, Outlet $outlet, Register $register): JsonResponse
    {
        if ($register->outlet_id !== $outlet->id) {
            return response()->json(['message' => 'Register does not belong to this outlet.'], 404);
        }

        // Check if there is already an open shift
        $existingOpen = $register->registerSessions()
            ->where('status', 'open')
            ->first();

        if ($existingOpen) {
            return response()->json([
                'message' => 'There is already an active open shift on this register. Close it before opening a new one.',
                'data' => $existingOpen->load('cashDrawerSession'),
            ], 422);
        }

        $userUuid = $request->attributes->get('user_uuid');
        $data = $request->validated();

        $posDeviceId = null;
        if (! empty($data['pos_device_uuid'])) {
            $posDevice = PosDevice::where('uuid', $data['pos_device_uuid'])
                ->where('outlet_id', $outlet->id)
                ->first();
            $posDeviceId = $posDevice?->id;
        }

        $session = DB::transaction(function () use ($outlet, $register, $posDeviceId, $userUuid, $data) {
            $regSession = RegisterSession::create([
                'business_id' => $outlet->business_id,
                'outlet_id' => $outlet->id,
                'register_id' => $register->id,
                'pos_device_id' => $posDeviceId,
                'opened_by_user_uuid' => $userUuid,
                'opening_cash' => $data['opening_cash'],
                'status' => 'open',
                'opened_at' => now(),
            ]);

            $drawerSession = CashDrawerSession::create([
                'register_session_id' => $regSession->id,
                'business_id' => $outlet->business_id,
                'outlet_id' => $outlet->id,
                'register_id' => $register->id,
                'opening_amount' => $data['opening_cash'],
                'expected_amount' => $data['opening_cash'],
                'status' => 'open',
                'opened_at' => now(),
            ]);

            CashDrawerMovement::create([
                'cash_drawer_session_id' => $drawerSession->id,
                'business_id' => $outlet->business_id,
                'outlet_id' => $outlet->id,
                'register_id' => $register->id,
                'user_uuid' => $userUuid,
                'type' => 'opening',
                'amount' => $data['opening_cash'],
                'reason' => 'Opening float',
                'notes' => $data['notes'] ?? null,
            ]);

            return $regSession;
        });

        return response()->json([
            'message' => 'Register shift opened successfully.',
            'data' => $session->fresh(['posDevice', 'cashDrawerSession.movements']),
        ], 201);
    }

    /**
     * Close the specified register shift and finalize drawer balance.
     */
    public function close(CloseRegisterSessionRequest $request, Outlet $outlet, Register $register, RegisterSession $registerSession): JsonResponse
    {
        if ($registerSession->outlet_id !== $outlet->id || $registerSession->register_id !== $register->id) {
            return response()->json(['message' => 'Shift session does not belong to this register/outlet.'], 404);
        }

        if ($registerSession->status !== 'open') {
            return response()->json([
                'message' => 'Shift session is already closed. Current status: '.$registerSession->status,
            ], 422);
        }

        $userUuid = $request->attributes->get('user_uuid');
        $data = $request->validated();
        $closingCash = (float) $data['closing_cash'];

        $session = DB::transaction(function () use ($registerSession, $userUuid, $closingCash, $data, $outlet, $register) {
            $drawerSession = $registerSession->cashDrawerSession;

            // Calculate expected cash from drawer movements
            $netMovement = CashDrawerMovement::where('cash_drawer_session_id', $drawerSession?->id)
                ->sum('amount');

            $expectedCash = (float) $netMovement;
            $difference = $closingCash - $expectedCash;

            // Record closing movement
            if ($drawerSession) {
                CashDrawerMovement::create([
                    'cash_drawer_session_id' => $drawerSession->id,
                    'business_id' => $outlet->business_id,
                    'outlet_id' => $outlet->id,
                    'register_id' => $register->id,
                    'user_uuid' => $userUuid,
                    'type' => 'closing',
                    'amount' => -$closingCash,
                    'reason' => 'Closing shift reconciliation',
                    'notes' => $data['notes'] ?? null,
                ]);

                $drawerSession->update([
                    'expected_amount' => $expectedCash,
                    'counted_amount' => $closingCash,
                    'difference_amount' => $difference,
                    'status' => 'closed',
                    'closed_at' => now(),
                ]);
            }

            $registerSession->update([
                'closed_by_user_uuid' => $userUuid,
                'expected_cash' => $expectedCash,
                'closing_cash' => $closingCash,
                'difference_amount' => $difference,
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            return $registerSession;
        });

        return response()->json([
            'message' => 'Register shift closed successfully.',
            'data' => $session->fresh(['posDevice', 'cashDrawerSession.movements']),
        ]);
    }
}

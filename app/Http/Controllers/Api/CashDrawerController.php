<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordCashMovementRequest;
use App\Models\CashDrawerMovement;
use App\Models\CashDrawerSession;
use App\Models\Outlet;
use App\Models\Register;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CashDrawerController extends Controller
{
    /**
     * Display the specified cash drawer session summary and balance.
     */
    public function show(Outlet $outlet, Register $register, CashDrawerSession $cashDrawerSession): JsonResponse
    {
        if ($cashDrawerSession->outlet_id !== $outlet->id || $cashDrawerSession->register_id !== $register->id) {
            return response()->json(['message' => 'Cash drawer session does not belong to this register/outlet.'], 404);
        }

        $currentBalance = CashDrawerMovement::where('cash_drawer_session_id', $cashDrawerSession->id)
            ->where('type', '!=', 'closing')
            ->sum('amount');

        $cashDrawerSession->load(['registerSession', 'movements']);

        return response()->json([
            'data' => $cashDrawerSession,
            'current_balance' => (float) $currentBalance,
        ]);
    }

    /**
     * List all cash movements for the specified cash drawer session.
     */
    public function movements(Outlet $outlet, Register $register, CashDrawerSession $cashDrawerSession): JsonResponse
    {
        if ($cashDrawerSession->outlet_id !== $outlet->id || $cashDrawerSession->register_id !== $register->id) {
            return response()->json(['message' => 'Cash drawer session does not belong to this register/outlet.'], 404);
        }

        $movements = $cashDrawerSession->movements()
            ->latest('created_at')
            ->get();

        return response()->json([
            'data' => $movements,
        ]);
    }

    /**
     * Record a new cash movement (cash in, cash out, payout, deposit, adjustment, sale, refund) into the cash drawer.
     */
    public function recordMovement(RecordCashMovementRequest $request, Outlet $outlet, Register $register, CashDrawerSession $cashDrawerSession): JsonResponse
    {
        if ($cashDrawerSession->outlet_id !== $outlet->id || $cashDrawerSession->register_id !== $register->id) {
            return response()->json(['message' => 'Cash drawer session does not belong to this register/outlet.'], 404);
        }

        if ($cashDrawerSession->status !== 'open') {
            return response()->json([
                'message' => 'Cannot record movement on a closed cash drawer session.',
            ], 422);
        }

        $userUuid = $request->attributes->get('user_uuid');
        $data = $request->validated();

        $rawAmount = (float) $data['amount'];
        // Outbound movements reduce cash (stored as negative amounts), inbound increases cash
        if (in_array($data['type'], ['cash_out', 'payout', 'cash_refund'], true)) {
            $amount = -abs($rawAmount);
        } else {
            $amount = abs($rawAmount);
        }

        [$movement, $newBalance] = DB::transaction(function () use ($cashDrawerSession, $outlet, $register, $userUuid, $data, $amount) {
            // Lock the cash drawer session row to prevent race conditions during concurrent balance calculations
            $lockedSession = CashDrawerSession::where('id', $cashDrawerSession->id)
                ->lockForUpdate()
                ->first();

            $movement = CashDrawerMovement::create([
                'cash_drawer_session_id' => $lockedSession ? $lockedSession->id : $cashDrawerSession->id,
                'business_id' => $outlet->business_id,
                'outlet_id' => $outlet->id,
                'register_id' => $register->id,
                'user_uuid' => $userUuid,
                'type' => $data['type'],
                'amount' => $amount,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_uuid' => $data['reference_uuid'] ?? null,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $newBalance = CashDrawerMovement::where('cash_drawer_session_id', $cashDrawerSession->id)
                ->sum('amount');

            if ($lockedSession) {
                $lockedSession->update([
                    'expected_amount' => $newBalance,
                ]);
            } else {
                $cashDrawerSession->update([
                    'expected_amount' => $newBalance,
                ]);
            }

            return [$movement, $newBalance];
        });

        return response()->json([
            'message' => 'Cash movement recorded successfully.',
            'data' => $movement,
            'current_balance' => (float) $newBalance,
        ], 201);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceSession;
use App\Models\PosDevice;
use Illuminate\Http\JsonResponse;

class DeviceSessionController extends Controller
{
    /**
     * List all active and past sessions for the specified POS device.
     */
    public function index(PosDevice $posDevice): JsonResponse
    {
        $sessions = $posDevice->deviceSessions()
            ->latest('started_at')
            ->paginate(20);

        return response()->json($sessions);
    }

    /**
     * Revoke the specified device session.
     */
    public function revoke(PosDevice $posDevice, DeviceSession $deviceSession): JsonResponse
    {
        if ($deviceSession->pos_device_id !== $posDevice->id) {
            return response()->json(['message' => 'Session does not belong to this POS device.'], 404);
        }

        $deviceSession->update([
            'revoked_at' => now(),
        ]);

        return response()->json([
            'message' => 'Device session revoked successfully.',
            'data' => $deviceSession,
        ]);
    }
}

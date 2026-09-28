<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Push\RegisterDeviceTokenRequest;
use App\Http\Requests\Api\Push\RemoveDeviceTokenRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    /** Called on login and app open, and whenever the FCM SDK reports a fresh token. */
    public function store(RegisterDeviceTokenRequest $request): JsonResponse
    {
        // `token` is unique across all users: re-registering the same device
        // (app reinstall, different account on the same phone) reassigns it.
        $request->user('sanctum')->deviceTokens()->updateOrCreate(
            ['token' => $request->string('token')->toString()],
            [
                'platform' => $request->input('platform'),
                'device_name' => $request->input('device_name'),
                'last_used_at' => now(),
            ],
        );

        return response()->json(['message' => 'Device registered.']);
    }

    /** Called on logout so this device stops receiving push notifications. */
    public function destroy(RemoveDeviceTokenRequest $request): JsonResponse
    {
        $request->user('sanctum')->deviceTokens()
            ->where('token', $request->string('token')->toString())
            ->delete();

        return response()->json(['message' => 'Device removed.']);
    }
}

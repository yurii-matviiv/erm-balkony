<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Stores / removes the current user's browser push subscription (called from the reminders page). */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        // Upsert by endpoint: the same browser re-subscribing (or another user logging in
        // on the same browser) must not create duplicates.
        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $data['endpoint'],
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => ['required', 'url']]);

        PushSubscription::where('endpoint_hash', hash('sha256', $request->input('endpoint')))
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['ok' => true]);
    }
}

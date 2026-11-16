<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Sends a Web Push to every browser a user has subscribed. One place for it, so the admin
 * "test" button and the per-minute reminders dispatcher (docs/SCHEDULER.md) behave identically.
 * Subscriptions the push service reports as gone (404/410) are deleted here, so dead
 * browsers do not pile up.
 */
class WebPushSender
{
    /** @return array{subscriptions:int, sent:int, failed:int, errors:list<string>} */
    public function sendToUser(User $user, string $title, string $body, string $url = '/'): array
    {
        $subscriptions = PushSubscription::where('user_id', $user->id)->get();
        $result = ['subscriptions' => $subscriptions->count(), 'sent' => 0, 'failed' => 0, 'errors' => []];

        if ($subscriptions->isEmpty()) {
            return $result;
        }

        if (! config('webpush.public_key') || ! config('webpush.private_key')) {
            $result['failed'] = $result['subscriptions'];
            $result['errors'][] = 'VAPID keys are not configured on the server.';

            return $result;
        }

        $webPush = new WebPush(['VAPID' => [
            'subject' => config('webpush.subject'),
            'publicKey' => config('webpush.public_key'),
            'privateKey' => config('webpush.private_key'),
        ]]);

        $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url], JSON_UNESCAPED_UNICODE);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->p256dh,
                'authToken' => $subscription->auth,
                'contentEncoding' => 'aes128gcm',
            ]), $payload);
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $result['sent']++;

                continue;
            }

            $result['failed']++;
            $result['errors'][] = $report->getReason();

            if ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint_hash', hash('sha256', $report->getEndpoint()))->delete();
            }
        }

        return $result;
    }
}

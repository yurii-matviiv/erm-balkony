<?php

namespace App\Services\Audiences;

use App\Models\AppSetting;
use App\Models\Client;
use App\Services\Conversions\DataManagerApiService;
use App\Services\Conversions\UserDataBuilder;

/**
 * Customer Match list (hashed phone/email of clients) in Google Ads via Data Manager API.
 * Raw phones/emails are never stored or logged; only SHA-256 hashes are sent.
 */
class CustomerMatchService
{
    public function __construct(
        private DataManagerApiService $api,
        private UserDataBuilder $userData,
    ) {}

    public function segments(): array
    {
        return array_keys((array) config('customer_match.segments', []));
    }

    public function listId(string $segment): ?string
    {
        $id = AppSetting::get('customer_match_list_'.$segment);

        return $id !== null && $id !== '' ? (string) $id : null;
    }

    public function setListId(string $segment, string $id): void
    {
        AppSetting::set('customer_match_list_'.$segment, $id);
    }

    /**
     * Creates the Customer Match list in Google Ads (no personal data is sent).
     *
     * @return array{id: ?string, name: ?string, validated_only: bool}
     */
    public function createList(string $segment, bool $live): array
    {
        $cfg = $this->segmentConfig($segment);
        $account = $this->account();

        $body = [
            'displayName' => $cfg['name'],
            'description' => $cfg['description'],
            'membershipDuration' => ((int) config('customer_match.membership_days', 540) * 86400).'s',
            'ingestedUserListInfo' => [
                'contactIdInfo' => ['dataSourceType' => 'DATA_SOURCE_TYPE_FIRST_PARTY'],
                'uploadKeyTypes' => ['CONTACT_ID'],
            ],
        ];

        $response = $this->api->postJson(
            $account,
            "accountTypes/GOOGLE_ADS/accounts/{$account->customer_id}/userLists",
            $body,
            $live ? [] : ['validateOnly' => 'true'],
        );

        $id = isset($response['id']) ? (string) $response['id'] : null;

        if ($live && $id) {
            $this->setListId($segment, $id);
        }

        return ['id' => $id, 'name' => $response['name'] ?? null, 'validated_only' => ! $live];
    }

    /**
     * Collects hashed members for a segment (nothing is sent).
     *
     * @return array{members: array<int, array<string, mixed>>, clients: int, without_identifier: int}
     */
    public function collect(string $segment): array
    {
        $this->segmentConfig($segment);

        $query = Client::query()->select(['id', 'phone', 'phone2', 'email']);

        if ($segment === 'buyers') {
            $query->whereHas('orders');
        }

        $members = [];
        $seen = [];
        $clients = 0;
        $without = 0;

        $query->chunkById(1000, function ($chunk) use (&$members, &$seen, &$clients, &$without) {
            foreach ($chunk as $client) {
                $clients++;
                $identifiers = $this->userData->forClient($client);

                if ($identifiers === []) {
                    $without++;

                    continue;
                }

                $key = implode('|', array_map(fn ($i) => array_values($i)[0], $identifiers));

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $members[] = ['compositeData' => ['userData' => ['userIdentifiers' => $identifiers]]];
            }
        });

        return ['members' => $members, 'clients' => $clients, 'without_identifier' => $without];
    }

    /**
     * Sends the segment to its list. Without $live only validateOnly runs.
     *
     * @return array<string, mixed>
     */
    public function sync(string $segment, bool $live): array
    {
        if ($live && ! config('customer_match.confirmed')) {
            throw new \RuntimeException('Реальна відправка вимкнена: у .env немає CUSTOMER_MATCH_CONFIRMED=true.');
        }

        $account = $this->account();
        $listId = $this->listId($segment);
        $data = $this->collect($segment);

        $stats = [
            'segment' => $segment,
            'clients' => $data['clients'],
            'members' => count($data['members']),
            'without_identifier' => $data['without_identifier'],
            'list_id' => $listId,
            'mode' => $live ? 'live' : 'validateOnly',
            'batches' => 0,
            'request_ids' => [],
        ];

        if ($listId === null) {
            $stats['note'] = 'Список ще не створено: спершу audience:create '.$segment.' --live';

            return $stats;
        }

        foreach (array_chunk($data['members'], max(1, (int) config('customer_match.batch_size', 5000))) as $batch) {
            $response = $this->api->postJson($account, 'audienceMembers:ingest', [
                'destinations' => [[
                    'operatingAccount' => ['accountType' => 'GOOGLE_ADS', 'accountId' => $account->customer_id],
                    'productDestinationId' => $listId,
                ]],
                'audienceMembers' => $batch,
                'consent' => [
                    'adUserData' => 'CONSENT_GRANTED',
                    'adPersonalization' => 'CONSENT_GRANTED',
                ],
                'encoding' => 'HEX',
                'termsOfService' => ['customerMatchTermsOfServiceStatus' => 'ACCEPTED'],
                'validateOnly' => ! $live,
            ]);

            $stats['batches']++;
            $stats['request_ids'][] = $response['requestId'] ?? null;
        }

        if ($live) {
            AppSetting::set('customer_match_synced_at_'.$segment, now()->toIso8601String());
        }

        return $stats;
    }

    private function account()
    {
        $account = $this->api->activeAccount();

        if (! $account) {
            throw new \RuntimeException('Немає активного підключення Google Ads.');
        }

        return $account;
    }

    /**
     * @return array{name: string, description: string}
     */
    private function segmentConfig(string $segment): array
    {
        $cfg = config('customer_match.segments.'.$segment);

        if (! $cfg) {
            throw new \InvalidArgumentException("Невідомий сегмент: {$segment} (доступні: ".implode(', ', $this->segments()).')');
        }

        return $cfg;
    }
}

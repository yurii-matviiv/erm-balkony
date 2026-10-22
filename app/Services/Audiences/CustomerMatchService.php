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
     * qualified/unqualified: only clients whose list differs from the last upload (incremental).
     *
     * @return array{members: array<int, array<string, mixed>>, ids: array<int, int>, clients: int, without_identifier: int}
     */
    public function collect(string $segment, ?string $removeFrom = null): array
    {
        $this->segmentConfig($segment);

        $query = Client::query()->select(['id', 'phone', 'phone2', 'email']);

        if ($removeFrom !== null) {
            // клієнти, що стали кваліфікованими, але лежать у списку некваліфікованих
            $query->where('ad_status', $segment)->where('ad_synced_status', $removeFrom);
        } elseif ($segment === 'buyers') {
            $query->whereHas('orders');
        } else {
            $query->where('ad_status', $segment)
                ->where(fn ($q) => $q->whereNull('ad_synced_status')->orWhere('ad_synced_status', '!=', $segment));
        }

        $members = [];
        $ids = [];
        $seen = [];
        $clients = 0;
        $without = 0;

        $query->chunkById(1000, function ($chunk) use (&$members, &$ids, &$seen, &$clients, &$without) {
            foreach ($chunk as $client) {
                $clients++;
                $ids[] = $client->id;
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

        return ['members' => $members, 'ids' => $ids, 'clients' => $clients, 'without_identifier' => $without];
    }

    /**
     * Sends the segment to its list. Without $live only validateOnly runs.
     * For `qualified` also removes upgraded clients from the `unqualified` list.
     *
     * @return array<string, mixed>
     */
    public function sync(string $segment, bool $live): array
    {
        if ($live && ! config('customer_match.confirmed')) {
            throw new \RuntimeException('Реальна відправка вимкнена: у .env немає CUSTOMER_MATCH_CONFIRMED=true.');
        }

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

        // апгрейд: був у некваліфікованих -> зібрати до маркування
        $moved = $segment === 'qualified' && $this->listId('unqualified') !== null
            ? $this->collect('qualified', 'unqualified')
            : null;

        $stats['batches'] += $this->send('audienceMembers:ingest', $listId, $data['members'], $live, $stats['request_ids']);

        $incremental = in_array($segment, ['qualified', 'unqualified'], true);

        if ($live && $incremental) {
            foreach (array_chunk($data['ids'], 1000) as $part) {
                Client::whereIn('id', $part)->update(['ad_synced_status' => $segment]);
            }
        }

        // видалити з некваліфікованих
        if ($moved !== null) {
            $other = $this->listId('unqualified');
            $stats['removed_from_unqualified'] = count($moved['members']);
            $stats['batches'] += $this->send('audienceMembers:remove', $other, $moved['members'], $live, $stats['request_ids']);

            if ($live) {
                foreach (array_chunk($moved['ids'], 1000) as $part) {
                    Client::whereIn('id', $part)->update(['ad_synced_status' => 'qualified']);
                }
            }
        }

        if ($live) {
            AppSetting::set('customer_match_synced_at_'.$segment, now()->toIso8601String());
        }

        return $stats;
    }

    /**
     * Deletes the segment's list in Google Ads and forgets its id.
     */
    public function deleteList(string $segment, bool $live): array
    {
        $listId = $this->listId($segment);

        if ($listId === null) {
            return ['segment' => $segment, 'note' => 'Список не створено'];
        }

        $account = $this->account();
        $this->api->deleteResource($account, "accountTypes/GOOGLE_ADS/accounts/{$account->customer_id}/userLists/{$listId}", ! $live);

        if ($live) {
            AppSetting::set('customer_match_list_'.$segment, '');
        }

        return ['segment' => $segment, 'list_id' => $listId, 'mode' => $live ? 'deleted' : 'validateOnly'];
    }

    /**
     * @param  array<int, array<string, mixed>>  $members
     * @param  array<int, mixed>  $requestIds
     */
    private function send(string $method, string $listId, array $members, bool $live, array &$requestIds): int
    {
        $account = $this->account();
        $batches = 0;

        foreach (array_chunk($members, max(1, (int) config('customer_match.batch_size', 5000))) as $batch) {
            $body = [
                'destinations' => [[
                    'operatingAccount' => ['accountType' => 'GOOGLE_ADS', 'accountId' => $account->customer_id],
                    'productDestinationId' => $listId,
                ]],
                'audienceMembers' => $batch,
                'encoding' => 'HEX',
                'validateOnly' => ! $live,
            ];

            if ($method === 'audienceMembers:ingest') {
                $body['consent'] = ['adUserData' => 'CONSENT_GRANTED', 'adPersonalization' => 'CONSENT_GRANTED'];
                $body['termsOfService'] = ['customerMatchTermsOfServiceStatus' => 'ACCEPTED'];
            }

            $response = $this->api->postJson($account, $method, $body);
            $batches++;
            $requestIds[] = $response['requestId'] ?? null;
        }

        return $batches;
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

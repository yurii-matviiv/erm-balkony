<?php

namespace App\Services\Sync\Mappers;

use App\Models\Lead;
use App\Models\LeadServiceType;
use App\Services\Sync\AbstractSyncMapper;
use Illuminate\Support\Facades\DB;

/**
 * Syncs `leads` (old CRM, ~14k rows) into the new `leads` table plus a
 * 1:1 `lead_marketing_data` row — see that table's migration docblock for
 * why marketing/attribution data is a separate table from the lead
 * itself. `profile` (window profile, e.g. rehau_60) and
 * `advance_calculation1-3` (preliminary price) are deliberately NOT
 * migrated — they belong to the future "Замовлення" module, not a Lead;
 * the data isn't lost, it's still in the legacy DB during the parallel
 * run.
 *
 * Rows where the old `caller_type` is 'spam', 'other', or 'supplier' are
 * skipped entirely — same reasoning as the Client/Supplier split
 * elsewhere in this project: those were never real sales leads.
 *
 * `application_type` (new vs repeat) is computed the same way the live
 * "Додати заявку" form computes it: a lead is "repeat" if its client
 * already has an earlier Lead. Because rows are synced in ascending old-id
 * (i.e. chronological) order and each row is committed before the next is
 * processed, checking this DURING the sync correctly replays history.
 */
class LeadsSyncMapper extends AbstractSyncMapper
{
    /**
     * @var array<string, string>
     */
    private const SERVICE_TYPE_MAP = [
        'window' => 'Вікна',
        'balcony' => 'Балкони',
        'window_in_cottage' => 'Вікна в котеджі',
        'window_repair' => 'Ремонт вікон',
        'glass_unit_replacement' => 'Заміна склопакета',
        'balcony_cladding' => 'Обшивка балкона',
        'balcony_with_takeout' => 'Балкон з виносом',
        'mosquito_net' => 'Сітки',
        'turnkey_balcony' => 'Балкон під ключ',
        'windowsill' => 'Підвіконня',
        'internal_roller_blinds' => 'Внутрішні ролети',
        // 'another_appeal' deliberately excluded — that old value meant
        // "repeat contact", not a service. application_type covers it now.
    ];

    /**
     * Old `source` enum -> new free-string source. call/office-visit are
     * the same 2 of our 3 manual options (referral has no old equivalent
     * here); everything else is a real historical marketing/telephony
     * channel, kept as-is for analytics even though today's "Додати
     * заявку" form doesn't offer them as choices yet.
     *
     * @var array<string, string>
     */
    private const SOURCE_MAP = [
        'call' => 'call',
        'office-visit' => 'office_visit',
        'binotel_chat' => 'binotel_chat',
        'site' => 'site',
        'fb_lid' => 'fb_lead_ads',
        'get_call_binotel' => 'binotel_call',
        'fb_chat' => 'fb_chat',
    ];

    /**
     * Old `status` enum -> new (stage, status, lost_reason). The old enum
     * mixed real pipeline stages with disqualification reasons — this is
     * a deliberate, lossy simplification; the raw value is preserved in
     * `legacy_status` so nothing is silently lost.
     *
     * @var array<string, array{0: string, 1: string, 2: ?string}>
     */
    private const STATUS_MAP = [
        'new' => ['new', 'open', null],
        'processing' => ['contacted', 'open', null],
        'zamir' => ['measurement_scheduled', 'open', null],
        'vizyt_ofis' => ['contacted', 'open', null],
        'for_later' => ['contacted', 'open', null],
        'accepted' => ['closing', 'won', null],
        'canceled' => ['new', 'lost', 'Скасовано (стара система)'],
        'not_targeted' => ['new', 'lost', 'Нецільовий лід (стара система)'],
        'another_city' => ['new', 'lost', 'Інше місто обслуговування (стара система)'],
        // NOT a lost reason — "рекламація" is a TYPE OF REQUEST (complaint
        // about an already-installed window), same dimension as "Вікна"/
        // "Балкони". Corrected after user feedback: this lead is active
        // work being handled, not a failed sale — see afterUpsert(), which
        // additionally tags it with the "Рекламація" service type.
        'reklamatsiya_amtech' => ['contacted', 'open', null],
        'propushcheno' => ['new', 'lost', 'Пропущено (стара система)'],
    ];

    public function key(): string
    {
        return 'leads';
    }

    public function label(): string
    {
        return 'Ліди (заявки)';
    }

    public function oldTable(): string
    {
        return 'leads';
    }

    public function newTable(): string
    {
        return 'leads';
    }

    public function fieldMap(): array
    {
        return [
            ['old' => 'id', 'new' => 'legacy_id', 'note' => 'технічне поле, для повторної синхронізації без дублів'],
            ['old' => 'client_id', 'new' => 'client_id', 'note' => 'переводиться на новий ID через clients.legacy_id; якщо клієнт не знайдений — рядок пропускається'],
            ['old' => 'who_added_user_id', 'new' => 'manager_id', 'note' => 'переводиться через users.legacy_id; якщо не знайдено — залишається пустим'],
            ['old' => 'source', 'new' => 'source', 'note' => 'call/office-visit копіюються як є (нормалізовано), решта — реальні маркетингові/телефонні канали, зберігаються для аналітики'],
            ['old' => 'order_type', 'new' => 'serviceTypes (pivot)', 'note' => 'мапиться на довідник lead_service_types; "another_appeal" пропускається (це не послуга)'],
            ['old' => 'status', 'new' => 'stage / status / lost_reason', 'note' => 'стара воронка розділяється на 3 нових поля — див. STATUS_MAP'],
            ['old' => 'status (raw)', 'new' => 'legacy_status', 'note' => 'оригінальне значення зберігається без змін для трасування'],
            ['old' => 'comment + comment_call + comment_callback + comment_sales_head', 'new' => 'comment', 'note' => "об'єднується в один коментар з підписами, які старі поля непусті"],
            ['old' => 'address', 'new' => 'site_street', 'note' => 'стара система зберігала повну адресу одним рядком — переноситься в site_street як є; менеджер може розбити на окремі поля вручну'],
            ['old' => 'caller_type', 'new' => '—', 'note' => "рядки з caller_type у ('spam','other','supplier') пропускаються — це не справжні ліди"],
            ['old' => 'profile, advance_calculation1-3', 'new' => '—', 'note' => 'НЕ переноситься — належить майбутньому модулю "Замовлення", дані лишаються в старій БД'],
            ['old' => 'utm_*, device, ip_address, gdid, gbraid, wbraid, site_source, form_*, referrer, referral_1-4', 'new' => 'lead_marketing_data (1:1)', 'note' => 'окрема таблиця, gdid перейменовано на gclid'],
        ];
    }

    /**
     * Preserve the old system's primary key as the new row's id — lead IDs
     * appear in order relations and URLs, so keeping the same numeric id
     * avoids broken references after migration. Same pattern as
     * OrdersSyncMapper::persistRow().
     */
    protected function persistRow(array $newData, array $oldRow, bool $existed): ?int
    {
        $explicitId = (int) $oldRow[$this->oldPrimaryKey];

        if ($existed) {
            DB::table($this->newTable())
                ->where('legacy_id', $newData['legacy_id'])
                ->update($newData);
        } else {
            DB::table($this->newTable())
                ->insert(array_merge(['id' => $explicitId], $newData));
        }

        return $explicitId;
    }

    protected function transformRow(array $oldRow): array
    {
        $callerType = $oldRow['caller_type'] ?? null;

        if (in_array($callerType, ['spam', 'other', 'supplier'], true)) {
            throw new \RuntimeException("Lead #{$oldRow['id']}: caller_type={$callerType}, not a real lead — skipping.");
        }

        $clientId = DB::table('clients')->where('legacy_id', $oldRow['client_id'])->value('id');

        if (! $clientId) {
            throw new \RuntimeException("Lead #{$oldRow['id']}: no matching client (legacy_id={$oldRow['client_id']}) — skipping.");
        }

        $managerId = null;

        if (! empty($oldRow['who_added_user_id'])) {
            $managerId = DB::table('users')->where('legacy_id', $oldRow['who_added_user_id'])->value('id');
        }

        $hasPriorLead = Lead::where('client_id', $clientId)->exists();

        $oldStatus = $oldRow['status'] ?? null;
        [$stage, $status, $lostReason] = self::STATUS_MAP[$oldStatus] ?? ['new', 'open', null];

        $comment = trim(implode("\n", array_filter([
            ! empty($oldRow['comment']) ? $oldRow['comment'] : null,
            ! empty($oldRow['comment_call']) ? 'Коментар дзвінка: '.$oldRow['comment_call'] : null,
            ! empty($oldRow['comment_callback']) ? 'Коментар зворотного дзвінка: '.$oldRow['comment_callback'] : null,
            ! empty($oldRow['comment_sales_head']) ? "Коментар керівника відділу продажу: ".$oldRow['comment_sales_head'] : null,
        ])));

        return [
            'client_id' => $clientId,
            'manager_id' => $managerId,
            'source' => self::SOURCE_MAP[$oldRow['source'] ?? ''] ?? 'unknown',
            'application_type' => $hasPriorLead ? 'repeat' : 'new',
            'stage' => $stage,
            'status' => $status,
            'lost_reason' => $lostReason,
            'legacy_status' => $oldStatus,
            'comment' => $comment !== '' ? $comment : null,
            // Old system stored the full site address as one string — we
            // write it into site_street as-is. The manager can split it
            // into city/house/apartment/floor manually if needed.
            'site_street' => ! empty($oldRow['address']) ? $oldRow['address'] : null,
            'created_at' => $oldRow['created_at'] ?? now(),
            'updated_at' => now(),
        ];
    }

    protected function afterUpsert(array $oldRow, int $newId): void
    {
        $serviceTypeNames = array_filter([
            self::SERVICE_TYPE_MAP[$oldRow['order_type'] ?? ''] ?? null,
            // The old `status` (not `order_type`!) value 'reklamatsiya_amtech'
            // is how complaints about already-installed windows were
            // flagged in the old system — re-tagged here as a service
            // type instead of a lost reason, see STATUS_MAP comment above.
            ($oldRow['status'] ?? null) === 'reklamatsiya_amtech' ? 'Рекламація' : null,
        ]);

        if ($serviceTypeNames !== []) {
            $serviceTypeIds = collect($serviceTypeNames)
                ->map(fn (string $name) => LeadServiceType::firstOrCreate(['name' => $name])->id)
                ->all();

            // syncWithoutDetaching, not sync(): a re-run of this sync must
            // never remove service types a manager added by hand later.
            Lead::find($newId)?->serviceTypes()->syncWithoutDetaching($serviceTypeIds);
        }

        DB::table('lead_marketing_data')->upsert([[
            'lead_id' => $newId,
            'utm_url' => $oldRow['utm_url'] ?? null,
            'utm_source' => $oldRow['utm_source'] ?? null,
            'utm_campaign' => $oldRow['utm_campaign'] ?? null,
            'utm_medium' => $oldRow['utm_medium'] ?? null,
            'utm_term' => $oldRow['utm_term'] ?? null,
            'utm_content' => $oldRow['utm_content'] ?? null,
            'utm_group' => $oldRow['utm_group'] ?? null,
            'utm_asset_group' => $oldRow['utm_asset_group'] ?? null,
            'site_source' => $oldRow['site_source'] ?? null,
            'form_name' => $oldRow['form_name'] ?? null,
            'form_position' => $oldRow['form_position'] ?? null,
            'device' => $oldRow['device'] ?? null,
            'ip_address' => $oldRow['ip_address'] ?? null,
            'user_fingerprint' => $oldRow['user_fingerprint'] ?? null,
            'client_cookie_id' => $oldRow['client_cookie_id'] ?? null,
            'gclid' => $oldRow['gdid'] ?? null,
            'gbraid' => $oldRow['gbraid'] ?? null,
            'wbraid' => $oldRow['wbraid'] ?? null,
            'referrer' => $oldRow['referrer'] ?? null,
            'referral_1' => $oldRow['referral_1'] ?? null,
            'referral_2' => $oldRow['referral_2'] ?? null,
            'referral_3' => $oldRow['referral_3'] ?? null,
            'referral_4' => $oldRow['referral_4'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['lead_id'], [
            'utm_url', 'utm_source', 'utm_campaign', 'utm_medium', 'utm_term', 'utm_content',
            'utm_group', 'utm_asset_group', 'site_source', 'form_name', 'form_position',
            'device', 'ip_address', 'user_fingerprint', 'client_cookie_id',
            'gclid', 'gbraid', 'wbraid', 'referrer', 'referral_1', 'referral_2', 'referral_3', 'referral_4',
            'updated_at',
        ]);
    }
}

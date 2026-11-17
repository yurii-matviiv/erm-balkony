<?php

namespace App\Services\Conversions;

use App\Models\Lead;
use App\Models\Order;

/**
 * Pure decision logic: should this lead / order become a conversion, and with
 * which action, value and click id. Reads only the NEW database. Used both when
 * queueing and again right before sending (the buffer re-check).
 *
 * Plan shape: ['send' => bool, 'reason' => ?string, 'data' => array]
 * `reason` is set when send=false.
 */
class ConversionPlanner
{
    public function __construct(private UserDataBuilder $userData) {}

    /**
     * @return array{send: bool, reason: ?string, data: array<string, mixed>}
     */
    public function planQualified(Lead $lead): array
    {
        $state = $this->leadState($lead);
        $base = ['lead_state' => $state];

        // Рішення «кваліфікований чи ні» ЗАВЖДИ за новими полями stage/status — і для лідів зі старої CRM,
        // і для створених у новій ERM. Старі статуси sync:legacy вже переводить у ці поля
        // (LeadsSyncMapper::STATUS_MAP: new/не-цільові/скасовані/інше місто/пропущено → етап «Новий» або «Втрачено»),
        // тому для лідів зі старої бази результат не змінюється (звірено 2026-11-17 на всіх 15 343 лідах).
        // Це дозволяє працювати і зі старою CRM, і з новою без перемикача.
        if ($lead->status === 'lost') {
            return $this->skip('lost', $base);
        }

        if (in_array($lead->stage, Lead::PRE_QUALIFIED_STAGES, true)) {
            return $this->skip('status_new', $base);
        }

        // Рекламація — це звернення щодо вже встановлених вікон, а не новий продаж: конверсію в рекламу не шлемо.
        // Позначається типом послуги «Рекламація» (LeadsSyncMapper ставить його для старого статусу reklamatsiya_amtech).
        // LEGACY-CLEANUP: другу умову (legacy_status) можна прибрати, коли тип послуги проставлено всім лідам.
        if ($lead->serviceTypes->contains('name', config('conversions.complaint_service_type', 'Рекламація'))
            || $lead->legacy_status === 'reklamatsiya_amtech') {
            return $this->skip('complaint', $base);
        }

        $identity = $this->identity($lead);

        if ($identity === null) {
            return $this->skip('no_identifier', $base);
        }

        if ($this->outsideLookback($lead)) {
            return $this->skip('older_than_lookback', $base);
        }

        $action = config('conversions.qualified_actions.'.($lead->source ?? ''));

        if (! $action) {
            return $this->skip('source_not_mapped', $base);
        }

        return [
            'send' => true,
            'reason' => null,
            'data' => $base + [
                'click_id_type' => $identity['click_id_type'],
                'click_id' => $identity['click_id'],
                'conversion_action_id' => (string) $action,
                'value' => $this->expectedValue($lead),
                'currency' => config('conversions.currency', 'UAH'),
            ],
        ];
    }

    /**
     * @return array{send: bool, reason: ?string, data: array<string, mixed>}
     */
    public function planSale(Order $order, Lead $lead): array
    {
        $base = ['lead_state' => $this->leadState($lead)];
        $action = config('conversions.sale_action_id');

        if (! $action) {
            return $this->skip('sale_action_not_configured', $base);
        }

        if ($order->status === 'cancelled') {
            return $this->skip('order_cancelled', $base);
        }

        $total = (float) $order->total_price;

        if ($total <= 0) {
            return $this->skip('no_order_total', $base);
        }

        $identity = $this->identity($lead);

        if ($identity === null) {
            return $this->skip('no_identifier', $base);
        }

        if ($this->outsideLookback($lead)) {
            return $this->skip('older_than_lookback', $base);
        }

        return [
            'send' => true,
            'reason' => null,
            'data' => $base + [
                'click_id_type' => $identity['click_id_type'],
                'click_id' => $identity['click_id'],
                'conversion_action_id' => (string) $action,
                'value' => round($total, 2),
                'currency' => config('conversions.currency', 'UAH'),
            ],
        ];
    }

    /**
     * What Google can match the conversion on: a gclid (best) and/or hashed phone/email
     * (enhanced conversions for leads). gbraid/wbraid are NOT sent (ONE_PER_CLICK actions
     * reject them) — such a lead goes with user data only. Null = nothing to match on.
     *
     * @return array{click_id_type: ?string, click_id: ?string}|null
     */
    public function identity(Lead $lead): ?array
    {
        $click = $this->clickId($lead);
        $gclid = $click !== null && $click['type'] === 'gclid' ? $click['id'] : null;

        if ($gclid === null && $this->userData->forLead($lead) === []) {
            return null;
        }

        return ['click_id_type' => $gclid !== null ? 'gclid' : null, 'click_id' => $gclid];
    }

    /**
     * Мітка стану ліда для outbox (лише для відображення й перезапуску буфера при зміні стану).
     * LEGACY-CLEANUP: після повного переходу на нову ERM прибрати `legacy_status ??` — лишити `stage/status`.
     */
    public function leadState(Lead $lead): string
    {
        return $lead->legacy_status ?? ($lead->stage.'/'.$lead->status);
    }

    /**
     * @return array{type: string, id: string}|null
     */
    public function clickId(Lead $lead): ?array
    {
        $marketing = $lead->marketingData;

        if (! $marketing) {
            return null;
        }

        foreach (['gclid', 'gbraid', 'wbraid'] as $type) {
            $value = trim((string) $marketing->{$type});

            if ($value !== '') {
                return ['type' => $type, 'id' => $value];
            }
        }

        return null;
    }

    public function expectedValue(Lead $lead): float
    {
        $weights = config('conversions.weights', []);
        $best = 0.0;

        foreach ($lead->serviceTypes as $type) {
            $best = max($best, (float) ($weights[$type->name] ?? 0));
        }

        return $best > 0 ? $best : (float) config('conversions.default_value', 5000);
    }

    private function outsideLookback(Lead $lead): bool
    {
        return $lead->created_at !== null
            && $lead->created_at->lt(now()->subDays((int) config('conversions.lookback_days', 90)));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{send: bool, reason: string, data: array<string, mixed>}
     */
    private function skip(string $reason, array $data = []): array
    {
        return ['send' => false, 'reason' => $reason, 'data' => $data];
    }
}

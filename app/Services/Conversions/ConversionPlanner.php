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
    /**
     * @return array{send: bool, reason: ?string, data: array<string, mixed>}
     */
    public function planQualified(Lead $lead): array
    {
        $state = $this->leadState($lead);
        $base = ['lead_state' => $state];

        if ($lead->legacy_status !== null) {
            if ($lead->legacy_status === 'new') {
                return $this->skip('status_new', $base);
            }

            if (in_array($lead->legacy_status, config('conversions.junk_statuses', []), true)) {
                return $this->skip('junk_status', $base);
            }
        } else {
            if ($lead->status === 'lost') {
                return $this->skip('lost', $base);
            }

            if ($lead->stage === 'new') {
                return $this->skip('status_new', $base);
            }
        }

        $click = $this->clickId($lead);

        if ($click === null) {
            return $this->skip('no_click_id', $base);
        }

        if ($click['type'] !== 'gclid') {
            // The existing actions are ONE_PER_CLICK, which Google does not accept with gbraid/wbraid.
            return $this->skip('braid_not_supported', $base + ['click_id_type' => $click['type'], 'click_id' => $click['id']]);
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
                'click_id_type' => $click['type'],
                'click_id' => $click['id'],
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

        $click = $this->clickId($lead);

        if ($click === null) {
            return $this->skip('no_click_id', $base);
        }

        if ($click['type'] !== 'gclid') {
            return $this->skip('braid_not_supported', $base + ['click_id_type' => $click['type'], 'click_id' => $click['id']]);
        }

        if ($this->outsideLookback($lead)) {
            return $this->skip('older_than_lookback', $base);
        }

        return [
            'send' => true,
            'reason' => null,
            'data' => $base + [
                'click_id_type' => $click['type'],
                'click_id' => $click['id'],
                'conversion_action_id' => (string) $action,
                'value' => round($total, 2),
                'currency' => config('conversions.currency', 'UAH'),
            ],
        ];
    }

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

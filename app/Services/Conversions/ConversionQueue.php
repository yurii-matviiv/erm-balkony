<?php

namespace App\Services\Conversions;

use App\Models\ConversionOutbox;
use App\Models\Lead;
use App\Models\Order;

/**
 * Keeps conversion_outbox in line with the current state of leads/orders.
 * Idempotent: safe to run every minute. Writes only to the NEW database.
 */
class ConversionQueue
{
    public function __construct(private ConversionPlanner $planner) {}

    /**
     * Leads that could ever matter: have a click id and are inside the lookback window.
     */
    private function candidateLeads()
    {
        return Lead::query()
            ->where('created_at', '>=', now()->subDays((int) config('conversions.lookback_days', 90)))
            ->whereHas('marketingData', function ($q) {
                $q->where(function ($w) {
                    $w->where('gclid', '!=', '')->orWhere('gbraid', '!=', '')->orWhere('wbraid', '!=', '');
                });
            })
            ->with(['marketingData', 'serviceTypes']);
    }

    /**
     * One-off: mark everything that qualifies RIGHT NOW as skipped('baseline'),
     * so going live does not re-send what the old pipeline already sent.
     *
     * @return array{qualified: int, sale: int}
     */
    public function baseline(): array
    {
        $count = ['qualified' => 0, 'sale' => 0];

        $this->candidateLeads()->chunkById(200, function ($leads) use (&$count) {
            foreach ($leads as $lead) {
                $plan = $this->planner->planQualified($lead);

                if ($plan['send']) {
                    $this->upsertRow($lead, ConversionOutbox::KIND_QUALIFIED, null, [
                        'status' => ConversionOutbox::STATUS_SKIPPED,
                        'skip_reason' => 'baseline',
                    ] + $plan['data']);
                    $count['qualified']++;
                }

                foreach (Order::where('lead_id', $lead->id)->get() as $order) {
                    $salePlan = $this->planner->planSale($order, $lead);

                    if ($salePlan['send']) {
                        $this->upsertRow($lead, ConversionOutbox::KIND_SALE, $order, [
                            'status' => ConversionOutbox::STATUS_SKIPPED,
                            'skip_reason' => 'baseline',
                        ] + $salePlan['data']);
                        $count['sale']++;
                        break;
                    }
                }
            }
        });

        ConversionSettings::markBaselineDone();

        return $count;
    }

    /**
     * Regular pass: create / update / cancel rows. Returns counters for logging.
     *
     * @return array<string, int>
     */
    public function sync(): array
    {
        $stats = ['created' => 0, 'restarted' => 0, 'cancelled' => 0, 'restated' => 0];

        $this->candidateLeads()->chunkById(200, function ($leads) use (&$stats) {
            foreach ($leads as $lead) {
                $this->syncQualified($lead, $stats);

                $order = Order::where('lead_id', $lead->id)->orderBy('id')->first();

                if ($order) {
                    $this->syncSale($lead, $order, $stats);
                }
            }
        });

        return $stats;
    }

    /**
     * Test helper (validate mode): queue the N most recent already-qualified leads
     * for immediate validation, ignoring baseline and buffer.
     */
    public function enqueueForTest(int $limit): int
    {
        $created = 0;

        $leads = $this->candidateLeads()->orderByDesc('id')->limit($limit * 10)->get();

        foreach ($leads as $lead) {
            if ($created >= $limit) {
                break;
            }

            if (ConversionOutbox::where('lead_id', $lead->id)->where('kind', ConversionOutbox::KIND_QUALIFIED)->exists()) {
                continue;
            }

            $plan = $this->planner->planQualified($lead);

            if (! $plan['send']) {
                continue;
            }

            $this->upsertRow($lead, ConversionOutbox::KIND_QUALIFIED, null, [
                'status' => ConversionOutbox::STATUS_PENDING,
                'send_after' => now(),
                'event_time' => now(),
            ] + $plan['data']);
            $created++;
        }

        return $created;
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function syncQualified(Lead $lead, array &$stats): void
    {
        $plan = $this->planner->planQualified($lead);
        $row = ConversionOutbox::where('lead_id', $lead->id)->where('kind', ConversionOutbox::KIND_QUALIFIED)->first();

        if (! $row) {
            if ($plan['send']) {
                $this->upsertRow($lead, ConversionOutbox::KIND_QUALIFIED, null, $this->pendingFields($plan['data']));
                $stats['created']++;
            } elseif (in_array($plan['reason'], ['braid_not_supported', 'source_not_mapped'], true)) {
                // Visible in the outbox, so it is clear why a click-bearing lead is not sent.
                $this->upsertRow($lead, ConversionOutbox::KIND_QUALIFIED, null, [
                    'status' => ConversionOutbox::STATUS_SKIPPED,
                    'skip_reason' => $plan['reason'],
                ] + $plan['data']);
            }

            return;
        }

        $this->applyPlan($row, $plan, $stats, restateOnSent: false);
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function syncSale(Lead $lead, Order $order, array &$stats): void
    {
        $plan = $this->planner->planSale($order, $lead);
        $row = ConversionOutbox::where('lead_id', $lead->id)->where('kind', ConversionOutbox::KIND_SALE)->first();

        if (! $row) {
            if ($plan['send']) {
                $this->upsertRow($lead, ConversionOutbox::KIND_SALE, $order, $this->pendingFields($plan['data']));
                $stats['created']++;
            }

            return;
        }

        $row->order_id = $order->id;
        $this->applyPlan($row, $plan, $stats, restateOnSent: true);
    }

    /**
     * @param  array{send: bool, reason: ?string, data: array<string, mixed>}  $plan
     * @param  array<string, int>  $stats
     */
    private function applyPlan(ConversionOutbox $row, array $plan, array &$stats, bool $restateOnSent): void
    {
        $open = [ConversionOutbox::STATUS_PENDING, ConversionOutbox::STATUS_VALIDATED];

        if (in_array($row->status, $open, true)) {
            if (! $plan['send']) {
                $row->update([
                    'status' => ConversionOutbox::STATUS_CANCELLED,
                    'skip_reason' => $plan['reason'],
                ]);
                $stats['cancelled']++;

                return;
            }

            // State or value changed while waiting -> buffer restarts from now.
            if ($row->lead_state !== $plan['data']['lead_state'] || (float) $row->value !== (float) $plan['data']['value']) {
                $row->update($this->pendingFields($plan['data']));
                $stats['restarted']++;
            } elseif ($row->isDirty()) {
                $row->save();
            }

            return;
        }

        if ($row->status === ConversionOutbox::STATUS_CANCELLED && $plan['send']) {
            $row->update($this->pendingFields($plan['data']));
            $stats['restarted']++;

            return;
        }

        if ($row->status === ConversionOutbox::STATUS_SKIPPED && $row->skip_reason !== 'baseline' && $plan['send']) {
            $row->update($this->pendingFields($plan['data']));
            $stats['created']++;

            return;
        }

        if ($restateOnSent
            && in_array($row->status, [ConversionOutbox::STATUS_SENT, ConversionOutbox::STATUS_CONFIRMED], true)
            && $plan['send']
            && (float) $row->value !== (float) $plan['data']['value']) {
            // Order total changed after sending: re-send the same transaction id with the new value.
            $row->update($this->pendingFields($plan['data']) + ['confirmed_at' => null, 'google_request_id' => null]);
            $stats['restated']++;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function pendingFields(array $data): array
    {
        return $data + [
            'status' => ConversionOutbox::STATUS_PENDING,
            'skip_reason' => null,
            'attempts' => 0,
            'last_error' => null,
            'event_time' => now(),
            'send_after' => now()->addMinutes(ConversionSettings::bufferMinutes()),
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function upsertRow(Lead $lead, string $kind, ?Order $order, array $fields): ConversionOutbox
    {
        $suffix = $kind === ConversionOutbox::KIND_SALE ? 'sale' : 'q';

        return ConversionOutbox::updateOrCreate(
            ['lead_id' => $lead->id, 'kind' => $kind],
            ['transaction_id' => "lead-{$lead->id}-{$suffix}", 'order_id' => $order?->id] + $fields,
        );
    }
}

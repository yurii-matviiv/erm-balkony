<?php

namespace App\Services\Conversions;

use App\Models\ConversionOutbox;
use App\Models\Lead;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * Sends due outbox rows. Before sending, every row is re-checked against the
 * CURRENT lead/order state (buffer re-validation): if a manager's mistake was
 * reverted, the row is cancelled and nothing reaches Google.
 */
class ConversionDispatcher
{
    public function __construct(
        private ConversionPlanner $planner,
        private DataManagerApiService $api,
    ) {}

    /**
     * @return array<string, int>
     */
    public function dispatchDue(string $mode): array
    {
        $stats = ['sent' => 0, 'validated' => 0, 'cancelled' => 0, 'failed' => 0, 'retry' => 0];

        $account = $this->api->activeAccount();

        if (! $account) {
            Log::warning('Conversions: немає активного підключення Google Ads.');

            return $stats;
        }

        $statuses = $mode === 'live'
            ? [ConversionOutbox::STATUS_PENDING, ConversionOutbox::STATUS_VALIDATED]
            : [ConversionOutbox::STATUS_PENDING];

        $due = ConversionOutbox::query()
            ->whereIn('status', $statuses)
            ->where('send_after', '<=', now())
            ->orderBy('send_after')
            ->limit((int) config('conversions.batch_size', 200))
            ->get();

        $ready = [];

        foreach ($due as $row) {
            if (! $this->stillValid($row)) {
                $row->update(['status' => ConversionOutbox::STATUS_CANCELLED, 'skip_reason' => 'changed_before_send']);
                $stats['cancelled']++;

                continue;
            }

            $ready[$row->conversion_action_id][] = $row;
        }

        foreach ($ready as $actionId => $rows) {
            $this->sendGroup($account, (string) $actionId, $rows, $mode, $stats);
        }

        return $stats;
    }

    /**
     * Poll Google for the processing result of requests sent in live mode.
     *
     * @return array<string, int>
     */
    public function checkStatuses(): array
    {
        $stats = ['confirmed' => 0, 'failed' => 0, 'processing' => 0];
        $account = $this->api->activeAccount();

        if (! $account) {
            return $stats;
        }

        $requestIds = ConversionOutbox::query()
            ->where('status', ConversionOutbox::STATUS_SENT)
            ->whereNotNull('google_request_id')
            ->where('sent_at', '<=', now()->subMinutes(2))
            ->distinct()
            ->limit(50)
            ->pluck('google_request_id');

        foreach ($requestIds as $requestId) {
            try {
                $status = $this->api->requestStatus($account, $requestId);
            } catch (\Throwable $e) {
                Log::warning('Conversions: requestStatus failed', ['request_id' => $requestId, 'error' => $e->getMessage()]);

                continue;
            }

            $state = $status['requestStatus'] ?? 'REQUEST_STATUS_UNKNOWN';
            $rows = ConversionOutbox::where('google_request_id', $requestId)->where('status', ConversionOutbox::STATUS_SENT);

            if ($state === 'SUCCESS') {
                $stats['confirmed'] += $rows->update(['status' => ConversionOutbox::STATUS_CONFIRMED, 'confirmed_at' => now(), 'last_error' => null]);
            } elseif (in_array($state, ['FAILED', 'PARTIAL_SUCCESS'], true)) {
                $errors = json_encode($status['errorInfo']['errorCounts'] ?? [], JSON_UNESCAPED_UNICODE);
                $stats['failed'] += $rows->update([
                    'status' => ConversionOutbox::STATUS_FAILED,
                    'last_error' => "{$state}: {$errors}",
                ]);
            } else {
                $stats['processing']++;
            }
        }

        return $stats;
    }

    private function stillValid(ConversionOutbox $row): bool
    {
        $lead = Lead::with(['marketingData', 'serviceTypes'])->find($row->lead_id);

        if (! $lead) {
            return false;
        }

        if ($row->kind === ConversionOutbox::KIND_SALE) {
            $order = $row->order_id ? Order::find($row->order_id) : null;

            return $order !== null && $this->planner->planSale($order, $lead)['send'];
        }

        return $this->planner->planQualified($lead)['send'];
    }

    /**
     * @param  array<int, ConversionOutbox>  $rows
     * @param  array<string, int>  $stats
     */
    private function sendGroup($account, string $actionId, array $rows, string $mode, array &$stats): void
    {
        $validateOnly = $mode !== 'live';

        try {
            $requestId = $this->api->ingest(
                $account,
                $actionId,
                array_map(fn (ConversionOutbox $r) => $this->api->buildEvent($r->toArray()), $rows),
                $validateOnly,
            );

            foreach ($rows as $row) {
                $this->markOk($row, $requestId, $validateOnly, $stats);
            }

            return;
        } catch (\Throwable $e) {
            if (count($rows) === 1) {
                $this->markError($rows[0], $e->getMessage(), $stats);

                return;
            }
        }

        // Batch rejected: retry one by one so a single bad event does not block the rest.
        foreach ($rows as $row) {
            try {
                $requestId = $this->api->ingest($account, $actionId, [$this->api->buildEvent($row->toArray())], $validateOnly);
                $this->markOk($row, $requestId, $validateOnly, $stats);
            } catch (\Throwable $e) {
                $this->markError($row, $e->getMessage(), $stats);
            }
        }
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function markOk(ConversionOutbox $row, string $requestId, bool $validateOnly, array &$stats): void
    {
        if ($validateOnly) {
            $row->update(['status' => ConversionOutbox::STATUS_VALIDATED, 'validated_at' => now(), 'last_error' => null]);
            $stats['validated']++;

            return;
        }

        $row->update([
            'status' => ConversionOutbox::STATUS_SENT,
            'sent_at' => now(),
            'google_request_id' => $requestId,
            'last_error' => null,
        ]);
        $stats['sent']++;
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function markError(ConversionOutbox $row, string $message, array &$stats): void
    {
        $attempts = $row->attempts + 1;
        $failed = $attempts >= (int) config('conversions.max_attempts', 5);

        $row->update([
            'attempts' => $attempts,
            'last_error' => mb_substr($message, 0, 1000),
            'status' => $failed ? ConversionOutbox::STATUS_FAILED : $row->status,
            'send_after' => $failed ? $row->send_after : now()->addMinutes(10 * $attempts),
        ]);

        $failed ? $stats['failed']++ : $stats['retry']++;
    }
}

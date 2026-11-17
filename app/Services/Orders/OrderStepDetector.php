<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\ProcessStep;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Кроки замовлення, що визначаються ФАКТОМ у даних (docs/STAGES.md, «Кроки замовлення»). Без дій менеджера.
 * Платежі й дати зазвичай потрапляють у нову базу синхронізацією зі старої CRM (запис напряму в БД, без подій моделей),
 * тому кроки ловить ця окрема перевірка (`steps:detect-orders`, кожні 5 хв), а не хук моделі. Ідемпотентна, безпечна для повторів.
 *
 * Кроки (код → правило):
 *  - advance_received : є хоч один отриманий платіж клієнта (income, status=received, не анульований, сума > 0), будь-якої суми
 *  - paid_in_full     : сума отриманих платежів клієнта ≥ суми замовлення (total_price > 0)
 *  - manufactured     : вказано дату готовності (readiness_date): planned, доки дата в майбутньому; done, коли дата настала
 *  - montage_done     : вказано дату монтажу (montage_date): planned, доки дата в майбутньому; done, коли дата настала
 * Етап замовлення цим НЕ змінюється (лише журнал кроків). Скасовані замовлення: кроки-дати не ставляться.
 */
class OrderStepDetector
{
    private const SUBJECT = 'App\\Models\\Order';

    /** @return array<string, int> кількість створених/оновлених записів за кроком */
    public function run(?Carbon $today = null): array
    {
        $today ??= Carbon::now('Europe/Kyiv')->startOfDay();

        return [
            'advance_received' => $this->payments('advance_received'),
            'paid_in_full' => $this->payments('paid_in_full'),
            'manufactured' => $this->dates('manufactured', 'readiness_date', $today),
            'montage_done' => $this->dates('montage_done', 'montage_date', $today),
        ];
    }

    /** Отримані платежі клієнта за замовленням: order_id => [[paid_at, amount], ...] у порядку дати. */
    private function incomeByOrder(): array
    {
        $rows = DB::table('order_payments')
            ->where('direction', 'income')
            ->where('status', 'received')
            ->where('classification_status', '<>', 'annulled')
            ->where('amount', '>', 0)
            ->whereNotNull('order_id')
            ->orderBy('paid_at')->orderBy('id')
            ->get(['order_id', 'paid_at', 'amount']);

        $by = [];

        foreach ($rows as $r) {
            $by[$r->order_id][] = [$r->paid_at, (float) $r->amount];
        }

        return $by;
    }

    private function payments(string $step): int
    {
        $have = $this->haveDone($step);
        $totals = Order::query()->pluck('total_price', 'id');
        $count = 0;

        foreach ($this->incomeByOrder() as $orderId => $payments) {
            if (isset($have[$orderId]) || ! $totals->has($orderId)) {
                continue;
            }

            $date = null;

            if ($step === 'advance_received') {
                $date = $payments[0][0];
            } else {
                $total = (float) $totals[$orderId];

                if ($total <= 0) {
                    continue;
                }

                $sum = 0.0;

                foreach ($payments as [$paidAt, $amount]) {
                    $sum += $amount;

                    if ($sum + 0.005 >= $total) {
                        $date = $paidAt;
                        break;
                    }
                }
            }

            if ($date === null) {
                continue;
            }

            $this->write($orderId, $step, 'done', $date);
            $count++;
        }

        return $count;
    }

    private function dates(string $step, string $column, Carbon $today): int
    {
        $count = 0;

        $orders = DB::table('orders')
            ->whereNotNull($column)
            ->where('status', '<>', 'cancelled')
            ->get(['id', $column.' as d']);

        $existing = ProcessStep::where('subject_type', self::SUBJECT)->where('step', $step)->get()->keyBy('subject_id');

        foreach ($orders as $o) {
            $date = Carbon::parse($o->d, 'Europe/Kyiv')->startOfDay();
            $state = $date->lte($today) ? 'done' : 'planned';
            $row = $existing->get($o->id);

            if ($row && $row->state === 'done') {
                continue; // що відбулось, те не відкликаємо (лише вперед)
            }

            if (! $row || $row->state !== $state || $row->occurred_at?->toDateString() !== $date->toDateString()) {
                $this->write($o->id, $step, $state, $date->toDateString());
                $count++;
            }
        }

        // Дату прибрали, поки крок лише запланований, — позначаємо скасованим.
        $withDate = $orders->pluck('id')->all();

        foreach ($existing as $id => $row) {
            if ($row->state === 'planned' && ! in_array($id, $withDate, true)) {
                $row->update(['state' => 'cancelled']);
                $count++;
            }
        }

        return $count;
    }

    /** @return array<int, true> */
    private function haveDone(string $step): array
    {
        return ProcessStep::where('subject_type', self::SUBJECT)->where('step', $step)->where('state', 'done')
            ->pluck('subject_id')->flip()->map(fn () => true)->all();
    }

    private function write(int $orderId, string $step, string $state, string $date): void
    {
        ProcessStep::updateOrCreate(
            ['subject_type' => self::SUBJECT, 'subject_id' => $orderId, 'step' => $step, 'state' => $state === 'done' ? 'done' : 'planned'],
            ['source' => 'auto', 'occurred_at' => Carbon::parse($date, 'Europe/Kyiv')->startOfDay(), 'comment' => null],
        );

        // planned -> done: старий запис planned прибираємо, щоб крок не дублювався.
        if ($state === 'done') {
            ProcessStep::where('subject_type', self::SUBJECT)->where('subject_id', $orderId)->where('step', $step)->where('state', 'planned')->delete();
        }
    }
}

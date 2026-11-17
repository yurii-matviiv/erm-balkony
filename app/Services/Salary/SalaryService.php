<?php

namespace App\Services\Salary;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Розрахунок менеджерської з/п по замовленнях — перенесено зі старої CRM
 * (app/Models/Salary.php), логіка збережена, нижче — що саме і де поліпшено.
 * Таблицю малює Filament (SalaryModule::table()); тут лише чисті розрахунки.
 *
 * Як рахується:
 *  1. «Якість продажу» (%) = (вартість − витрати) / вартість × 100 − 7, де витрати =
 *     замір + з/п монтажника + з/п за доп. роботи + СПЛАЧЕНІ постачальникам.
 *  2. Відсоток з/п: формула 'percentage' — вибраний на замовленні (за замовчуванням 7);
 *     формула 'multiplier' — сходинки від якості продажу (multiplierPercent()).
 *  3. Нараховано = вартість × відсоток / 100, але лише коли є аванс від клієнта
 *     (у старій CRM для цього був прапорець orders.prepaid — він давно не
 *     ведеться; тут «аванс є» = від клієнта отримано хоч якусь оплату).
 *  4. Сплачено = сума платежів category = 'salary', status = 'received'.
 *
 * Поліпшення проти старої: сходинки множника порівнюються через ≥ (у старій
 * для 3–9 стояло точне «==», тож якість 9,5 давала 0%); без запиту на кожне
 * замовлення (усе одним агрегатом).
 */
class SalaryService
{
    /** Поправка до «якості продажу» (старе «- 7»). */
    public const MARGIN_ADJUSTMENT = 7;

    /** Відсоток, якщо на замовленні не вибрано (як у старій). */
    public const DEFAULT_PERCENT = 7.0;

    /** Етапи, на яких з/п можна виплачувати (старі статуси 7 «Монтаж» і 8 «Змонтовано»). */
    public const PAYABLE_STAGES = ['montage', 'success'];

    public static function multiplierPercent(float $quality): float
    {
        return match (true) {
            $quality >= 15 => 7.0,
            $quality >= 13 => 6.0,
            $quality >= 10 => 5.0,
            $quality >= 9 => 4.0,
            $quality >= 8 => 3.0,
            $quality >= 7 => 2.5,
            $quality >= 6 => 2.0,
            $quality >= 5 => 1.5,
            $quality >= 4 => 1.0,
            $quality >= 3 => 0.5,
            default => 0.0,
        };
    }

    /** @return array<string, string> */
    public static function formulaOptions(): array
    {
        return [
            'percentage' => 'Відсоток (фіксований)',
            'multiplier' => 'Множник (від якості продажу)',
        ];
    }

    /** @return array<string, string> значення => підпис */
    public static function percentageOptions(): array
    {
        $options = [];

        foreach ([2, 3, 3.5, 4, 4.5, 5, 5.5, 6, 6.5, 7] as $value) {
            $options[(string) $value] = rtrim(rtrim(number_format($value, 1, ',', ''), '0'), ',').'%';
        }

        return $options;
    }

    /**
     * Підзапити з платіжними сумами для запиту замовлень (поля supplier_costs, client_paid, salary_paid).
     * Враховуються лише отримані (status = received) платежі.
     */
    public static function withPaymentSums(Builder $query): Builder
    {
        $sum = fn (string $condition): \Illuminate\Database\Query\Builder => DB::table('order_payments')
            ->selectRaw('COALESCE(SUM(amount), 0)')
            ->whereColumn('order_payments.order_id', 'orders.id')
            ->where('order_payments.status', 'received')
            ->whereRaw($condition);

        return $query
            ->select('orders.*')
            ->selectSub($sum("payer_type = 'supplier' AND direction = 'outgo'"), 'supplier_costs')
            ->selectSub($sum("payer_type = 'client' AND direction = 'income'"), 'client_paid')
            ->selectSub($sum("category = 'salary'"), 'salary_paid');
    }

    /**
     * Розрахунок по одному замовленню.
     *
     * @param  array<string, mixed>  $a  атрибути замовлення + supplier_costs, client_paid, salary_paid
     * @return array<string, mixed>
     */
    public function calculate(array $a): array
    {
        $total = (float) ($a['total_price'] ?? 0);
        $costs = (float) ($a['measuring_price'] ?? 0)
            + (float) ($a['montage_salary'] ?? 0)
            + (float) ($a['additional_salary'] ?? 0)
            + (float) ($a['supplier_costs'] ?? 0);

        $quality = $total != 0.0 ? (($total - $costs) / $total * 100) - self::MARGIN_ADJUSTMENT : 0.0;

        $formula = ($a['formula_salary'] ?? null) === 'multiplier' ? 'multiplier' : 'percentage';
        $percent = $formula === 'multiplier'
            ? self::multiplierPercent($quality)
            : ((float) ($a['percentage_value'] ?? 0) > 0 ? (float) $a['percentage_value'] : self::DEFAULT_PERCENT);

        $hasAdvance = (float) ($a['client_paid'] ?? 0) > 0;
        $accrued = $hasAdvance ? round($total * $percent / 100, 2) : 0.0;
        $paid = round((float) ($a['salary_paid'] ?? 0), 2);

        return [
            'total' => $total,
            'formula' => $formula,
            'percent' => $percent,
            'quality' => $quality,
            'has_advance' => $hasAdvance,
            'accrued' => $accrued,
            'paid' => $paid,
            'is_paid' => $accrued > 0 && $paid + 0.01 >= $accrued,
            'can_pay' => $accrued > 0
                && in_array($a['stage'] ?? null, self::PAYABLE_STAGES, true)
                && $paid + 0.01 < $accrued,
            'balance' => (float) ($a['balance'] ?? 0),
        ];
    }

    /**
     * Підсумки для набору замовлень (рядки підсумків таблиці).
     *
     * @param  iterable<int>  $ids
     * @return array{count: int, total: float, avg_percent: float, accrued: float, paid: float, balance: float}
     */
    public function totals(iterable $ids): array
    {
        $rows = self::withPaymentSums(Order::query())
            ->whereIn('orders.id', collect($ids)->all())
            ->get()
            ->map(fn (Order $o): array => $this->calculate($o->getAttributes()));

        return [
            'count' => $rows->count(),
            'total' => (float) $rows->sum('total'),
            'avg_percent' => $rows->isEmpty() ? 0.0 : (float) $rows->avg('percent'),
            'accrued' => (float) $rows->sum('accrued'),
            'paid' => (float) $rows->sum('paid'),
            'balance' => (float) $rows->sum('balance'),
        ];
    }
}

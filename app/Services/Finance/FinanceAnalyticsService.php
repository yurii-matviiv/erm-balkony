<?php

namespace App\Services\Finance;

use App\Models\Expense;
use App\Models\OrderPayment;
use App\Models\PrivatbankAccount;
use App\Services\Privatbank\PrivatbankApiService;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates data for the "Аналітика рахунків" page.
 *
 * Two data sources:
 *   A) Our CRM — order_payments (tied to orders) + expenses (general)
 *   B) PrivatBank API — raw bank statement for the same period
 *
 * All public methods accept a neutral date range from FinanceDateRange::parseDateRange().
 */
class FinanceAnalyticsService
{
    public function __construct(
        private readonly PrivatbankApiService $privatbank,
    ) {}

    // ══════════════════════════════════════════════
    // KPI INDICATORS
    // ══════════════════════════════════════════════

    /**
     * Three top-level numbers: total income, total expenses, net result.
     * Combines order_payments + expenses, status='received' only.
     * Excludes 'between_accounts' category (internal transfers).
     *
     * @param  array{from: string, to: string}  $range  Y-m-d
     * @return array{income: float, expenses: float, result: float}
     */
    public function getKpiTotals(array $range): array
    {
        $crm = FinanceDateRange::formatForCrm($range);

        // Income from client order payments
        $orderIncome = OrderPayment::query()
            ->where('direction', 'income')
            ->where('status', 'received')
            ->whereBetween('paid_at', [$crm['from'], $crm['to']])
            ->sum('amount');

        // Income from general entries (e.g. mosquito-net cash-in not tied to an order)
        $generalIncome = Expense::query()
            ->where('direction', 'income')
            ->where('status', 'received')
            ->whereBetween('paid_at', [$crm['from'], $crm['to']])
            ->sum('amount');

        $income = (float) $orderIncome + (float) $generalIncome;

        // Expenses: order-tied outgoing payments (suppliers, installers, gaugers, office/expense).
        // Exclude 'between_accounts' — those are internal transfers between our own accounts,
        // not real expenses. They would inflate the KPI total vs the analytics table.
        $orderExpenses = OrderPayment::query()
            ->where('direction', 'outgo')
            ->where('status', 'received')
            ->where(fn ($q) => $q->whereNull('category')->orWhere('category', '!=', 'between_accounts'))
            ->whereBetween('paid_at', [$crm['from'], $crm['to']])
            ->sum('amount');

        // Expenses: general (office, phone, marketing, tax, salary)
        $generalExpenses = Expense::query()
            ->where('direction', 'outgo')
            ->where('status', 'received')
            ->whereBetween('paid_at', [$crm['from'], $crm['to']])
            ->sum('amount');

        $expenses = (float) $orderExpenses + (float) $generalExpenses;

        return [
            'income'   => $income,
            'expenses' => $expenses,
            'result'   => $income - $expenses,
        ];
    }

    // ══════════════════════════════════════════════
    // INCOME TABLE
    // ══════════════════════════════════════════════

    /**
     * Income breakdown: cash vs cashless, CRM vs PrivatBank.
     *
     * Returns:
     *   crm_cash        — sum of cash income in CRM
     *   crm_cashless    — sum of cashless income in CRM
     *   crm_total       — crm_cash + crm_cashless
     *   pb_credit       — total credited to the bank account (PrivatBank)
     *   pb_diff         — pb_credit - crm_cashless (positive = unregistered bank income)
     *   pb_accounts     — per-account breakdown [display_name, credit, debit]
     *   pb_error        — true if API call failed
     *
     * @param  array{from: string, to: string}  $range
     */
    public function getIncomeData(array $range): array
    {
        $crm = FinanceDateRange::formatForCrm($range);
        $pb  = FinanceDateRange::formatForPrivatBank($range);

        // — CRM income —
        $crmCash = (float) OrderPayment::query()
            ->where('direction', 'income')
            ->where('status', 'received')
            ->where('payment_method', 'cash')
            ->whereBetween('paid_at', [$crm['from'], $crm['to']])
            ->sum('amount');

        $crmCashless = (float) OrderPayment::query()
            ->where('direction', 'income')
            ->where('status', 'received')
            ->whereIn('payment_method', ['cashless', 'card'])
            ->whereBetween('paid_at', [$crm['from'], $crm['to']])
            ->sum('amount');

        // — PrivatBank income (credits across all active accounts) —
        //
        // NOTE: the /balance endpoint only returns today's turnoverCredit/Debt,
        // NOT an aggregate for a multi-day range. We must fetch transactions and
        // sum TRANTYPE='C' (credit) / 'D' (debit) ourselves — same approach as
        // ViewPrivatbankAccount::mount() which noted this exact limitation.
        $pbTotal    = 0.0;
        $pbAccounts = [];
        $pbError    = false;

        try {
            $accounts = PrivatbankAccount::where('is_active', true)->get();
            foreach ($accounts as $account) {
                // Fetch up to 500 transactions for the period; for longer ranges
                // this may need pagination — acceptable for now (30-day default
                // is typically well under 500 transactions per account).
                $transactions = $this->privatbank->getTransactions(
                    $account,
                    $pb['startDate'],
                    $pb['endDate'],
                    500,
                );

                $credit = 0.0;
                $debit  = 0.0;
                foreach ($transactions as $tx) {
                    $amount = (float) ($tx['SUM'] ?? 0);
                    if (($tx['TRANTYPE'] ?? '') === 'C') {
                        $credit += $amount;
                    } else {
                        $debit += $amount;
                    }
                }

                $pbTotal += $credit;
                $pbAccounts[] = [
                    'name'   => $account->display_name,
                    'credit' => $credit,
                    'debit'  => $debit,
                ];
            }
        } catch (\Throwable) {
            $pbError = true;
        }

        return [
            'crm_cash'     => $crmCash,
            'crm_cashless' => $crmCashless,
            'crm_total'    => $crmCash + $crmCashless,
            'pb_credit'    => $pbTotal,
            'pb_diff'      => $pbTotal - $crmCashless,
            'pb_accounts'  => $pbAccounts,
            'pb_error'     => $pbError,
        ];
    }

    // ══════════════════════════════════════════════
    // EXPENSES TABLE
    // ══════════════════════════════════════════════

    /**
     * Expenses grouped for the analytics table.
     *
     * Group structure:
     *   production  — order_payments WHERE payer_type IN (supplier, installer, gauger)
     *   office      — expenses WHERE category = 'office'
     *   telephone   — expenses WHERE category = 'telephone'
     *   marketing   — expenses WHERE category = 'marketing'  (with PB cross-check for Google)
     *   tax         — expenses WHERE category = 'tax'
     *   salary      — expenses WHERE category = 'salary'
     *   other       — expenses WHERE category = 'order'
     *
     * Each group:
     *   label       — display name
     *   total       — sum of all items in the group
     *   cash        — sum of cash items
     *   cashless    — sum of cashless items
     *   items       — array of per-sub_category rows (label, cash, cashless, total)
     *   pb_total    — (marketing/google only) PrivatBank spend for comparison
     *   pb_error    — true if API call failed for this group
     *
     * @param  array{from: string, to: string}  $range
     */
    public function getExpensesData(array $range): array
    {
        $crm = FinanceDateRange::formatForCrm($range);

        $groups = [];

        // — Production (order-tied outgoing payments to suppliers only) —
        // Installers and gaugers are labour costs → moved to the Зарплата group.
        $groups['production'] = $this->buildOrderPaymentGroup(
            label: 'Виробництво',
            payerTypes: ['supplier'],
            range: $crm,
        );

        // — General expense groups (expenses table + order_payments with matching category) —
        // Order matches the analytical grouping in the old CRM + UI design.
        // 'collection' is intentionally excluded from the office group and shown separately.
        $groups['office']      = $this->buildExpenseGroup('Офіс',               'office',      $crm, excludeSubCategories: ['collection']);
        $groups['collection']  = $this->buildCollectionGroup($crm);
        $groups['telephone']   = $this->buildExpenseGroup('Телефонія',           'telephone',   $crm);
        $groups['marketing']   = $this->buildExpenseGroup('Маркетинг',           'marketing',   $crm);
        $groups['tax']         = $this->buildExpenseGroup('Податки',             'tax',         $crm);
        $groups['salary']      = $this->buildSalaryGroup($crm);
        $groups['recruitment'] = $this->buildExpenseGroup('Рекрутинг',           'recruitment', $crm);
        $groups['order']       = $this->buildExpenseGroup('Замовлення (інше)',   'order',       $crm);

        $pbRange = FinanceDateRange::formatForPrivatBank($range);

        // — Google Ads PrivatBank cross-check (marketing group only) —
        $groups['marketing'] = $this->attachGoogleAdsPbData($groups['marketing'], $pbRange);

        // — FOP tax payments PrivatBank cross-check (tax group only) —
        $groups['tax'] = $this->attachTaxPbData($groups['tax'], $pbRange);

        // — Uncategorized: order_payments outgo that doesn't fit any group above —
        // Catches payer_type NOT IN production types, with NULL or unknown category.
        // Makes the "KPI total expenses vs table total" gap visible instead of hidden.
        $knownPayerTypes = ['supplier', 'installer', 'gauger', 'office', 'expense'];
        $knownCategories = ['office', 'telephone', 'marketing', 'tax', 'salary', 'recruitment', 'order', 'between_accounts'];

        $uncatCash = (float) OrderPayment::query()
            ->where('direction', 'outgo')->where('status', 'received')
            ->where(fn ($q) => $q
                ->whereNotIn('payer_type', $knownPayerTypes)
                ->orWhere(fn ($q2) => $q2
                    ->whereIn('payer_type', ['office', 'expense'])
                    ->where(fn ($q3) => $q3
                        ->whereNull('category')
                        ->orWhereNotIn('category', $knownCategories)
                    )
                )
            )
            ->whereBetween('paid_at', [$crm['from'], $crm['to']])
            ->where('payment_method', 'cash')
            ->sum('amount');

        $uncatCashless = (float) OrderPayment::query()
            ->where('direction', 'outgo')->where('status', 'received')
            ->where(fn ($q) => $q
                ->whereNotIn('payer_type', $knownPayerTypes)
                ->orWhere(fn ($q2) => $q2
                    ->whereIn('payer_type', ['office', 'expense'])
                    ->where(fn ($q3) => $q3
                        ->whereNull('category')
                        ->orWhereNotIn('category', $knownCategories)
                    )
                )
            )
            ->whereBetween('paid_at', [$crm['from'], $crm['to']])
            ->where('payment_method', '!=', 'cash')
            ->sum('amount');

        $uncatTotal = $uncatCash + $uncatCashless;

        if ($uncatTotal > 0) {
            // Fetch individual uncategorized rows for the detail table
            $uncatRows = OrderPayment::query()
                ->select('paid_at', 'payer_type', 'payer_name', 'payment_method', 'amount', 'comment', 'category')
                ->where('direction', 'outgo')
                ->where('status', 'received')
                ->where(fn ($q) => $q
                    ->whereNotIn('payer_type', $knownPayerTypes)
                    ->orWhere(fn ($q2) => $q2
                        ->whereIn('payer_type', ['office', 'expense'])
                        ->where(fn ($q3) => $q3
                            ->whereNull('category')
                            ->orWhereNotIn('category', $knownCategories)
                        )
                    )
                )
                ->whereBetween('paid_at', [$crm['from'], $crm['to']])
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn ($r) => [
                    'date'    => $r->paid_at,
                    'label'   => $r->payer_name ?? $r->payer_type ?? '—',
                    'type'    => $r->category,
                    'cash'    => $r->payment_method === 'cash' ? (float) $r->amount : 0.0,
                    'cashless'=> $r->payment_method !== 'cash' ? (float) $r->amount : 0.0,
                    'total'   => (float) $r->amount,
                    'comment' => $r->comment,
                ])
                ->values()
                ->toArray();

            $groups['uncategorized'] = [
                'label'    => 'Некатегоризовано',
                'total'    => $uncatTotal,
                'cash'     => $uncatCash,
                'cashless' => $uncatCashless,
                'items'    => [],
                'rows'     => $uncatRows,
            ];
        }

        return $groups;
    }

    // ──────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────

    /** Build a group from order_payments (production costs). */
    private function buildOrderPaymentGroup(
        string $label,
        array $payerTypes,
        array $range,
    ): array {
        $subLabels = [
            'supplier'  => 'Постачальники',
            'installer' => 'Монтажники',
            'gauger'    => 'Замірники',
        ];

        // Aggregation by payer_type (shown as sub-items in the group header)
        $agg = OrderPayment::query()
            ->select(
                'payer_type',
                DB::raw("SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END) as cash"),
                DB::raw("SUM(CASE WHEN payment_method != 'cash' THEN amount ELSE 0 END) as cashless"),
                DB::raw('SUM(amount) as total'),
            )
            ->where('direction', 'outgo')
            ->where('status', 'received')
            ->whereIn('payer_type', $payerTypes)
            ->whereBetween('paid_at', [$range['from'], $range['to']])
            ->groupBy('payer_type')
            ->get();

        $items = $agg->map(fn ($r) => [
            'label'    => $subLabels[$r->payer_type] ?? $r->payer_type,
            'cash'     => (float) $r->cash,
            'cashless' => (float) $r->cashless,
            'total'    => (float) $r->total,
        ])->values()->toArray();

        // Individual rows — every payment, sorted by date desc
        $detailRows = OrderPayment::query()
            ->select('paid_at', 'payer_name', 'payer_type', 'payment_method', 'amount', 'comment')
            ->where('direction', 'outgo')
            ->where('status', 'received')
            ->whereIn('payer_type', $payerTypes)
            ->whereBetween('paid_at', [$range['from'], $range['to']])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($r) => [
                'date'    => $r->paid_at,
                'label'   => $r->payer_name ?? ($subLabels[$r->payer_type] ?? $r->payer_type),
                'type'    => $subLabels[$r->payer_type] ?? null,
                'cash'    => $r->payment_method === 'cash' ? (float) $r->amount : 0.0,
                'cashless'=> $r->payment_method !== 'cash' ? (float) $r->amount : 0.0,
                'total'   => (float) $r->amount,
                'comment' => $r->comment,
            ])
            ->values()
            ->toArray();

        return [
            'label'    => $label,
            'total'    => (float) $agg->sum('total'),
            'cash'     => (float) $agg->sum('cash'),
            'cashless' => (float) $agg->sum('cashless'),
            'items'    => $items,
            'rows'     => $detailRows,
        ];
    }

    /**
     * Build a group from BOTH sources:
     *   1. `expenses` table  — general costs not tied to any order
     *   2. `order_payments`  — costs that were tied to orders in the old CRM
     *                          (payer_type = 'office'/'expense', same category)
     *
     * Returns aggregation (items) + individual detail rows (rows).
     * Note: order_payments has no sub_category column — those historical rows
     * show as '—' in the sub_category breakdown but their comment is visible
     * in the detail table.
     */
    /**
     * Salary group — three sources combined:
     *   1. `expenses` WHERE category = 'salary'   (general salaries not tied to an order)
     *   2. `order_payments` WHERE payer_type IN ('installer', 'gauger')
     *      (per-order labour payments — these are wage costs, not production/material costs)
     *   3. `order_payments` WHERE payer_type = 'expense' AND category = 'salary'
     *      (order-linked manager salary rows — they live ONLY in order_payments
     *      since the duplicate-tables fix, see GeneralExpensesSyncMapper::legacyQuery)
     *
     * Gaugers are included alongside installers: both are workers paid for labour.
     */
    private function buildSalaryGroup(array $crm): array
    {
        $workerLabels = [
            'installer' => 'Монтажники',
            'gauger'    => 'Замірники',
            // Order-linked salary rows (old payer_type=expense +
            // category=salary) live ONLY in order_payments since the
            // duplicate-tables fix (see GeneralExpensesSyncMapper::
            // legacyQuery) — count them here so the Зарплата group stays
            // complete.
            'expense'   => 'ЗП по замовленнях',
        ];

        // Sub-items from expenses table (manager/head-of-sales)
        $expSubLabels = Expense::subCategoryOptions()['salary'] ?? [];
        $merged = [];

        $expAgg = Expense::query()
            ->select(
                'sub_category',
                DB::raw("SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END) as cash"),
                DB::raw("SUM(CASE WHEN payment_method != 'cash' THEN amount ELSE 0 END) as cashless"),
                DB::raw('SUM(amount) as total'),
            )
            ->where('direction', 'outgo')
            ->where('status', 'received')
            ->where('category', 'salary')
            ->whereBetween('paid_at', [$crm['from'], $crm['to']])
            ->groupBy('sub_category')
            ->get();

        foreach ($expAgg as $r) {
            $key = $r->sub_category ?? 'other';
            $merged[$key] = [
                'label'    => $expSubLabels[$key] ?? $key,
                'cash'     => (float) $r->cash,
                'cashless' => (float) $r->cashless,
                'total'    => (float) $r->total,
            ];
        }

        // Sub-items from order_payments (installer / gauger)
        $opAgg = OrderPayment::query()
            ->select(
                'payer_type',
                DB::raw("SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END) as cash"),
                DB::raw("SUM(CASE WHEN payment_method != 'cash' THEN amount ELSE 0 END) as cashless"),
                DB::raw('SUM(amount) as total'),
            )
            ->where('direction', 'outgo')
            ->where('status', 'received')
            ->where(function ($q) {
                $q->whereIn('payer_type', ['installer', 'gauger'])
                    ->orWhere(fn ($q2) => $q2->where('payer_type', 'expense')->where('category', 'salary'));
            })
            ->whereBetween('paid_at', [$crm['from'], $crm['to']])
            ->groupBy('payer_type')
            ->get();

        foreach ($opAgg as $r) {
            $key = $r->payer_type;
            if (isset($merged[$key])) {
                $merged[$key]['cash']     += (float) $r->cash;
                $merged[$key]['cashless'] += (float) $r->cashless;
                $merged[$key]['total']    += (float) $r->total;
            } else {
                $merged[$key] = [
                    'label'    => $workerLabels[$key] ?? $key,
                    'cash'     => (float) $r->cash,
                    'cashless' => (float) $r->cashless,
                    'total'    => (float) $r->total,
                ];
            }
        }

        $items = array_values(array_filter($merged, fn ($i) => $i['total'] > 0));
        usort($items, fn ($a, $b) => $b['total'] <=> $a['total']);

        $grandCash     = array_sum(array_column($items, 'cash'));
        $grandCashless = array_sum(array_column($items, 'cashless'));

        // Detail rows
        $expRows = Expense::query()
            ->select('paid_at', 'sub_category', 'payment_method', 'amount', 'comment')
            ->where('direction', 'outgo')
            ->where('status', 'received')
            ->where('category', 'salary')
            ->whereBetween('paid_at', [$crm['from'], $crm['to']])
            ->orderByDesc('paid_at')->orderByDesc('id')
            ->get()
            ->map(fn ($r) => [
                'date'     => $r->paid_at,
                'label'    => $expSubLabels[$r->sub_category] ?? ($r->sub_category ?? '—'),
                'type'     => null,
                'cash'     => $r->payment_method === 'cash' ? (float) $r->amount : 0.0,
                'cashless' => $r->payment_method !== 'cash' ? (float) $r->amount : 0.0,
                'total'    => (float) $r->amount,
                'comment'  => $r->comment,
            ]);

        $opRows = OrderPayment::query()
            ->select('paid_at', 'payer_name', 'payer_type', 'payment_method', 'amount', 'comment')
            ->where('direction', 'outgo')
            ->where('status', 'received')
            ->where(function ($q) {
                $q->whereIn('payer_type', ['installer', 'gauger'])
                    ->orWhere(fn ($q2) => $q2->where('payer_type', 'expense')->where('category', 'salary'));
            })
            ->whereBetween('paid_at', [$crm['from'], $crm['to']])
            ->orderByDesc('paid_at')->orderByDesc('id')
            ->get()
            ->map(fn ($r) => [
                'date'     => $r->paid_at,
                'label'    => $r->payer_name ?? ($workerLabels[$r->payer_type] ?? $r->payer_type),
                'type'     => $workerLabels[$r->payer_type] ?? null,
                'cash'     => $r->payment_method === 'cash' ? (float) $r->amount : 0.0,
                'cashless' => $r->payment_method !== 'cash' ? (float) $r->amount : 0.0,
                'total'    => (float) $r->amount,
                'comment'  => $r->comment,
            ]);

        $detailRows = collect(array_merge($expRows->toArray(), $opRows->toArray()))
            ->sortByDesc('date')
            ->values()
            ->toArray();

        return [
            'label'    => 'Зарплата',
            'total'    => $grandCash + $grandCashless,
            'cash'     => $grandCash,
            'cashless' => $grandCashless,
            'items'    => $items,
            'rows'     => $detailRows,
        ];
    }

    /**
     * Інкасація — cash collection service (money physically leaving the office
     * cash box, deposited to the bank by a security courier).
     * Stored as category='office', sub_category='collection' in both old and new DB.
     * Shown as a standalone group rather than a sub-item of Офіс because it is a
     * distinct type of cash movement, not a regular office operating cost.
     */
    private function buildCollectionGroup(array $crm): array
    {
        $rows = Expense::query()
            ->select('paid_at', 'payment_method', 'amount', 'comment')
            ->where('direction', 'outgo')
            ->where('status', 'received')
            ->where('sub_category', 'collection')
            ->whereBetween('paid_at', [$crm['from'], $crm['to']])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($r) => [
                'date'     => $r->paid_at,
                'label'    => 'Інкасація',
                'type'     => null,
                'cash'     => $r->payment_method === 'cash' ? (float) $r->amount : 0.0,
                'cashless' => $r->payment_method !== 'cash' ? (float) $r->amount : 0.0,
                'total'    => (float) $r->amount,
                'comment'  => $r->comment,
            ])
            ->values()
            ->toArray();

        $cash     = array_sum(array_column($rows, 'cash'));
        $cashless = array_sum(array_column($rows, 'cashless'));

        return [
            'label'    => 'Інкасація',
            'total'    => $cash + $cashless,
            'cash'     => $cash,
            'cashless' => $cashless,
            'items'    => [],   // no sub-breakdown needed — it's a single type
            'rows'     => $rows,
        ];
    }

    private function buildExpenseGroup(
        string $label,
        string $category,
        array $crm,
        array $excludeSubCategories = [],
    ): array {
        $subLabels = Expense::subCategoryOptions()[$category] ?? [];

        // ── Aggregation (sub_category totals) ──────────────────────────────

        $merged = [];

        $addAggRows = function ($rows, string $subKey = 'sub_category') use (&$merged): void {
            foreach ($rows as $r) {
                $key = $r->$subKey ?? 'other';
                if (! isset($merged[$key])) {
                    $merged[$key] = ['cash' => 0.0, 'cashless' => 0.0, 'total' => 0.0];
                }
                $merged[$key]['cash']     += (float) $r->cash;
                $merged[$key]['cashless'] += (float) $r->cashless;
                $merged[$key]['total']    += (float) $r->total;
            }
        };

        // Source 1 agg
        $addAggRows(
            Expense::query()
                ->select(
                    'sub_category',
                    DB::raw("SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END) as cash"),
                    DB::raw("SUM(CASE WHEN payment_method != 'cash' THEN amount ELSE 0 END) as cashless"),
                    DB::raw('SUM(amount) as total'),
                )
                ->where('direction', 'outgo')
                ->where('status', 'received')
                ->where('category', $category)
                ->when($excludeSubCategories, fn ($q) => $q->whereNotIn('sub_category', $excludeSubCategories))
                ->whereBetween('paid_at', [$crm['from'], $crm['to']])
                ->groupBy('sub_category')
                ->get()
        );

        // Source 2 agg (order_payments has no sub_category column → bucket as 'other')
        $addAggRows(
            OrderPayment::query()
                ->select(
                    DB::raw('NULL as sub_category'),
                    DB::raw("SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END) as cash"),
                    DB::raw("SUM(CASE WHEN payment_method != 'cash' THEN amount ELSE 0 END) as cashless"),
                    DB::raw('SUM(amount) as total'),
                )
                ->where('direction', 'outgo')
                ->where('status', 'received')
                ->whereIn('payer_type', ['office', 'expense'])
                ->where('category', $category)
                ->whereBetween('paid_at', [$crm['from'], $crm['to']])
                ->get()
        );

        $items = [];
        $grandCash = $grandCashless = $grandTotal = 0.0;

        foreach ($merged as $key => $sums) {
            if ($sums['total'] <= 0) {
                continue;
            }
            $items[] = [
                'label'    => $subLabels[$key] ?? ($key === 'other' ? '—' : $key),
                'cash'     => $sums['cash'],
                'cashless' => $sums['cashless'],
                'total'    => $sums['total'],
            ];
            $grandCash     += $sums['cash'];
            $grandCashless += $sums['cashless'];
            $grandTotal    += $sums['total'];
        }

        usort($items, fn ($a, $b) => $b['total'] <=> $a['total']);

        // ── Individual detail rows (every payment, for drill-down) ─────────

        // Source 1 rows: expenses table (has sub_category)
        $expRows = Expense::query()
            ->select('paid_at', 'sub_category', 'payment_method', 'amount', 'comment')
            ->where('direction', 'outgo')
            ->where('status', 'received')
            ->where('category', $category)
            ->when($excludeSubCategories, fn ($q) => $q->whereNotIn('sub_category', $excludeSubCategories))
            ->whereBetween('paid_at', [$crm['from'], $crm['to']])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($r) => [
                'date'    => $r->paid_at,
                'label'   => $subLabels[$r->sub_category] ?? ($r->sub_category ?? '—'),
                'type'    => null,
                'cash'    => $r->payment_method === 'cash' ? (float) $r->amount : 0.0,
                'cashless'=> $r->payment_method !== 'cash' ? (float) $r->amount : 0.0,
                'total'   => (float) $r->amount,
                'comment' => $r->comment,
            ]);

        // Source 2 rows: order_payments (no sub_category, but comment is informative)
        $opRows = OrderPayment::query()
            ->select('paid_at', 'payment_method', 'amount', 'comment')
            ->where('direction', 'outgo')
            ->where('status', 'received')
            ->whereIn('payer_type', ['office', 'expense'])
            ->where('category', $category)
            ->whereBetween('paid_at', [$crm['from'], $crm['to']])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($r) => [
                'date'    => $r->paid_at,
                'label'   => '—',
                'type'    => null,
                'cash'    => $r->payment_method === 'cash' ? (float) $r->amount : 0.0,
                'cashless'=> $r->payment_method !== 'cash' ? (float) $r->amount : 0.0,
                'total'   => (float) $r->amount,
                'comment' => $r->comment,
            ]);

        // Eloquent\Collection::merge() calls getKey() on each item — it expects
        // Eloquent models, but after ->map(fn => [...]) the items are plain PHP arrays.
        // Fix: convert both to plain arrays first, then wrap in a base collect().
        $detailRows = collect(array_merge($expRows->toArray(), $opRows->toArray()))
            ->sortByDesc('date')
            ->values()
            ->toArray();

        return [
            'label'    => $label,
            'total'    => $grandTotal,
            'cash'     => $grandCash,
            'cashless' => $grandCashless,
            'items'    => $items,
            'rows'     => $detailRows,
        ];
    }

    /**
     * Add PrivatBank FOP tax payments cross-check to the tax group.
     *
     * Detects tax payments by scanning debit transactions for Ukrainian
     * tax-related keywords in OSND (payment purpose) or AUT_CNTR_NAM
     * (counterparty name). Returns a per-account breakdown so the user
     * can verify each FOP entity separately.
     *
     * Keywords (case-insensitive, Ukrainian):
     *   OSND:          єдиний податок, єсв, єдиний соціальний внесок, пдфо
     *   Counterparty:  казначейство, дпс, державна податкова
     *
     * This is a CROSS-CHECK, not an additional expense — do not add to total.
     * See docs/INVOICE-ANALYTICS.md §6 for the full rationale.
     *
     * Adds to the group:
     *   pb_tax_total    — total FOP tax outflow found across all accounts
     *   pb_tax_accounts — per-account breakdown [{name, total, transactions}]
     *   pb_tax_error    — true if any API call failed
     */
    private function attachTaxPbData(array $taxGroup, array $pb): array
    {
        // Ukrainian keywords that identify FOP tax payments in PrivatBank statements
        $osndKeywords  = ['єдиний податок', 'єсв', 'єдиний соціальний внесок', 'пдфо'];
        $counterpartyKeywords = ['казначейство', 'дпс', 'державна податкова'];

        $pbTaxTotal    = 0.0;
        $pbTaxAccounts = [];
        $pbTaxError    = false;

        try {
            $accounts = PrivatbankAccount::where('is_active', true)->get();

            foreach ($accounts as $account) {
                $transactions = $this->privatbank->getTransactions(
                    $account,
                    $pb['startDate'],
                    $pb['endDate'],
                    500,
                );

                $accountTotal = 0.0;
                $accountTxs   = [];

                foreach ($transactions as $tx) {
                    if (($tx['TRANTYPE'] ?? '') !== 'D') {
                        continue; // only outgoing payments
                    }

                    $osnd        = mb_strtolower($tx['OSND'] ?? '', 'UTF-8');
                    $counterparty = mb_strtolower($tx['AUT_CNTR_NAM'] ?? '', 'UTF-8');

                    $isTax = false;
                    foreach ($osndKeywords as $kw) {
                        if (str_contains($osnd, $kw)) {
                            $isTax = true;
                            break;
                        }
                    }
                    if (! $isTax) {
                        foreach ($counterpartyKeywords as $kw) {
                            if (str_contains($counterparty, $kw)) {
                                $isTax = true;
                                break;
                            }
                        }
                    }

                    if ($isTax) {
                        $amount        = (float) ($tx['SUM'] ?? 0);
                        $accountTotal += $amount;
                        $accountTxs[]  = [
                            'date'        => $tx['DAT_KL'] ?? null,
                            'amount'      => $amount,
                            'osnd'        => $tx['OSND'] ?? '',
                            'counterparty'=> $tx['AUT_CNTR_NAM'] ?? '',
                        ];
                    }
                }

                if ($accountTotal > 0) {
                    $pbTaxTotal      += $accountTotal;
                    $pbTaxAccounts[]  = [
                        'name'         => $account->display_name,
                        'total'        => $accountTotal,
                        'transactions' => $accountTxs,
                    ];
                }
            }
        } catch (\Throwable) {
            $pbTaxError = true;
        }

        $taxGroup['pb_tax_total']    = $pbTaxTotal;
        $taxGroup['pb_tax_accounts'] = $pbTaxAccounts;
        $taxGroup['pb_tax_error']    = $pbTaxError;

        return $taxGroup;
    }

    /**
     * Add PrivatBank Google Ads spending to the marketing group.
     * Looks for transactions where OSND contains 'Google' or 'GOOGLE'.
     */
    private function attachGoogleAdsPbData(array $marketingGroup, array $pb): array
    {
        $pbGoogleTotal = 0.0;
        $pbError       = false;

        try {
            $accounts = PrivatbankAccount::where('is_active', true)->get();
            foreach ($accounts as $account) {
                $transactions = $this->privatbank->getTransactions(
                    $account,
                    $pb['startDate'],
                    $pb['endDate'],
                    500,
                );

                foreach ($transactions as $tx) {
                    if (
                        ($tx['TRANTYPE'] ?? '') === 'D' // debit = money out
                        && stripos($tx['OSND'] ?? '', 'google') !== false
                    ) {
                        $pbGoogleTotal += (float) ($tx['SUM'] ?? 0);
                    }
                }
            }
        } catch (\Throwable) {
            $pbError = true;
        }

        // Compare PB card charges against the AUTO-charge bucket
        // ('Google Ads (списання)' — imported from the old google_ads_pay
        // bank journal). Manual contractor invoices (sub 'google', e.g.
        // "За рекламу Гугл Шевченко С.В.") are a different kind of spend
        // and deliberately NOT part of this check.
        $googleCrmTotal = collect($marketingGroup['items'])
            ->firstWhere('label', 'Google Ads (списання)')['total'] ?? 0.0;

        $marketingGroup['pb_google']       = $pbGoogleTotal;
        $marketingGroup['pb_google_diff']  = $pbGoogleTotal - $googleCrmTotal;
        $marketingGroup['pb_error']        = $pbError;

        return $marketingGroup;
    }
}

<?php

namespace App\Services\Sync\Mappers;

use App\Services\Sync\AbstractSyncMapper;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Syncs `google_ads_pay` (old CRM) -> `expenses` (new system).
 *
 * The old table is a mini bank-transactions journal the old system kept
 * for ONE case: automatic Google Ads card charges pulled from PrivatBank
 * (comment = raw bank OSND, num_doc = unique bank document ref,
 * pay_account = the FOP's IBAN). Without it the Маркетинг group in the
 * analytics showed a huge CRM-vs-PrivatBank gap — the money existed only
 * on the bank side.
 *
 * Rows land as general expenses with category=marketing,
 * sub_category=google_ads ("Google Ads (списання)") — deliberately a
 * SEPARATE sub-category from manual 'google' rows (contractor invoices
 * like "За рекламу Гугл Шевченко С.В."), per explicit user request to
 * keep auto-charges and contractor payments apart.
 *
 * Shares the `expenses` table with GeneralExpensesSyncMapper, so its
 * legacy_id range is shifted by LEGACY_ID_OFFSET — same pattern as
 * AddCandidateSyncMapper (see AbstractSyncMapper::resolveLegacyId()).
 */
class GoogleAdsPaySyncMapper extends AbstractSyncMapper
{
    public const LEGACY_ID_OFFSET = 2_000_000;

    public function key(): string
    {
        return 'google_ads_pay';
    }

    public function label(): string
    {
        return 'Google Ads (списання з карток)';
    }

    public function oldTable(): string
    {
        return 'google_ads_pay';
    }

    public function newTable(): string
    {
        return 'expenses';
    }

    public function fieldMap(): array
    {
        return [
            ['old' => 'id', 'new' => 'legacy_id (+2 000 000)', 'note' => 'зсув, бо expenses ділиться з мапером загальних витрат — той пише old orders_payments.id'],
            ['old' => 'date', 'new' => 'paid_at', 'note' => 'дата списання'],
            ['old' => 'amount', 'new' => 'amount', 'note' => 'копіюється як є'],
            ['old' => 'pay_account (IBAN)', 'new' => 'fop_account_id', 'note' => 'прямий матч по privatbank_accounts.account_number'],
            ['old' => 'comment', 'new' => 'comment', 'note' => 'сире призначення платежу з банку'],
            ['old' => 'num_doc', 'new' => 'privatbank_num', 'note' => 'унікальний номер банківського документа'],
            ['old' => 'category/sub_category', 'new' => 'marketing / google_ads', 'note' => 'окрема підкатегорія "Google Ads (списання)" — НЕ змішується з ручними платежами підряднику (google)'],
            ['old' => 'user_id', 'new' => 'created_by', 'note' => 'через users.legacy_id (зазвичай бот)'],
        ];
    }

    protected function resolveLegacyId(array $oldRow): int
    {
        return self::LEGACY_ID_OFFSET + (int) $oldRow[$this->oldPrimaryKey];
    }

    protected function syncedQuery(): Builder
    {
        return parent::syncedQuery()->where('legacy_id', '>=', self::LEGACY_ID_OFFSET);
    }

    /**
     * classification_status protected from re-sync overwrites — same
     * pattern as the other two payment mappers.
     */
    protected function persistRow(array $newData, array $oldRow, bool $existed): ?int
    {
        DB::table($this->newTable())->upsert(
            [$newData],
            ['legacy_id'],
            array_values(array_diff(array_keys($newData), ['classification_status'])),
        );

        return DB::table($this->newTable())
            ->where('legacy_id', $newData['legacy_id'])
            ->value('id');
    }

    protected function transformRow(array $oldRow): array
    {
        return [
            'direction' => $oldRow['type'] ?? 'outgo',
            'payment_method' => 'cashless',
            'amount' => (float) ($oldRow['amount'] ?? 0),
            'status' => $oldRow['status'] ?? 'received',
            // Fully structured by nature (bank charge, known category) —
            // born classified, per принцип 2/3 (CLAUDE.md "Платежі").
            'classification_status' => 'classified',
            'category' => 'marketing',
            'sub_category' => 'google_ads',
            'comment' => $oldRow['comment'] ?: null,
            'created_by' => $this->resolveCreatedBy($oldRow),
            'fop_account_id' => $this->resolveFopAccountByIban((string) ($oldRow['pay_account'] ?? '')),
            'privatbank_num' => $oldRow['num_doc'] ?: null,
            'paid_at' => $this->sanitizeDate($oldRow['date'] ?? null),
            'created_at' => $this->sanitizeDate($oldRow['date'] ?? null) ?? now(),
            'updated_at' => now(),
        ];
    }

    /** Direct IBAN match against the FOP directory. */
    private function resolveFopAccountByIban(string $iban): ?int
    {
        if ($iban === '') {
            return null;
        }

        return DB::table('privatbank_accounts')
            ->where('account_number', $iban)
            ->value('id');
    }

    private function resolveCreatedBy(array $oldRow): ?int
    {
        $oldUserId = (int) ($oldRow['user_id'] ?? 0);

        if ($oldUserId <= 0) {
            return null;
        }

        return DB::table('users')->where('legacy_id', $oldUserId)->value('id');
    }

    /** Zero-dates become NULL — same guard as the other payment mappers. */
    private function sanitizeDate(?string $value): ?string
    {
        if (blank($value) || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        $ts = strtotime($value);

        return $ts !== false && $ts > 0 ? date('Y-m-d', $ts) : null;
    }
}

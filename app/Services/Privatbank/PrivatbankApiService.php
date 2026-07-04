<?php

namespace App\Services\Privatbank;

use App\Models\PrivatbankAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wraps the PrivatBank Business API (acp.privatbank.ua) for the
 * integration module.
 *
 * Based on the old system's privat24_api/api_function.php but rewritten
 * using Laravel's Http client (Guzzle) instead of raw curl.
 *
 * API base: https://acp.privatbank.ua/api/statements/
 * Auth: `token` and `User-Agent` headers (per account).
 * Docs format: dd-m-Y for date params (e.g. "24-06-2026").
 *
 * Method overview:
 *   getSettings()    — GET /settings  — server status + today's operational date
 *   getBalance()     — GET /balance   — current balance for the account
 *   getTransactions()— GET /transactions — paginated list of transactions
 */
class PrivatbankApiService
{
    private const BASE_URL = 'https://acp.privatbank.ua/api/statements';

    // ──────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────

    /**
     * GET /api/statements/settings
     *
     * Returns server phase (should be 'WRK') and the current operational
     * date (today). Used to verify the token is valid and the bank API
     * is reachable before making balance/transaction calls.
     *
     * @return array{status: string, settings: array{phase: string, today: string}}|null
     *   null on network error or non-200 response
     */
    public function getSettings(PrivatbankAccount $account): ?array
    {
        try {
            $response = $this->client($account)->get(self::BASE_URL . '/settings');

            if (! $response->successful()) {
                Log::warning('PrivatBank getSettings failed', [
                    'account' => $account->display_name,
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (ConnectionException $e) {
            Log::error('PrivatBank getSettings connection error', [
                'account' => $account->display_name,
                'error'   => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * GET /api/statements/balance  — today's balance
     *
     * Verifies the API is in WRK phase (via /settings), then fetches the
     * balance for today's operational date. Returns the first entry from
     * the `balances` array:
     *   balanceOut    — closing balance
     *   turnoverCredit — total credited today
     *   turnoverDebt   — total debited today
     *
     * @return array|null  null if API is unavailable or token is wrong
     */
    public function getBalance(PrivatbankAccount $account): ?array
    {
        $settings = $this->getSettings($account);

        if (! $settings || ($settings['status'] ?? '') !== 'SUCCESS') {
            return null;
        }

        if (($settings['settings']['phase'] ?? '') !== 'WRK') {
            Log::warning('PrivatBank not in WRK phase', [
                'account' => $account->display_name,
                'phase'   => $settings['settings']['phase'] ?? 'unknown',
            ]);
            return null;
        }

        $today = date('d-m-Y', strtotime($settings['settings']['today']));

        return $this->fetchBalance($account, $today, $today);
    }

    /**
     * GET /api/statements/balance  — balance for an arbitrary period
     *
     * Use this for period summaries (e.g. current month).
     * turnoverCredit / turnoverDebt in the response are TOTALS for the
     * requested date range (not just today).
     *
     * @param  string  $startDate  format dd-mm-YYYY
     * @param  string  $endDate    format dd-mm-YYYY
     * @return array|null
     */
    public function getBalanceForPeriod(
        PrivatbankAccount $account,
        string $startDate,
        string $endDate,
    ): ?array {
        return $this->fetchBalance($account, $startDate, $endDate);
    }

    /**
     * Shared low-level balance fetcher used by getBalance() and getBalanceForPeriod().
     */
    private function fetchBalance(
        PrivatbankAccount $account,
        string $startDate,
        string $endDate,
    ): ?array {
        try {
            $response = $this->client($account)->get(self::BASE_URL . '/balance', [
                'acc'       => $account->account_number,
                'startDate' => $startDate,
                'endDate'   => $endDate,
            ]);

            if (! $response->successful()) {
                Log::warning('PrivatBank getBalance failed', [
                    'account' => $account->display_name,
                    'status'  => $response->status(),
                ]);
                return null;
            }

            $data = $response->json();

            if (($data['status'] ?? '') === 'SUCCESS' && ! empty($data['balances'])) {
                return $data['balances'][0];
            }

            return null;
        } catch (ConnectionException $e) {
            Log::error('PrivatBank getBalance connection error', [
                'account' => $account->display_name,
                'error'   => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * GET /api/statements/transactions
     *
     * Returns the most recent transactions (newest-first) for a date
     * range, up to $limit rows. Handles PrivatBank's cursor-based
     * pagination internally — stops as soon as $limit rows have been
     * collected, even if more pages exist.
     *
     * Transaction object fields of interest:
     *   AUT_MY_ACC   — IBAN of our account
     *   AUT_CNTR_ACC — counterparty IBAN
     *   AUT_CNTR_NAM — counterparty name
     *   TRANTYPE     — 'C' = credit (money in), 'D' = debit (money out)
     *   SUM          — amount (string, e.g. "500.00")
     *   CCY          — currency (e.g. "UAH")
     *   DAT_KL       — transaction date (e.g. "24.06.2026")
     *   NUM_DOC      — PrivatBank document number
     *   OSND         — payment comment/purpose (used for order matching via regex /91\d{2}-\d+/)
     *
     * @param  string  $startDate  format dd-mm-YYYY
     * @param  string  $endDate    format dd-mm-YYYY
     * @param  int     $limit      max rows to return (default 10 for the detail UI)
     * @return array<int, array>
     */
    public function getTransactions(
        PrivatbankAccount $account,
        string $startDate,
        string $endDate,
        int $limit = 10,
    ): array {
        $params = [
            'acc'       => $account->account_number,
            'startDate' => $startDate,
            'endDate'   => $endDate,
            'limit'     => min($limit, 100), // API max is 100 per page
        ];

        $collected  = [];
        $followId   = null;
        $hasMore    = true;

        try {
            while ($hasMore && count($collected) < $limit) {
                if ($followId) {
                    $params['followId'] = $followId;
                }

                $response = $this->client($account)->get(
                    self::BASE_URL . '/transactions',
                    $params,
                );

                if (! $response->successful()) {
                    Log::warning('PrivatBank getTransactions failed', [
                        'account' => $account->display_name,
                        'status'  => $response->status(),
                    ]);
                    break;
                }

                $data = $response->json();

                if (($data['status'] ?? '') !== 'SUCCESS') {
                    Log::warning('PrivatBank getTransactions API error', [
                        'account'  => $account->display_name,
                        'response' => $data,
                    ]);
                    break;
                }

                $collected = array_merge($collected, $data['transactions'] ?? []);
                $hasMore   = (bool) ($data['exist_next_page'] ?? false);
                $followId  = $data['next_page_id'] ?? null;
            }
        } catch (ConnectionException $e) {
            Log::error('PrivatBank getTransactions connection error', [
                'account' => $account->display_name,
                'error'   => $e->getMessage(),
            ]);
        }

        return array_slice($collected, 0, $limit);
    }

    // ──────────────────────────────────────────────
    // Internal helpers
    // ──────────────────────────────────────────────

    /**
     * Pre-configured Http client for a given account.
     * Token and UserAgent headers are set here so every method gets
     * them automatically.
     */
    private function client(PrivatbankAccount $account): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout(15)
            ->withHeaders([
                'token'        => $account->token,
                'User-Agent'   => $account->user_agent ?? 'ERM-API-Integration',
                'Content-Type' => 'application/json;charset=utf8',
            ]);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SQL VIEW `payments_ledger` — the single dataset behind the "Платежі"
 * page (see CLAUDE.md "Платежі — принципи"): order_payments UNION ALL
 * expenses, with columns normalized to one shape so ONE Filament table
 * can filter across both sources.
 *
 * Why a view and not two tabs/queries: the user explicitly asked for ONE
 * interface with ONE combined filter over ALL money movements. A view
 * keeps that page a plain Eloquent table (sortable/filterable/paginated
 * for free) instead of hand-merged collections.
 *
 * `id` is a synthetic string ('op-123' / 'ex-45') — Filament needs a
 * unique record key across the union. `source` + `source_id` point back
 * to the real row for edit actions.
 *
 * IMPORTANT: when either table gains a column this page should filter
 * on, update this view (new migration re-creating it) — a view's column
 * list is frozen at CREATE time.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS payments_ledger');

        DB::statement(<<<'SQL'
CREATE VIEW payments_ledger AS
SELECT
    CONCAT('op-', op.id)        AS id,
    'order'                     AS source,
    op.id                       AS source_id,
    op.order_id                 AS order_id,
    op.direction                AS direction,
    op.payer_type               AS payer_type,
    op.payer_name               AS payer_name,
    op.payment_method           AS payment_method,
    op.amount                   AS amount,
    op.status                   AS status,
    op.classification_status    AS classification_status,
    op.category                 AS category,
    NULL                        AS sub_category,
    op.comment                  AS comment,
    op.created_by               AS created_by,
    op.paid_at                  AS paid_at,
    CAST(op.fop_account_legacy_id AS CHAR(30)) AS fop_account,
    op.privatbank_num           AS privatbank_num
FROM order_payments op

UNION ALL

SELECT
    CONCAT('ex-', ex.id)        AS id,
    'expense'                   AS source,
    ex.id                       AS source_id,
    NULL                        AS order_id,
    ex.direction                AS direction,
    NULL                        AS payer_type,
    NULL                        AS payer_name,
    ex.payment_method           AS payment_method,
    ex.amount                   AS amount,
    ex.status                   AS status,
    ex.classification_status    AS classification_status,
    ex.category                 AS category,
    ex.sub_category             AS sub_category,
    ex.comment                  AS comment,
    ex.created_by               AS created_by,
    ex.paid_at                  AS paid_at,
    CAST(ex.fop_account_id AS CHAR(30)) AS fop_account,
    ex.privatbank_num           AS privatbank_num
FROM expenses ex
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS payments_ledger');
    }
};

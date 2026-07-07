<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * payments_ledger v2 — adds fop_account_id (FK to privatbank_accounts)
 * from both source tables, replacing the raw legacy string column. A
 * view's column list is frozen at CREATE time, so any column addition
 * requires re-creating it (see the original create_payments_ledger_view
 * docblock).
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
    op.fop_account_id           AS fop_account_id,
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
    ex.fop_account_id           AS fop_account_id,
    ex.privatbank_num           AS privatbank_num
FROM expenses ex
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS payments_ledger');
    }
};

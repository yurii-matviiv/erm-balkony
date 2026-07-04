<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * General (non-order-tied) expense and income entries.
 *
 * The old CRM stored ALL money movements in one `orders_payments` table —
 * including office costs, salaries, taxes, marketing spend — with
 * `order_id = NULL` for general entries. In the new system these are
 * separated: order-specific payments live in `order_payments` (FK to
 * orders), while company-level entries live here.
 *
 * Expense groups (category + sub_category):
 *   telephone  / binotel, sim_cards
 *   office     / rent, electricity, stationery, collection, other_office
 *   marketing  / google, facebook, instagram, outsourced_marketing, videographer
 *   tax        / single_tax
 *   salary     / head_of_sales, (more sub-categories to come)
 *   order      / measurement, other   ← misc costs not tied to a single order
 *
 * `privatbank_num` links the entry to a PrivatBank transaction — used to
 * cross-check that every cashless bank debit is accounted for in the CRM.
 *
 * `fop_account_id` is nullable because cash payments have no FOP account.
 * Will get a proper FK once the "наші юридичні особи/ФОПи" module lands.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            // income = general income not tied to an order (e.g. mosquito-net cash);
            // outgo  = company expense
            $table->enum('direction', ['income', 'outgo'])->default('outgo');

            // cash / cashless
            $table->string('payment_method', 30)->nullable();

            $table->decimal('amount', 12, 2)->default(0);

            // received = confirmed; pending = planned
            $table->string('status', 20)->default('received');

            // Category group — see docblock above
            $table->string('category', 30)->nullable();

            // Sub-category — see docblock above
            $table->string('sub_category', 50)->nullable();

            $table->text('comment')->nullable();

            // Date the expense/income was made
            $table->date('paid_at')->nullable();

            // PrivatBank transaction number — for cashless cross-check
            $table->string('privatbank_num', 50)->nullable();

            // Raw FOP/legal entity ID — FK will appear when the module lands
            $table->unsignedBigInteger('fop_account_id')->nullable();

            // Who logged this entry
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // legacy_id from old orders_payments (NULL for new entries)
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();

            $table->timestamps();

            // Common query patterns
            $table->index(['direction', 'status', 'paid_at']);
            $table->index(['category', 'sub_category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};

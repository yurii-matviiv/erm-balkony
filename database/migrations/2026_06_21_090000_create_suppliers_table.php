<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suppliers are a distinct business entity from clients, not a "type" of
 * client. The old system stored supplier phone calls as rows in `clients`
 * with caller_type='supplier', which conflated two different concepts: a
 * call-intake log (why someone called) and a contact directory (who they
 * are). We are not migrating those old rows automatically — this table
 * starts fresh and gets populated going forward via the Suppliers admin UI.
 *
 * A single supplier company can have many contact people (see
 * supplier_contacts) and many billing/payment identities (see
 * supplier_payment_profiles), because Ukrainian suppliers frequently issue
 * invoices from several payers (different FOPs / sub-divisions) within the
 * same company.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            // Free-form notes about the supplier (terms, history, etc.).
            $table->text('notes')->nullable();

            // Reserved for a future migration of legacy data, if ever
            // needed — kept nullable/unique so it's safe to leave unused.
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};

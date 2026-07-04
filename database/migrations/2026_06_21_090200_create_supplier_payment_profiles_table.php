<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single supplier company often bills through several different payers
 * (separate FOPs / legal sub-divisions within the same business), so
 * payment/billing identities are a one-to-many child of Supplier rather
 * than flat columns on the suppliers table. Each profile is one payer:
 * who they bill as (payer_name), their tax id, and their bank details.
 *
 * Note: the user's own company will likely need an analogous structure
 * later for invoicing clients (their own multiple FOPs), but that is a
 * separate concept tied to managers/the company, not to Supplier — not
 * built yet, intentionally kept out of this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payment_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();

            // Who actually issues/receives the invoice (FOP or legal entity name).
            $table->string('payer_name');

            // EDRPOU (legal entity) or INN/RNOKPP (individual entrepreneur).
            $table->string('tax_id')->nullable();

            $table->string('bank_name')->nullable();
            $table->string('iban')->nullable();
            $table->string('mfo', 10)->nullable();

            $table->text('comment')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_profiles');
    }
};

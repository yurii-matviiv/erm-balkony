<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile-operator API accounts ("Мобільні оператори" integration module) —
 * same pattern as privatbank_accounts: one row = one API connection,
 * owned by a user, credentials stored encrypted, managed via UI.
 *
 * Unlike PrivatBank (IBAN), the identifying pair here is
 * operator + phone_number — so it is always clear WHERE the data comes
 * from (explicit user requirement). First operator: Kyivstar My Business
 * B2B API (b2b-api.kyivstar.ua, OAuth2 client_credentials). Current
 * account balance is read from /rest/billing-accounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_operator_accounts', function (Blueprint $table) {
            $table->id();

            // kyivstar / vodafone / lifecell — dictionary in the model
            $table->string('operator', 20)->default('kyivstar');

            // The phone number the account/balance belongs to
            $table->string('phone_number', 20);

            $table->string('display_name');

            // OAuth2 client-credentials pair from the operator's API portal.
            // Secret is encrypted via the model cast (like PB token).
            $table->string('client_id');
            $table->text('client_secret');

            // Owner — who this connection belongs to (as in privatbank_accounts)
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // One connection per operator+number
            $table->unique(['operator', 'phone_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_operator_accounts');
    }
};

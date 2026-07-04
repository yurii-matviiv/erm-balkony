<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PrivatBank Business API accounts — one row per FOP (individual entrepreneur)
 * that is connected to the PrivatBank integration.
 *
 * In the old system, these were hardcoded in privat24_api/config.php as a PHP
 * array (4 accounts: Iruna, Olena, Pijanova, Yurii). The new system stores
 * them in the database so the admin can add/remove accounts without touching
 * code, and tokens are stored encrypted at rest via Laravel's built-in
 * encrypted cast (AES-256-CBC using APP_KEY).
 *
 * Each account belongs to a User (manager/FOP). The user_agent string is the
 * identifier PrivatBank uses in their API logs — in the old system this was
 * things like 'API_uploading_invoices_Iruna'. We keep it configurable because
 * PrivatBank API tokens are tied to a specific UserAgent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privatbank_accounts', function (Blueprint $table) {
            $table->id();

            // Which manager/FOP this account belongs to
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Human-readable label for the admin UI, e.g. "ФОП Матвієнко Юрій"
            $table->string('display_name');

            // ЄДРПОУ — legal entity code (10 digits for FOPs)
            $table->string('edrpou', 20)->nullable();

            // Full IBAN, e.g. "UA673052990000026001006804387"
            $table->string('account_number', 34);

            // PrivatBank API token — stored encrypted (use 'encrypted' cast in model)
            // Tokens are long strings (UUID prefix + base64 payload ~ 200–300 chars)
            $table->text('token');

            // UserAgent must match what was registered with PrivatBank
            // e.g. 'API_uploading_invoices_Iruna'
            $table->string('user_agent', 100)->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privatbank_accounts');
    }
};

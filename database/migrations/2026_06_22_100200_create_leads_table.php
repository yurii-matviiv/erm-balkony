<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A lead — one sales request from a client. Deliberately does NOT
 * duplicate contact data (name/phone/address all live on Client, see
 * LeadResource's client picker) — a Lead is the "what they want and where
 * they are in our sales process" record, Client is "who they are".
 *
 * Funnel fields (`stage`, `status`, `lost_reason`) are a clean, modern
 * pipeline designed fresh rather than copied from the old `leads.status`
 * enum — that old enum mixed real pipeline stages with disqualification
 * reasons and one-off technical flags in a single column (new, processing,
 * zamir, accepted, canceled, not_targeted, another_city, for_later,
 * reklamatsiya_amtech, propushcheno, vizyt_ofis, ...). We deliberately
 * split that into: `stage` (always moves forward), `status` (open / won /
 * lost), and `lost_reason` (free text, only meaningful when lost).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();

            // Who in our team is handling this lead. Nullable because not
            // every lead is immediately assigned.
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();

            // How this lead reached us. Only the 3 manual-entry options
            // for now (call / office visit / referral) — website/ads
            // sources (UTM, Google Ads, etc.) are a separate future module
            // and will add their own values here, not replace these.
            $table->string('source');

            // 'new' or 'repeat' — computed automatically from whether this
            // client already had a prior lead, not chosen by hand. See
            // LeadResource for the computation.
            $table->string('application_type')->default('new');

            // Sales funnel position — see class docblock.
            $table->string('stage')->default('new');
            $table->string('status')->default('open');
            $table->text('lost_reason')->nullable();

            $table->text('comment')->nullable();

            $table->timestamps();
        });

        Schema::create('lead_lead_service_type', function (Blueprint $table) {
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('lead_service_type_id')->constrained('lead_service_types')->cascadeOnDelete();
            $table->primary(['lead_id', 'lead_service_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_lead_service_type');
        Schema::dropIfExists('leads');
    }
};

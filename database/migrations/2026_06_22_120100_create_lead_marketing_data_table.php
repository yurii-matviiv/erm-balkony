<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing/attribution snapshot for a lead — ONE row per lead (1:1),
 * captured once at the moment the lead came in. Deliberately a separate
 * table from `leads` (per explicit request): a sales rep filling out
 * "Додати заявку" by hand will never touch any of this, it only ever gets
 * populated by automated intake (the future "сайт" module reading UTM
 * params, Facebook Lead Ads, etc.) or by the historical sync below.
 *
 * Not 1:many like LeadCall — this is a single snapshot, not a log of
 * repeated events.
 *
 * Columns map directly onto the old `leads` table's marketing columns
 * (utm_*, device, ip_address, user_fingerprint, client_cookie_id, the old
 * `gdid` — actually a Google Click ID, renamed `gclid` here — gbraid,
 * wbraid, site_source, form_name/form_position, referrer, referral_1-4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_marketing_data', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lead_id')->unique()->constrained('leads')->cascadeOnDelete();

            $table->text('utm_url')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_group')->nullable();
            $table->string('utm_asset_group')->nullable();

            // Which of the company's sites/brands this came through (old
            // enum had e.g. 'btv', 'avicon') — kept as a free string since
            // the future "сайт" module may add more.
            $table->string('site_source')->nullable();

            $table->string('form_name')->nullable();
            $table->string('form_position')->nullable();

            $table->string('device')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_fingerprint')->nullable();
            $table->string('client_cookie_id')->nullable();

            // Google Ads click identifiers.
            $table->string('gclid')->nullable();
            $table->string('gbraid')->nullable();
            $table->string('wbraid')->nullable();

            $table->text('referrer')->nullable();
            $table->text('referral_1')->nullable();
            $table->text('referral_2')->nullable();
            $table->text('referral_3')->nullable();
            $table->text('referral_4')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_marketing_data');
    }
};

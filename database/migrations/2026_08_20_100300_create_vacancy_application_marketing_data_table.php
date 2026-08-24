<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing/attribution snapshot for a submitted VacancyApplication — 1:1,
 * captured once at submission time. Same shape as `vacancy_form_views`
 * minus the page-view semantics; see that migration and `LeadMarketingData`
 * (the equivalent for sales leads) for the reasoning behind keeping this
 * separate from `vacancy_applications` rather than flat columns there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancy_application_marketing_data', function (Blueprint $table) {
            $table->id();

            // Default FK constraint name for this table+column ("..._vacancy_
            // application_id_foreign") is 65 chars — 1 over MySQL's 64-char
            // identifier limit (error 1059). Explicit short name below;
            // the unique index's default name is exactly 64 chars, so it's
            // left as-is.
            $table->foreignId('vacancy_application_id')->unique();
            $table->foreign('vacancy_application_id', 'vam_vacancy_application_id_foreign')
                ->references('id')->on('vacancy_applications')
                ->cascadeOnDelete();

            $table->string('visitor_id')->nullable()->index();

            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();

            $table->text('referrer')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacancy_application_marketing_data');
    }
};

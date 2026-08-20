<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per page load of a public vacancy form — logged on every GET,
 * regardless of whether the candidate ends up submitting anything. This is
 * what makes "views → unique visitors → applications" a real funnel instead
 * of just a count of completed applications. See CLAUDE.md, "Форма набору
 * персоналу", for the full picture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancy_form_views', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vacancy_id')->constrained('vacancies')->cascadeOnDelete();

            // Long-lived cookie value (see App\Services\Marketing\FormAttribution)
            // — same visitor_id across repeat opens from the same browser,
            // which is what lets us count unique visitors, not just hits.
            $table->string('visitor_id')->index();

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
        Schema::dropIfExists('vacancy_form_views');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Skill checkboxes collected on the public application form (see
 * VacancyApplication::skillOptions()) plus a free-text "Інше" field.
 * Only populated by self-service submissions from the public form —
 * applications entered manually by staff are expected to leave these empty
 * and use `comment` instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacancy_applications', function (Blueprint $table) {
            $table->json('skills')->nullable()->after('vacancy_id');
            $table->text('skills_other')->nullable()->after('skills');
        });
    }

    public function down(): void
    {
        Schema::table('vacancy_applications', function (Blueprint $table) {
            $table->dropColumn(['skills', 'skills_other']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which of a user's (possibly several) roles they're currently "viewing
 * the interface as" — separate from which roles they actually HAVE
 * (model_has_roles). A super_admin testing what a manager sees picks
 * "Менеджер" here; their actual permissions/roles are untouched.
 *
 * Stored in the DB (not session), per explicit request: switching on one
 * device must carry over when the same user logs in elsewhere.
 *
 * Nullable: null means "no override" — the navigation falls back to the
 * user's own highest-priority real role (see NavigationResolver).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('active_role')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('active_role');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global (NOT per-role — same for every user/role, per explicit request)
 * override of a navigation item's display label. Deliberately a separate
 * table from `navigation_settings` (which IS per-role): renaming "Ліди"
 * to something else should not require setting it 12 times, once per
 * role — there's exactly one name for a given menu item across the whole
 * app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_labels', function (Blueprint $table) {
            $table->id();
            $table->string('item_key')->unique();
            $table->string('label');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_labels');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-role sidebar customisation — see App\Services\Navigation\
 * NavigationCatalog (auto-discovers every Resource/Page that exists) and
 * NavigationResolver (merges that catalog with these overrides for a
 * given role). Edited from the "Бокова панель" settings page
 * (App\Filament\Pages\Settings\SidebarSettings).
 *
 * One row per (role, navigation item). `role` is the Spatie role NAME
 * (string), not a foreign key — simpler, and role names are already
 * unique per guard. `item_key` is the Resource/Page's fully-qualified
 * class name — stable as long as the class isn't renamed/moved.
 *
 * Absence of a row for a given (role, item) means "use the code default"
 * (the item's own $navigationGroup/$navigationSort/etc.) — this table
 * only needs to hold OVERRIDES, not a full copy of every item for every
 * role, which is what makes new Resources/Pages "just show up" without
 * needing this table populated first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_settings', function (Blueprint $table) {
            $table->id();

            $table->string('role');
            $table->string('item_key');

            $table->string('group_label')->nullable();
            $table->integer('group_sort')->default(0);
            $table->integer('item_sort')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['role', 'item_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_settings');
    }
};

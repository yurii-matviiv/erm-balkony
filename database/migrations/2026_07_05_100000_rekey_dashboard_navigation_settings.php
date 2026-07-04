<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The stock Filament\Pages\Dashboard was replaced by our own
 * App\Filament\Pages\Dashboard (redirects each user to the first item of
 * their role's sidebar). navigation_settings / navigation_labels key items
 * by FQCN, so existing per-role rows for the old class would silently
 * stop matching — re-key them to the new class instead of losing them.
 */
return new class extends Migration
{
    private const OLD = 'Filament\Pages\Dashboard';

    private const NEW = 'App\Filament\Pages\Dashboard';

    public function up(): void
    {
        // A row for the NEW key may not coexist with the old one (unique
        // indexes on role+item_key / item_key) — only rename where no
        // conflicting row already exists.
        foreach (DB::table('navigation_settings')->where('item_key', self::OLD)->get() as $row) {
            $conflict = DB::table('navigation_settings')
                ->where('role', $row->role)
                ->where('item_key', self::NEW)
                ->exists();

            $conflict
                ? DB::table('navigation_settings')->where('id', $row->id)->delete()
                : DB::table('navigation_settings')->where('id', $row->id)->update(['item_key' => self::NEW]);
        }

        if (! DB::table('navigation_labels')->where('item_key', self::NEW)->exists()) {
            DB::table('navigation_labels')
                ->where('item_key', self::OLD)
                ->update(['item_key' => self::NEW]);
        }
    }

    public function down(): void
    {
        DB::table('navigation_settings')->where('item_key', self::NEW)->update(['item_key' => self::OLD]);
        DB::table('navigation_labels')->where('item_key', self::NEW)->update(['item_key' => self::OLD]);
    }
};

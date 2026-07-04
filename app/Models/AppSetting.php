<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Generic key-value settings store.
 *
 * Usage:
 *   AppSetting::get('sync_auto_enabled', false)
 *   AppSetting::set('sync_auto_enabled', true)
 *   AppSetting::get('sync_last_run_at')   // returns string|null
 *
 * Values are stored as strings in the DB; booleans are cast to '1'/'0'.
 * Use the typed helpers (getBool / getTimestamp) for convenience.
 */
class AppSetting extends Model
{
    protected $fillable = ['key', 'value'];

    // ──────────────────────────────────────────────
    // Static helpers
    // ──────────────────────────────────────────────

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::where('key', $key)->first();

        return $row ? $row->value : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value === null ? null : (string) $value],
        );
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $val = static::get($key);

        return $val === null ? $default : filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    public static function toggle(string $key, bool $default = false): bool
    {
        $current = static::getBool($key, $default);
        $new     = ! $current;
        static::set($key, $new ? '1' : '0');

        return $new;
    }
}

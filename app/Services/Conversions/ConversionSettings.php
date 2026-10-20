<?php

namespace App\Services\Conversions;

use App\Models\AppSetting;

/**
 * Runtime switches for offline conversions, stored in app_settings.
 * mode: off (default) | validate (validateOnly, nothing reaches Google Ads) | live.
 */
class ConversionSettings
{
    public const MODES = ['off', 'validate', 'live'];

    public static function mode(): string
    {
        $mode = (string) AppSetting::get('conversions_mode', 'off');

        return in_array($mode, self::MODES, true) ? $mode : 'off';
    }

    public static function setMode(string $mode): void
    {
        if (! in_array($mode, self::MODES, true)) {
            throw new \InvalidArgumentException("Unknown conversions mode: {$mode}");
        }

        AppSetting::set('conversions_mode', $mode);
    }

    public static function bufferMinutes(): int
    {
        return max(0, (int) AppSetting::get('conversions_buffer_minutes', config('conversions.buffer_minutes')));
    }

    public static function setBufferMinutes(int $minutes): void
    {
        AppSetting::set('conversions_buffer_minutes', max(0, $minutes));
    }

    public static function baselineAt(): ?string
    {
        $value = AppSetting::get('conversions_baseline_at');

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public static function markBaselineDone(): void
    {
        AppSetting::set('conversions_baseline_at', now()->toIso8601String());
    }
}

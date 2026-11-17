<?php

namespace App\Support;

/**
 * The old CRM wrote «Автовизначення: <місто>» into the address field when the client gave no
 * address. In the new system that text lives in its own `auto_address` column (visible only to
 * roles that oversee managers), and the real address field stays empty for the manager.
 */
class AutoAddress
{
    private const PREFIX = 'Автовизначення';

    /** @return array{0: ?string, 1: ?string} [real address, auto-detected city/address] */
    public static function split(?string $value): array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return [null, null];
        }

        if (str_starts_with($value, self::PREFIX)) {
            $auto = trim(ltrim(substr($value, strlen(self::PREFIX)), ': '));

            return [null, $auto !== '' ? $auto : null];
        }

        return [$value, null];
    }
}

<?php

namespace App\Services\Marketing;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Shared attribution capture for the public vacancy form: reads UTM query
 * params, referrer, IP, user agent, and a long-lived per-browser visitor
 * cookie. Used both when logging a page view (VacancyFormView) and when
 * logging a submission's marketing snapshot (VacancyApplicationMarketingData)
 * — same shape as `lead_marketing_data`'s UTM columns, minus the fields that
 * only make sense for the sales-lead site module (Google Ads click ids,
 * form_name/form_position, the old free-text `referral_*` columns).
 */
class FormAttribution
{
    private const VISITOR_COOKIE = 'erm_visitor_id';

    // ~5 years — long enough that "how many times did this person open the
    // form" stays meaningful across an entire hiring campaign.
    private const VISITOR_COOKIE_MINUTES = 60 * 24 * 365 * 5;

    public static function captureFromRequest(Request $request): array
    {
        return [
            'visitor_id' => self::visitorId($request),
            'utm_source' => $request->query('utm_source'),
            'utm_medium' => $request->query('utm_medium'),
            'utm_campaign' => $request->query('utm_campaign'),
            'utm_term' => $request->query('utm_term'),
            'utm_content' => $request->query('utm_content'),
            'referrer' => $request->header('referer'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }

    /**
     * Reads the visitor cookie if present, otherwise mints one and queues it
     * to be set on the response. The same value is returned to the caller
     * either way so it can be stored on the row being created right now
     * (queued cookies aren't readable from $request until the next request).
     */
    public static function visitorId(Request $request): string
    {
        $existing = $request->cookie(self::VISITOR_COOKIE);
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $id = (string) Str::uuid();
        Cookie::queue(self::VISITOR_COOKIE, $id, self::VISITOR_COOKIE_MINUTES);

        return $id;
    }

    /**
     * Best-effort mapping from utm_source onto VacancyApplication's fixed
     * channel list, for consistency with applications entered manually by
     * staff. Falls back to 'unknown' rather than leaving an arbitrary raw
     * utm_source string in a column meant to hold one of a known set of
     * values (see VacancyApplication::channelOptions()).
     */
    public static function guessChannel(Request $request): string
    {
        $source = strtolower((string) $request->query('utm_source', ''));

        return match (true) {
            $source === '' => 'unknown',
            str_contains($source, 'instagram') || $source === 'ig' => 'instagram',
            str_contains($source, 'facebook') || $source === 'fb' => 'facebook',
            str_contains($source, 'telegram') => 'telegram',
            str_contains($source, 'tiktok') => 'tiktok',
            str_contains($source, 'work.ua') => 'work.ua',
            str_contains($source, 'robota.ua') => 'robota.ua',
            str_contains($source, 'olx') => 'olx',
            default => 'unknown',
        };
    }
}

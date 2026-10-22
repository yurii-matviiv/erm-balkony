<?php

namespace App\Services\Conversions;

use App\Models\Client;
use App\Models\Lead;

/**
 * Builds Google "enhanced conversions for leads" identifiers: normalised and
 * SHA-256 hashed (hex) phone / email of the lead's client. Raw values are never
 * stored in the outbox or logged — hashes are computed at send time.
 *
 * Normalisation per Google docs: trim + lowercase for email, E.164 for phone.
 */
class UserDataBuilder
{
    /**
     * @return array<int, array<string, string>> userIdentifiers, e.g. [['phoneNumber' => '<sha256>'], ...]
     */
    public function forLead(Lead $lead): array
    {
        $client = $lead->client;

        return $client ? $this->forClient($client) : [];
    }

    /**
     * @return array<int, array<string, string>> userIdentifiers for a client
     */
    public function forClient(Client $client): array
    {
        $identifiers = [];

        foreach (array_unique(array_filter([
            $this->normalizePhone((string) $client->phone),
            $this->normalizePhone((string) $client->phone2),
        ])) as $phone) {
            $identifiers[] = ['phoneNumber' => hash('sha256', $phone)];
        }

        $email = $this->normalizeEmail((string) $client->email);

        if ($email !== null) {
            $identifiers[] = ['emailAddress' => hash('sha256', $email)];
        }

        return $identifiers;
    }

    /**
     * Google normalisation: trim, lowercase; for gmail.com / googlemail.com also
     * drop dots and "+tag" in the local part.
     */
    public function normalizeEmail(string $raw): ?string
    {
        $email = preg_replace('/\s+/u', '', mb_strtolower(trim($raw))) ?? '';

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);

        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $local = str_replace('.', '', explode('+', $local, 2)[0]);

            if ($local === '') {
                return null;
            }

            return $local.'@'.$domain;
        }

        return $email;
    }

    /**
     * Ukrainian numbers (default country) to E.164; other international numbers
     * are accepted only if they were written with "+" / "00" and have a plausible length.
     */
    public function normalizePhone(string $raw): ?string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        $international = str_starts_with($raw, '+') || str_starts_with($raw, '00');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if ($international && ! str_starts_with($digits, '380')) {
            return strlen($digits) >= 10 && strlen($digits) <= 15 ? '+'.$digits : null;
        }

        // Ukrainian formats: 0XXXXXXXXX, 80XXXXXXXXX, 380XXXXXXXXX, XXXXXXXXX
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '38'.$digits;
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '80')) {
            $digits = '3'.$digits;
        } elseif (strlen($digits) === 9) {
            $digits = '380'.$digits;
        }

        return strlen($digits) === 12 && str_starts_with($digits, '380') ? '+'.$digits : null;
    }
}

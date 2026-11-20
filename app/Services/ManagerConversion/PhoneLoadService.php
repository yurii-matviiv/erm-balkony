<?php

namespace App\Services\ManagerConversion;

/** Maps Binotel employees to ERM managers and turns raw call totals into per-manager phone workload. */
final class PhoneLoadService
{
    public function __construct(private readonly ManagerStatsService $stats)
    {
    }

    /**
     * Binotel knows employees by name ("Анна SIP", "Ірина SIM"), ERM users by
     * "Прізвище Ім'я По батькові". A manager is matched by first name; one
     * person can have several Binotel endpoints (SIP + SIM) — they are summed.
     * Fine while active managers have distinct first names; a clash would
     * merge two people, so this is flagged in the UI note.
     *
     * @param  list<array<string,mixed>>  $managers
     * @param  array<string,array<string,mixed>>  $employees
     * @return list<array<string,mixed>>
     */
    public function perManager(array $managers, array $employees, string $from, string $to): array
    {
        $workDays = max(1, $this->stats->workingDays($from, $to));
        $out = [];

        foreach ($managers as $m) {
            $first = mb_strtolower(explode(' ', trim($m['name']))[1] ?? '');
            $t = ['in' => 0, 'out' => 0, 'out_try' => 0, 'sec' => 0];

            foreach ($employees as $e) {
                if ($first !== '' && mb_strtolower(explode(' ', trim($e['name']))[0]) === $first) {
                    $t['in'] += $e['in_answered'];
                    $t['out'] += $e['out_answered'];
                    $t['out_try'] += $e['out_attempts'];
                    $t['sec'] += $e['in_talk_sec'] + $e['out_talk_sec'];
                }
            }

            $out[] = [
                'id' => $m['id'],
                'name' => implode(' ', array_slice(explode(' ', trim($m['name'])), 0, 2)),
                'in' => $t['in'],
                'out' => $t['out'],
                'out_try' => $t['out_try'],
                'sec' => $t['sec'],
                'calls_per_day' => round(($t['in'] + $t['out']) / $workDays, 1),
                'hours_per_day' => round($t['sec'] / 3600 / $workDays, 1),
            ];
        }

        usort($out, fn ($a, $b) => $b['sec'] <=> $a['sec']);

        return $out;
    }
}

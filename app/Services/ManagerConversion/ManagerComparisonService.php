<?php

namespace App\Services\ManagerConversion;

/**
 * Compare mode: ONE manager over two periods. Returns his row for each period (zeros if he had nothing),
 * and the Binotel phone figures of each period when they are loaded. Always ordered from the past to the
 * future: "a" = the earlier period, "b" = the later one, whatever order the filters were filled in.
 */
final class ManagerComparisonService
{
    public function __construct(
        private readonly ManagerStatsService $stats,
        private readonly PhoneLoadService $phone,
    ) {
    }

    /**
     * @param  list<array<string,mixed>>  $managersA  managers of period 1 (already loaded)
     * @param  array{0:string,1:string}  $periodA
     * @param  array{0:string,1:string}  $periodB
     * @param  array{key:string,state:?array}  $binotelA  Binotel state loaded for period 1 (key = "from|to")
     * @param  array{key:string,state:?array}  $binotelB
     * @return array<string,mixed>
     */
    public function compare(int $managerId, array $managersA, array $periodA, array $periodB, array $binotelA, array $binotelB): array
    {
        [$fromA, $toA] = $periodA;
        [$fromB, $toB] = $periodB;
        $baseB = $this->stats->forPeriod($fromB, $toB);

        // Managers known in either period; the picker lists all of them.
        $options = array_column($managersA, 'name', 'id') + array_column($baseB['managers'], 'name', 'id');
        $id = isset($options[$managerId]) ? $managerId : (int) (array_key_first($options) ?? 0);

        $pick = fn (array $rows) => collect($rows)->firstWhere('id', $id);
        $empty = ['id' => $id, 'name' => $options[$id] ?? '—', 'leads' => 0, 'converted' => 0, 'orders' => 0, 'rate' => 0.0, 'turnover' => 0.0,
            'ad_cost' => 0.0, 'ad_budget' => 0.0, 'drr' => null, 'result' => 0.0, 'per_day' => null, 'load' => null, 'load_level' => 'none', 'peak' => 0];

        $phoneRow = function (array $binotel, array $managers, string $from, string $to) use ($id): ?array {
            $state = $binotel['state'];
            if ($binotel['key'] !== $from.'|'.$to || ! ($state['ok'] ?? false)) {
                return null;
            }

            return collect($this->phone->perManager($managers, $state['employees'], $from, $to))->firstWhere('id', $id);
        };

        $data = [
            'options' => $options,
            'managerId' => $id,
            'period1' => [$fromA, $toA],
            'period2' => [$fromB, $toB],
            'a' => $pick($managersA) ?? $empty,
            'b' => $pick($baseB['managers']) ?? $empty,
            'phoneA' => $phoneRow($binotelA, $managersA, $fromA, $toA),
            'phoneB' => $phoneRow($binotelB, $baseB['managers'], $fromB, $toB),
            'phoneStateA' => $binotelA['key'] === $fromA.'|'.$toA ? $binotelA['state'] : null,
            'phoneStateB' => $binotelB['key'] === $fromB.'|'.$toB ? $binotelB['state'] : null,
        ];

        if ($fromA > $fromB) {
            foreach ([['period1', 'period2'], ['a', 'b'], ['phoneA', 'phoneB'], ['phoneStateA', 'phoneStateB']] as [$x, $y]) {
                [$data[$x], $data[$y]] = [$data[$y], $data[$x]];
            }
        }

        return $data;
    }
}

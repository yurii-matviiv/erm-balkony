<?php

namespace App\Services\Leads;

use App\Models\Lead;

/**
 * Показники роботи менеджера для бару над таблицею лідів. Усе береться зі збережених полів (без перерахунків по дзвінках).
 */
class ManagerIndicators
{
    public const DAYS = 30;

    /**
     * Середня швидкість реакції за останні DAYS днів по заявках менеджера.
     * Заявки без значення (дзвінка ще не було) в середнє НЕ входять — AVG пропускає NULL.
     *
     * @return array{avg: ?int, counted: int, without: int}
     */
    public static function reaction(int $managerId): array
    {
        $row = Lead::query()
            ->where('manager_id', $managerId)
            ->where('created_at', '>=', now()->subDays(self::DAYS))
            ->selectRaw('AVG(first_reaction_minutes) as avg_min, COUNT(first_reaction_minutes) as counted, COUNT(*) as total')
            ->first();

        $counted = (int) ($row->counted ?? 0);

        return [
            'avg' => $counted > 0 ? (int) round((float) $row->avg_min) : null,
            'counted' => $counted,
            'without' => (int) ($row->total ?? 0) - $counted,
        ];
    }

    public static function formatMinutes(int $min): string
    {
        if ($min < 1) {
            return 'менше хвилини';
        }
        if ($min < 60) {
            return $min.' хв';
        }
        if ($min < 1440) {
            return intdiv($min, 60).' год '.($min % 60).' хв';
        }

        return intdiv($min, 1440).' дн '.intdiv($min % 1440, 60).' год';
    }

    /** Колір за тими самими порогами, що й у картці заявки: до 15 хв — зелений, до 60 — жовтий, далі — червоний. */
    public static function reactionColor(int $min): string
    {
        return $min <= 15 ? '#1e8449' : ($min <= 60 ? '#b9770e' : '#c0392b');
    }
}

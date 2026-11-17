<?php

use App\Models\Lead;
use App\Services\Leads\FirstCallDetector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Швидкість реакції менеджера — хвилини від створення заявки до першого вихідного дзвінка клієнту (Binotel).
 * NULL = дзвінка ще не було (такі заявки НЕ входять у середнє). Дзвінок до створення заявки = 0.
 * Індекс (manager_id, created_at) — для швидкого середнього по менеджеру за період.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->unsignedInteger('first_reaction_minutes')->nullable()->after('first_call_checked_at');
            $table->index(['manager_id', 'created_at'], 'leads_manager_created_idx');
        });

        // Одноразово: заповнити з уже записаних кроків first_call.
        DB::table('process_steps')
            ->where('subject_type', (new Lead)->getMorphClass())
            ->where('step', 'first_call')->where('state', 'done')->whereNotNull('occurred_at')
            ->orderBy('id')
            ->each(function ($row): void {
                $lead = Lead::find($row->subject_id);

                if ($lead) {
                    DB::table('leads')->where('id', $lead->id)->update([
                        'first_reaction_minutes' => FirstCallDetector::reactionMinutes($lead, new \DateTimeImmutable($row->occurred_at, new \DateTimeZone(config('app.timezone', 'UTC')))),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex('leads_manager_created_idx');
            $table->dropColumn('first_reaction_minutes');
        });
    }
};

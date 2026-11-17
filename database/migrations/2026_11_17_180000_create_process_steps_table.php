<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Journal of steps of leads and orders (docs/STAGES.md): each step that happened is its own row,
 * instead of overwriting one «stage» field. Existing records get ONE legacy row for their current stage
 * (no date: we only know the step was reached).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_steps', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->string('step', 40);
            $table->string('state', 20)->default('done');   // planned | done | cancelled
            $table->string('source', 20)->default('auto');  // auto | manual | legacy
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('occurred_at')->nullable();
            $table->string('comment', 500)->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'step']);
            $table->index(['step', 'state', 'occurred_at']);
        });

        foreach (['leads' => 'App\\Models\\Lead', 'orders' => 'App\\Models\\Order'] as $table => $class) {
            DB::statement("INSERT INTO process_steps (subject_type, subject_id, step, state, source, occurred_at, created_at, updated_at)
                SELECT ?, id, stage, 'done', 'legacy', NULL, NOW(), NOW() FROM {$table} WHERE stage IS NOT NULL AND stage <> ''", [$class]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('process_steps');
    }
};

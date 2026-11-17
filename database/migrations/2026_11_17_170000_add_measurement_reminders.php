<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Нагадування про заміри (вкладка «Заміри»): тип нагадування, зв'язок із записом про замір,
 * підтвердження «Побачив» і лічильник відправлених push (для повторів). Див. config/measurement_reminders.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manager_reminders', function (Blueprint $table): void {
            // 'lead' = звичайне нагадування по заявці (як було), 'measurement' = автоматичне про замір.
            $table->string('kind', 20)->default('lead')->after('reminder_type');
            $table->foreignId('lead_measurement_id')->nullable()->after('lead_id')
                ->constrained('lead_measurements')->cascadeOnDelete();
            $table->dateTime('acknowledged_at')->nullable()->after('is_completed');
            $table->unsignedSmallInteger('notify_count')->default(0)->after('notified_at');

            $table->index(['kind', 'user_id', 'is_completed']);
        });
    }

    public function down(): void
    {
        Schema::table('manager_reminders', function (Blueprint $table): void {
            $table->dropIndex(['kind', 'user_id', 'is_completed']);
            $table->dropConstrainedForeignId('lead_measurement_id');
            $table->dropColumn(['kind', 'acknowledged_at', 'notify_count']);
        });
    }
};

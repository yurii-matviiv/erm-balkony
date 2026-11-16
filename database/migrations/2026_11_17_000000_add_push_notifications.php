<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Web Push for reminders (see docs/SCHEDULER.md): browser subscriptions per user
// and a "already notified" mark on the reminder so the minute-by-minute job
// never sends the same reminder twice.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Endpoint URLs are long; uniqueness is enforced on a hash of it.
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->string('p256dh');
            $table->string('auth');
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::table('manager_reminders', function (Blueprint $table) {
            $table->dateTime('notified_at')->nullable()->after('is_completed');
            // Serves the per-minute "what is due and not yet notified" lookup.
            $table->index(['notified_at', 'is_completed', 'reminder_datetime'], 'reminders_due_lookup');
        });

        // Everything already in the past (incl. thousands of legacy reminders) must NOT
        // fire when the dispatcher is switched on — only future reminders should.
        DB::table('manager_reminders')
            ->where('reminder_datetime', '<=', now())
            ->update(['notified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('manager_reminders', function (Blueprint $table) {
            $table->dropIndex('reminders_due_lookup');
            $table->dropColumn('notified_at');
        });

        Schema::dropIfExists('push_subscriptions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brand-new table in the new system (the old CRM has no separate "clients"
 * concept beyond its own `clients` table — this mirrors its contact data
 * one-to-one, plus the usual sync bookkeeping fields).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();

            // Reference to the row in the OLD database this client came
            // from — same purpose as users.legacy_id, see that migration.
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();

            $table->string('name');
            $table->string('phone', 30);
            $table->string('phone2', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('viber', 30)->nullable();
            $table->string('address')->nullable();
            $table->text('comment')->nullable();

            // Matches the old `caller_type` enum: client / supplier / spam / other.
            $table->string('caller_type')->nullable();

            // The staff member (new `users`.id) who added this client.
            // Nullable because old rows may point to a manager that wasn't
            // synced (or has none recorded).
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The old lead card has these fields and managers use them daily: result/action
// comment, window profile type and up to three preliminary calculations.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->text('comment_callback')->nullable()->after('comment_call');
            $table->string('profile')->nullable()->after('comment_sales_head');
            $table->string('advance_calculation1', 250)->nullable()->after('profile');
            $table->string('advance_calculation2', 250)->nullable()->after('advance_calculation1');
            $table->string('advance_calculation3', 250)->nullable()->after('advance_calculation2');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['comment_callback', 'profile', 'advance_calculation1', 'advance_calculation2', 'advance_calculation3']);
        });
    }
};

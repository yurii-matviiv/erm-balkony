<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Old CRM list shows three separate comment columns ("Коментар", "Що планую
// робити далі", "Звернути увагу!"). They were merged into one `comment` during
// sync, so managers lost the familiar layout. Restore them as separate fields.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->text('comment_call')->nullable()->after('comment');
            $table->text('comment_sales_head')->nullable()->after('comment_call');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['comment_call', 'comment_sales_head']);
        });
    }
};

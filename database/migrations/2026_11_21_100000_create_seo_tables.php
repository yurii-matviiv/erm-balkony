<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Знімки позицій з Google Search Console (щотижня). Це аналітика, не бізнес-дані CRM.
        Schema::create('seo_position_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date');
            $table->date('period_start');
            $table->date('period_end');
            // new_page | new_page_query | top_page | top_query
            $table->string('scope', 20);
            $table->string('page', 500)->default('');
            // '' для рядків без запиту (щоб працювала унікальність; у MySQL NULL у unique не рахується)
            $table->string('query', 500)->default('');
            $table->char('page_hash', 40);
            $table->char('query_hash', 40);
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->decimal('ctr', 8, 6)->default(0);
            // null = немає даних (0 показів), а не «позиція 0»
            $table->decimal('position', 8, 2)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['snapshot_date', 'scope', 'page_hash', 'query_hash'], 'seo_snap_unique');
            $table->index(['scope', 'snapshot_date']);
        });

        Schema::create('seo_index_status', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date');
            $table->string('page', 500);
            $table->char('page_hash', 40);
            $table->string('coverage_state')->nullable();
            $table->string('last_crawl_time')->nullable();
            $table->string('google_canonical', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['snapshot_date', 'page_hash']);
        });

        // Зафіксований у першому знімку склад топ-30 (сторінки й запити) — далі відстежується той самий набір.
        Schema::create('seo_tracked_items', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 20); // top_page | top_query
            $table->string('item', 500);
            $table->char('item_hash', 40);
            $table->date('added_at');
            $table->date('removed_at')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'item_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_tracked_items');
        Schema::dropIfExists('seo_index_status');
        Schema::dropIfExists('seo_position_snapshots');
    }
};

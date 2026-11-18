<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Скільки разів кожен користувач відкривав кожну сторінку меню (Resource/Page).
 * Один рядок = (користувач, сторінка). `page_key` — FQCN Resource/Page, той самий
 * ключ, що й у NavigationCatalog / navigation_settings. Живить «Дашборд»
 * (5 найчастіших сторінок), див. App\Http\Middleware\TrackPageVisit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('page_key');
            $table->unsignedInteger('visits')->default(0);
            $table->timestamp('last_visited_at')->nullable();

            $table->unique(['user_id', 'page_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_visits');
    }
};

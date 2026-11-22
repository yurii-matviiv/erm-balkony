<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_excluded_keywords', function (Blueprint $t) {
            $t->id();
            $t->string('keyword');
            $t->char('keyword_hash', 40)->unique();
            $t->string('source', 10)->default('manual'); // manual | auto (за правилами config/seo.php)
            $t->timestamp('created_at')->nullable();
            // Видалене користувачем не повертається автоматично.
            $t->timestamp('deleted_at')->nullable();
        });

        // Одразу відсіяти вже зібрані слова про ремонт і регулювання вікон.
        try {
            app(\App\Services\Seo\SeoExclusionService::class)->seedFromSnapshots();
        } catch (\Throwable) {
            // знімків ще немає — слова додадуться після першого знімка
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_excluded_keywords');
    }
};

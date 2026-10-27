<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ad budget allocation: Google Ads campaigns + daily stats pulled via the API,
 * and a mapping "utm_campaign of a lead -> Google Ads campaign" (the UTM names
 * do not always equal the Google names, so it is explicit and must be confirmed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('google_campaign_id')->unique();
            $table->string('name');
            $table->string('status', 20)->nullable();
            $table->string('channel_type', 40)->nullable();
            $table->unsignedBigInteger('budget_micros')->nullable();
            $table->timestamps();
        });

        Schema::create('ad_campaign_daily', function (Blueprint $table) {
            $table->id();
            $table->string('google_campaign_id');
            $table->date('date');
            $table->unsignedBigInteger('cost_micros')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->decimal('conversions', 12, 2)->default(0);
            $table->decimal('budget_lost_share', 6, 4)->nullable();
            $table->timestamps();

            $table->unique(['google_campaign_id', 'date']);
        });

        Schema::create('ad_campaign_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('utm_campaign')->unique();
            $table->string('google_campaign_id');
            $table->boolean('confirmed')->default(false);
            $table->timestamps();
        });

        // Suggested by name similarity; NOT confirmed until the owner clicks "Підтвердити".
        $suggested = [
            'IW_PMax_Vikna_Kyiv_Competitors' => '21904422650',
            'Вікна|SNC_ADW' => '22359637331',
            'Балкони|тераси|SNC_ADW' => '22353308301',
            'search_vikna_Rehau' => '21898194933',
            'DG|DNC_ADW' => '23657890698',
        ];

        foreach ($suggested as $utm => $id) {
            DB::table('ad_campaign_mappings')->insert([
                'utm_campaign' => $utm,
                'google_campaign_id' => $id,
                'confirmed' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_campaign_mappings');
        Schema::dropIfExists('ad_campaign_daily');
        Schema::dropIfExists('ad_campaigns');
    }
};

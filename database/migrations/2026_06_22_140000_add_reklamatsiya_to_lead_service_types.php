<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Рекламація" (a complaint/warranty claim about an already-installed
 * window) is a TYPE OF REQUEST ("тип звернення" — same dimension as
 * "Вікна", "Балкони"), not a reason a sale was lost. Originally
 * LeadsSyncMapper mapped the old `status='reklamatsiya_amtech'` value to
 * status=lost — corrected after user feedback. See the updated
 * LeadsSyncMapper for the corrected mapping.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('lead_service_types')->where('name', 'Рекламація')->exists()) {
            DB::table('lead_service_types')->insert([
                'name' => 'Рекламація',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('lead_service_types')->where('name', 'Рекламація')->delete();
    }
};

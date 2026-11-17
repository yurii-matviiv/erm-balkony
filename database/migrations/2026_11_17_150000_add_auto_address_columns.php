<?php

use App\Support\AutoAddress;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «Автовизначення: <місто>» зі старої CRM переїжджає в окреме поле auto_address
 * (бачать лише Адмін / Керівник компанії / Керівник відділу продажу), а звичайне поле адреси
 * лишається порожнім для менеджера. Нові синхронізації розкладають значення так само
 * (див. AutoAddress у мапперах).
 */
return new class extends Migration
{
    /** table => [real address column, nullable?] */
    private const TARGETS = [
        'leads' => ['site_street', true],
        'clients' => ['address', true],
        'orders' => ['address', false],
    ];

    public function up(): void
    {
        foreach (self::TARGETS as $table => [$column, $nullable]) {
            Schema::table($table, function (Blueprint $t) use ($column): void {
                $t->string('auto_address')->nullable()->after($column);
            });

            DB::table($table)
                ->where($column, 'like', 'Автовизначення%')
                // chunkById: rows leave the «like» filter while we update them, so offset paging would skip rows.
                ->chunkById(500, function ($rows) use ($table, $column, $nullable): void {
                    foreach ($rows as $row) {
                        [, $auto] = AutoAddress::split($row->{$column});
                        DB::table($table)->where('id', $row->id)->update([
                            'auto_address' => $auto,
                            $column => $nullable ? null : '',
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        foreach (self::TARGETS as $table => [$column]) {
            DB::table($table)->whereNotNull('auto_address')->orderBy('id')->each(
                fn ($row) => DB::table($table)->where('id', $row->id)->update([$column => 'Автовизначення: '.$row->auto_address]),
            );
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('auto_address'));
        }
    }
};

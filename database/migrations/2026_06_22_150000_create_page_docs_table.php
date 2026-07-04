<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Документація сторінки" — an in-app, database-stored explanation block
 * that admins/founders attach to any admin page (starting with the Leads
 * page's "Статуси" explainer) so every user has a single source of truth
 * for how to use that page/field, without leaving the app or relying on
 * tribal knowledge. See App\Filament\Concerns\HasPageDocs for how a
 * Filament page wires this in, and PageDoc model for the read/write API.
 *
 * One page can have several named sections (`section_key`) — e.g. a
 * general page intro plus a separate block just about statuses — added
 * incrementally as the need comes up, not all at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_docs', function (Blueprint $table) {
            $table->id();

            // Which page this belongs to, e.g. "leads". Free string, not
            // a foreign key — there's no single "pages" table to point at.
            $table->string('page_key');

            // Null = the page's general/intro doc. Otherwise a specific
            // named block, e.g. "statuses".
            $table->string('section_key')->nullable();

            $table->string('title')->nullable();
            $table->longText('content')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['page_key', 'section_key']);
        });

        // A role for editing this documentation, separate from
        // super_admin: per explicit request, both the technical admin AND
        // the company founder (who may not otherwise need any other admin
        // permissions) should be able to edit it. Created with no other
        // Laravel permissions attached — same "zero permissions by
        // default" safety convention used for roles synced from the old
        // atom_roles (see UsersSyncMapper).
        if (! DB::table('roles')->where('name', 'founder')->where('guard_name', 'web')->exists()) {
            DB::table('roles')->insert([
                'name' => 'founder',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('page_docs');
        DB::table('roles')->where('name', 'founder')->where('guard_name', 'web')->delete();
    }
};

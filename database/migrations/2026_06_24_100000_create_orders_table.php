<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Замовлення" — the next stage after a Lead, created by the "Створити
 * замовлення" action on the Lead edit page (see EditLead). One Lead can
 * have at most one Order in normal flow, but the FK lives on `orders`
 * (not a 1:1 enforced at DB level) so historical data — and the rare case
 * of a corrected/duplicate order — aren't blocked.
 *
 * Field set and naming is deliberately close to the OLD system's `orders`
 * table (read read-only via the `legacy` connection for sync — see
 * OrdersSyncMapper) so the two stay easy to compare/reason about side by
 * side, NOT because every old field is good design. Several things from
 * the old `orders` were intentionally left OUT of this first pass — they
 * belong to modules that don't exist yet, see CLAUDE.md "Замовлення":
 * - per-payment rows (old `orders_payments`) -> future "Оплати" module.
 * - uploaded files (old `orders_files`) -> not planned yet (no file
 *   storage strategy decided), and the old system saved these straight to
 *   Google Disk integration which we are NOT replicating.
 * - supplier commercial offer / invoice / "paid invoice to supplier" UI
 *   blocks -> future "Рахунки"/"Оплати" modules (the raw $ columns that
 *   feed them, e.g. invoice_from_supplier, ARE kept below since they're
 *   plain data, just not built into a UI screen yet).
 * - the live JS salary-calculator behaviour on the cost block (e.g.
 *   deriving montage_salary from a chosen percentage) -> belongs to the
 *   future "Зарплата" module's business logic, not this entity.
 *
 * `stage`/`status` split mirrors the same fix already applied to Lead
 * (see create_leads_table migration): the old system had ONE numeric
 * `status_new` (1-10, looked up against a `statuses` table) plus an
 * unrelated OLDER text `status` enum that nothing fully agreed on by the
 * end — both are preserved verbatim as `legacy_status`/`legacy_status_new`
 * for traceability, but the new `stage` is a clean re-derivation of
 * `status_new`'s intent, and `status` is just open/done/cancelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('legacy_id')->nullable()->unique();

            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();

            // "Якщо двоє ведуть 1 замовлення" — old comment on consultant_id.
            $table->foreignId('consultant_id')->nullable()->constrained('users')->nullOnDelete();

            // Same crew-assignment shape as LeadMeasurement, and the same
            // rule applies: `surveyor_id` (старе "gauger_id"/Замірник) is
            // ALWAYS the responsible person for the job, even if a
            // different installer is also assigned. See LeadMeasurement
            // migration docblock — this is the same business rule, now
            // carried into the Order.
            $table->foreignId('installer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('surveyor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();

            // The object's address — copied from Lead::site_address at
            // creation time (see CreateOrderAction), but stored on the
            // Order too: a Lead's site_* fields could change later
            // without the Order's installation address following along.
            $table->string('address');

            $table->string('order_type')->nullable();

            $table->string('contract_number')->nullable();
            $table->string('vendor_number')->nullable();
            $table->string('calculation_number')->nullable();

            $table->float('square_meters')->nullable();
            $table->decimal('montage_price_m2', 10, 2)->nullable();
            $table->integer('montage_price')->nullable();
            $table->integer('montage_salary')->nullable();
            $table->float('additional_price')->nullable();
            $table->float('additional_salary')->nullable();
            $table->float('measuring_price')->nullable();
            $table->float('gazda_price')->nullable()->comment('Стара назва — постачальник герметика/піни "Газда", не сама ціна "газди" в загальному сенсі.');
            $table->decimal('cost_of_lifts', 10, 2)->nullable()->comment('Вартість підйомів, якщо викликали вантажників.');
            $table->integer('total_price')->nullable();
            $table->float('balance')->nullable()->comment('Залишок, який має сплатити замовник.');
            $table->integer('discount')->nullable()->comment('Відсоток знижки.');
            $table->decimal('bonus', 10, 2)->nullable();

            $table->string('invoice_from_supplier')->nullable();
            $table->string('paid_invoice_to_supplier')->nullable();

            $table->boolean('is_need_install')->default(true);
            $table->boolean('is_need_measuring')->default(true);

            $table->date('measurement_date')->nullable();
            $table->date('readiness_date')->nullable();
            $table->time('delivery_time')->nullable();
            $table->date('removal_date')->nullable();
            $table->boolean('removal_request_sent')->default(false);
            $table->date('montage_date')->nullable();
            $table->date('montage_date_2')->nullable();
            $table->date('montage_date_3')->nullable();
            $table->date('montage_date_4')->nullable();
            $table->date('success_date')->nullable();
            $table->date('cancel_date')->nullable();

            $table->text('comment')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->text('client_feedback')->nullable();

            // Clean funnel position — see class docblock. Always moves
            // forward; whether the order is still active/done/cancelled is
            // the separate `status` field (same pattern as Lead).
            $table->string('stage')->default('new');
            $table->string('status')->default('open');

            // Raw old values, kept verbatim for traceability — see class
            // docblock. NOT used by any new code, only for looking up "what
            // was this in the old system" while sync/migration questions
            // come up.
            $table->string('legacy_status')->nullable();
            $table->unsignedTinyInteger('legacy_status_new')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

<?php
// LEGACY-CLEANUP (файл): потрібен лише для синхронізації зі старою CRM. Видалити після повного переходу на нову базу (див. docs/LEGACY-CLEANUP.md).

namespace App\Services\Sync\Mappers;

class InvoiceFromSupplierFileSyncMapper extends AbstractOrderFileSyncMapper
{
    public function key(): string   { return 'invoice_from_supplier_files'; }
    public function label(): string { return 'Файли: Рахунки від постачальника'; }
    public function oldTable(): string { return 'invoice_from_supplier'; }
    protected function fileType(): string { return 'supplier_invoice'; }
}

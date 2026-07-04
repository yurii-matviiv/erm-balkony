<?php

namespace App\Services\Sync\Mappers;

class InvoiceFromSupplierFileSyncMapper extends AbstractOrderFileSyncMapper
{
    public function key(): string   { return 'invoice_from_supplier_files'; }
    public function label(): string { return 'Файли: Рахунки від постачальника'; }
    public function oldTable(): string { return 'invoice_from_supplier'; }
    protected function fileType(): string { return 'supplier_invoice'; }
}

<?php

namespace App\Services\Sync\Mappers;

class PaidInvoiceToSupplierFileSyncMapper extends AbstractOrderFileSyncMapper
{
    public function key(): string   { return 'paid_invoice_to_supplier_files'; }
    public function label(): string { return 'Файли: Оплачені рахунки постачальнику'; }
    public function oldTable(): string { return 'paid_invoice_to_supplier'; }
    protected function fileType(): string { return 'paid_invoice'; }
}

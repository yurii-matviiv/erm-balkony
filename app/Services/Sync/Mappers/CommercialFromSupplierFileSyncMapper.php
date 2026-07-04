<?php

namespace App\Services\Sync\Mappers;

class CommercialFromSupplierFileSyncMapper extends AbstractOrderFileSyncMapper
{
    public function key(): string   { return 'commercial_from_supplier_files'; }
    public function label(): string { return 'Файли: Комерційні пропозиції від постачальника'; }
    public function oldTable(): string { return 'commercial_from_supplier'; }
    protected function fileType(): string { return 'commercial'; }
}

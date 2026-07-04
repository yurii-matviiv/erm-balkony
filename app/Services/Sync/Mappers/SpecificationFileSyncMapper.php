<?php

namespace App\Services\Sync\Mappers;

class SpecificationFileSyncMapper extends AbstractOrderFileSyncMapper
{
    public function key(): string   { return 'specification_files'; }
    public function label(): string { return 'Файли: Специфікації до договору'; }
    public function oldTable(): string { return 'specification_file'; }
    protected function fileType(): string { return 'specification'; }
}

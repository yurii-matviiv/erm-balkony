<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One block of in-app documentation attached to a page (and optionally a
 * named section within it). See the create_page_docs_table migration
 * docblock and App\Filament\Concerns\HasPageDocs for how this is used.
 */
#[Fillable(['page_key', 'section_key', 'title', 'content', 'sort_order', 'updated_by'])]
class PageDoc extends Model
{
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

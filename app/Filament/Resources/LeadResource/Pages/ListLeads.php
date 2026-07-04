<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Resources\LeadResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    /**
     * Custom view (otherwise identical to Filament's default ListRecords
     * view — see list-leads.blade.php) so we can add a tiny Alpine hook
     * that opens the create modal automatically when this page is reached
     * via the "Додати заявку" sidebar shortcut (?create=1). Doing this in
     * PHP's mount() doesn't work reliably here: the sidebar item is a
     * plain <a href>, which causes a full (non-Livewire) page load, and
     * server-side mountAction() calls made before Livewire/Alpine have
     * hydrated on the client don't result in the modal actually opening.
     * Triggering it client-side after Livewire is ready sidesteps that.
     */
    protected string $view = 'filament.resources.lead-resource.pages.list-leads';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Додати заявку')
                ->modalHeading('Нова заявка')
                ->modalWidth('2xl')
                ->mutateFormDataUsing(function (array $data): array {
                    // Whoever is creating the lead is the manager handling
                    // it by default — not exposed as a visible field, just
                    // recorded for future per-manager funnel analytics.
                    $data['manager_id'] ??= auth()->id();

                    return $data;
                }),
        ];
    }
}

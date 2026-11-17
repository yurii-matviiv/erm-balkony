<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Concerns\HasDateRangeBar;
use App\Filament\Resources\LeadResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListLeads extends ListRecords
{
    use HasDateRangeBar;

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
            // «i»: full-screen doc on stages/steps for the administrator (system view; a manager version comes later).
            \Filament\Actions\Action::make('stagesDoc')
                ->iconButton()
                ->icon('heroicon-o-information-circle')
                ->tooltip('Етапи, кроки та автоматика (для адміністратора)')
                ->visible(fn (): bool => in_array(auth()->user()?->getActiveRoleName(), ['Адмін', 'super_admin'], true))
                ->modalHeading('Етапи, кроки та автоматика заявок і замовлень')
                ->modalWidth(\Filament\Support\Enums\Width::Screen)
                ->modalContent(fn () => view('filament.resources.lead-resource.stages-doc-admin', [
                    'leadStages' => \App\Models\Lead::stageOptions(),
                    'orderStages' => \App\Models\Order::stageOptions(),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Закрити'),

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

    /** Page-level date bar filters by lead creation date. */
    protected function getTableQuery(): Builder|\Illuminate\Database\Eloquent\Relations\Relation|null
    {
        return $this->applyDateRange(parent::getTableQuery(), 'leads.created_at');
    }
}

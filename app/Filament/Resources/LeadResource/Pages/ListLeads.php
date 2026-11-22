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

    /** «Прийняти» з кнопки в списку заявок. */
    public function acceptLead(int $id): void
    {
        $record = \App\Models\Lead::query()->find($id);

        if ($record && LeadResource::hiddenUntilAccepted($record)) {
            LeadResource::acceptAction($record, $this);
        }
    }

    /**
     * Заголовок сторінки. Для адміністратора поруч дрібним текстом — дата й час найновішої заявки в базі
     * (щоб знати, яка копія бази зараз працює); у тому ж рядку, макет не змінюється. Менеджерам — звичайний заголовок.
     */
    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $heading = parent::getHeading();

        if (! in_array(auth()->user()?->getActiveRoleName(), ['Адмін', 'super_admin'], true)) {
            return $heading;
        }

        try {
            $newest = \Illuminate\Support\Facades\DB::table('leads')->max('created_at');
        } catch (\Throwable) {
            return $heading;
        }

        if (! $newest) {
            return $heading;
        }

        $date = \Illuminate\Support\Carbon::parse($newest);
        $weekday = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Нд'][$date->dayOfWeekIso - 1];
        $title = $heading instanceof \Illuminate\Contracts\Support\Htmlable ? $heading->toHtml() : e($heading);

        return new \Illuminate\Support\HtmlString(
            $title
            .' <span style="font-size:.7rem;font-weight:400;opacity:.65;margin-left:.75rem;white-space:nowrap;vertical-align:middle">'
            .'Заявка в базі від: '.e($weekday.' '.$date->format('d.m').' -- '.$date->format('H:i'))
            .'</span>'
        );
    }

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

            // Нову заявку додаємо ЛИШЕ з бокового меню («Додати заявку» → ?create=1 → mountAction('create')).
            // Кнопку на сторінці ховаємо CSS-класом, а не ->hidden(): прихована дія не відкривається з меню.
            CreateAction::make()
                ->extraAttributes(['class' => 'hidden'])
                ->label('Додати заявку')
                ->modalHeading('Нова заявка')
                ->modalWidth('2xl')
                ->createAnother(false)
                ->mutateFormDataUsing(function (array $data): array {
                    // Whoever is creating the lead is the manager handling
                    // it by default — not exposed as a visible field, just
                    // recorded for future per-manager funnel analytics.
                    $data['manager_id'] ??= auth()->id();

                    // «Тип контакту» належить клієнту, а не заявці: записуємо його клієнту й прибираємо з даних заявки.
                    $callerType = \Illuminate\Support\Arr::pull($data, 'client_caller_type');

                    if ($callerType && ! empty($data['client_id'])) {
                        \App\Models\Client::whereKey($data['client_id'])->update(['caller_type' => $callerType]);
                    }

                    return $data;
                })
                // Менеджер створив заявку сам = він її вже прийняв: етап «Прийнято», а не «Новий».
                // «Новий» лишається лише для заявок, яких ще ніхто не прийняв (сайт, дзвінок). Не кваліфікація для реклами.
                ->after(function (\Illuminate\Database\Eloquent\Model $record): void {
                    \App\Services\Leads\ProcessSteps::reach($record, 'assigned', 'created', auth()->id(), 'Менеджер створив заявку сам');
                }),
        ];
    }

    /** Page-level date bar filters by lead creation date. */
    protected function getTableQuery(): Builder|\Illuminate\Database\Eloquent\Relations\Relation|null
    {
        return $this->applyDateRange(parent::getTableQuery(), 'leads.created_at');
    }
}

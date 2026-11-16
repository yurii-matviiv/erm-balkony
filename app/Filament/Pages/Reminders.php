<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RequiresViewPermission;
use App\Filament\Resources\LeadResource;
use App\Models\Lead;
use App\Models\ManagerReminder;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Нагадування" — a tabbed page; each tab is one TYPE of reminder. Only the
 * "Заявки" tab (reminders attached to leads) exists so far; it is the default
 * and the first tab. More types are added by extending getTabs() and the
 * switch in tabQuery().
 *
 * Everyone sees ONLY their own reminders (user_id = current user) — no manager
 * switcher, by explicit decision, for every role including admin/heads.
 * Order: not-done first, nearest reminder date at the top.
 */
class Reminders extends Page implements HasTable
{
    use InteractsWithTable;
    use RequiresViewPermission;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Нагадування';

    protected static ?string $title = 'Нагадування';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.reminders';

    protected Width|string|null $maxContentWidth = Width::Full;

    public string $activeTab = 'leads';

    /** @return array<string, string> tab key => label */
    public function getTabs(): array
    {
        return [
            'leads' => 'Заявки',
        ];
    }

    public function setTab(string $tab): void
    {
        if (! array_key_exists($tab, $this->getTabs())) {
            return;
        }

        $this->activeTab = $tab;
        $this->resetPage();
    }

    /** Open (not done) reminders of the current user for a tab — shown as a count on the tab. */
    public function openCount(string $tab): int
    {
        return $this->tabQuery($tab)->where('is_completed', false)->count();
    }

    private function tabQuery(string $tab): Builder
    {
        $query = ManagerReminder::query()->where('user_id', auth()->id());

        return match ($tab) {
            default => $query->whereNotNull('lead_id'), // 'leads'
        };
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->tabQuery($this->activeTab)->with('lead.client'))
            // Param MUST be named $query: Filament injects the table query by name; any other name gets an empty model-less Builder.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->reorder()->orderBy('is_completed')->orderBy('reminder_datetime'))
            ->striped()
            ->paginated([25, 50, 100])
            ->recordUrl(fn (ManagerReminder $record): ?string => $record->lead_id
                ? LeadResource::getUrl('edit', ['record' => $record->lead_id])
                : null)
            ->columns([
                TextColumn::make('reminder_datetime')
                    ->label('Коли нагадати')
                    ->formatStateUsing(fn ($state): string => $state->locale('uk')->isoFormat('dd DD.MM.YYYY HH:mm'))
                    ->color(fn (ManagerReminder $record): ?string => ! $record->is_completed && $record->reminder_datetime->isPast() ? 'danger' : null),

                TextColumn::make('lead.client.full_name')
                    ->label('Клієнт')
                    ->description(fn (ManagerReminder $record): ?string => $record->lead?->client?->phone)
                    ->placeholder('—'),

                TextColumn::make('reminder_text')
                    ->label('Текст нагадування')
                    ->wrap()
                    ->limit(160),

                TextColumn::make('reminder_type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ManagerReminder::typeOptions()[$state] ?? '—'),

                ToggleColumn::make('is_completed')
                    ->label('Виконано'),
            ])
            ->emptyStateHeading('Немає нагадувань');
    }
}

<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use App\Filament\Resources\LeadResource;
use App\Models\Lead;
use App\Support\RolePanels;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * «Заявки» tab of the client card: all leads of this client. Read-only; a row opens the lead.
 * A manager sees only his own leads of this client.
 */
class LeadsRelationManager extends RelationManager
{
    protected static string $relationship = 'leads';

    protected static ?string $title = 'Заявки';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-inbox-arrow-down';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = static::scopeToUser($ownerRecord->leads())->count();

        return $count > 0 ? (string) $count : null;
    }

    private static function scopeToUser($query)
    {
        $user = auth()->user();

        if ($user && RolePanels::idForUser($user) === RolePanels::MANAGER) {
            $query->where('leads.manager_id', $user->id);
        }

        return $query;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => static::scopeToUser($query->with('manager')))
            ->recordUrl(fn (Lead $record): string => LeadResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('id')->label('Заявка №')->wrapHeader()->sortable(),
                TextColumn::make('created_at')->label('Створено')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('stage')
                    ->label('Статус')
                    ->state(fn (Lead $record): string => match ($record->status) {
                        'won' => 'Угода укладена',
                        'lost' => 'Втрачено',
                        default => Lead::stageOptions()[$record->stage] ?? '—',
                    })
                    ->filledBadge(fn ($state, Lead $record): string => match ($record->status) {
                        'won' => 'success',
                        'lost' => 'danger',
                        default => 'info',
                    }),
                TextColumn::make('source')->label('Джерело')->placeholder('—'),
                TextColumn::make('application_type')->label('Тип заявки')->wrapHeader()->placeholder('—'),
                TextColumn::make('manager.name')
                    ->label('Менеджер')
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => $state ? (explode(' ', trim($state))[0] ?: $state) : '')
                    ->visible(fn (): bool => in_array(auth()->user()?->getActiveRoleName(), ['Адмін', 'Керівник відділу продажу', 'Керівник компанії', 'super_admin'], true)),
                TextColumn::make('comment')
                    ->label('Коментар')
                    ->limit(60)
                    ->tooltip(fn (Lead $record): ?string => $record->comment)
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('У клієнта ще немає заявок')
            ->paginated([20, 100])
            ->defaultPaginationPageOption(20);
    }
}

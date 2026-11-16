<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RequiresViewPermission;
use App\Filament\Resources\LeadResource;
use App\Models\Lead;
use App\Models\ManagerReminder;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
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

    /** Gear at the top right: notification settings (enable + test) in a modal, for every role. */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('notificationSettings')
                ->iconButton()
                ->icon('heroicon-o-cog-6-tooth')
                ->tooltip('Налаштування сповіщень')
                // Red mark on the gear while this user has no subscribed browser at all (server-side check;
                // the banner on the page is exact for the CURRENT browser).
                ->badge(fn (): ?string => \App\Models\PushSubscription::where('user_id', auth()->id())->exists() ? null : '!')
                ->badgeColor('danger')
                ->modalHeading('Налаштування сповіщень')
                ->modalContent(view('filament.pages.reminders-push-settings'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Закрити'),
        ];
    }

    /** Sends a test push to the current user's own subscribed browsers (checks the whole chain without creating a reminder). */
    public function sendTestPush(): void
    {
        $result = app(\App\Services\WebPushSender::class)->sendToUser(
            auth()->user(),
            'Тестове сповіщення',
            'Якщо ви це бачите — push-сповіщення працюють.',
            url()->current(),
        );

        if ($result['subscriptions'] === 0) {
            Notification::make()->warning()->title('Немає підписаних браузерів')
                ->body('Спершу натисни «Дозволити сповіщення» на цій сторінці в потрібному браузері.')->send();

            return;
        }

        if ($result['failed'] > 0) {
            Notification::make()->danger()->title("Надіслано: {$result['sent']}, помилок: {$result['failed']}")
                ->body(implode('; ', array_unique($result['errors'])))->persistent()->send();

            return;
        }

        Notification::make()->success()->title("Тестове сповіщення надіслано ({$result['sent']})")->send();
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
            ->recordActions([
                // Pencil at the end of the row: edit text/date/type in a modal.
                // Queries are already limited to the current user's reminders.
                Action::make('editReminder')
                    ->iconButton()
                    ->icon('heroicon-o-pencil-square')
                    ->tooltip('Редагувати')
                    ->modalHeading('Редагувати нагадування')
                    ->modalSubmitActionLabel('Зберегти')
                    ->modalCancelActionLabel('Скасувати')
                    ->fillForm(fn (ManagerReminder $record): array => $record->only(['reminder_text', 'reminder_datetime', 'reminder_type']))
                    ->schema([
                        Textarea::make('reminder_text')->label('Текст нагадування')->required()->rows(3),
                        DateTimePicker::make('reminder_datetime')->label('Дата і час нагадування')->seconds(false)->required(),
                        Select::make('reminder_type')->label('Тип нагадування')->options(ManagerReminder::typeOptions())->required(),
                    ])
                    ->action(fn (array $data, ManagerReminder $record) => $record->update($data)),
            ])
            ->emptyStateHeading('Немає нагадувань');
    }
}

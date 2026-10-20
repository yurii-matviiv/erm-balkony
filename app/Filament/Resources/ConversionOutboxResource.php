<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConversionOutboxResource\Pages\ListConversionOutbox;
use App\Models\ConversionOutbox;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Black box of the Google Ads offline conversions pipeline: what is queued, what
 * waits in the buffer, what was sent, what failed and why. Read-only list (no
 * create/edit): rows are produced by ConversionQueue; the only manual actions
 * are "cancel" (don't send) and "retry" (failed -> pending).
 */
class ConversionOutboxResource extends Resource
{
    protected static ?string $model = ConversionOutbox::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationLabel = 'Конверсії Google Ads';

    protected static string|\UnitEnum|null $navigationGroup = 'Інтеграції';

    protected static ?int $navigationSort = 14;

    protected static ?string $modelLabel = 'конверсія';

    protected static ?string $pluralModelLabel = 'конверсії Google Ads';

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            ConversionOutbox::STATUS_PENDING => 'Очікує (буфер)',
            ConversionOutbox::STATUS_VALIDATED => 'Перевірено (не надіслано)',
            ConversionOutbox::STATUS_SENT => 'Надіслано',
            ConversionOutbox::STATUS_CONFIRMED => 'Підтверджено Google',
            ConversionOutbox::STATUS_FAILED => 'Помилка',
            ConversionOutbox::STATUS_CANCELLED => 'Скасовано',
            ConversionOutbox::STATUS_SKIPPED => 'Пропущено',
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),

                TextColumn::make('kind')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'sale' ? 'Продаж' : 'Кваліфікована заявка')
                    ->color(fn (string $state): string => $state === 'sale' ? 'success' : 'info'),

                TextColumn::make('lead_id')
                    ->label('Заявка')
                    ->formatStateUsing(fn ($state): string => '#'.$state)
                    ->url(fn (ConversionOutbox $record): ?string => class_exists(LeadResource::class)
                        ? LeadResource::getUrl('edit', ['record' => $record->lead_id])
                        : null)
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        ConversionOutbox::STATUS_CONFIRMED => 'success',
                        ConversionOutbox::STATUS_SENT, ConversionOutbox::STATUS_VALIDATED => 'info',
                        ConversionOutbox::STATUS_PENDING => 'warning',
                        ConversionOutbox::STATUS_FAILED => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('skip_reason')->label('Причина')->placeholder('—')->toggleable(),

                TextColumn::make('value')->label('Вага, грн')->numeric(0)->sortable(),

                TextColumn::make('send_after')->label('Відправити після')->dateTime('d.m.Y H:i')->sortable()->placeholder('—'),

                TextColumn::make('event_time')->label('Час події')->dateTime('d.m.Y H:i')->placeholder('—')->toggleable(),

                TextColumn::make('attempts')->label('Спроби')->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('google_request_id')->label('Request ID')->placeholder('—')->copyable()->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('last_error')->label('Помилка')->limit(60)->tooltip(fn (ConversionOutbox $record): ?string => $record->last_error)->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Статус')->options(static::statusLabels()),
                SelectFilter::make('kind')->label('Тип')->options([
                    ConversionOutbox::KIND_QUALIFIED => 'Кваліфікована заявка',
                    ConversionOutbox::KIND_SALE => 'Продаж',
                ]),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->label('Не відправляти')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (ConversionOutbox $record): bool => in_array($record->status, [ConversionOutbox::STATUS_PENDING, ConversionOutbox::STATUS_VALIDATED], true))
                    ->action(function (ConversionOutbox $record): void {
                        $record->update(['status' => ConversionOutbox::STATUS_CANCELLED, 'skip_reason' => 'manual']);
                        Notification::make()->title('Скасовано')->success()->send();
                    }),

                Action::make('retry')
                    ->label('Повторити')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (ConversionOutbox $record): bool => $record->status === ConversionOutbox::STATUS_FAILED)
                    ->requiresConfirmation()
                    ->action(function (ConversionOutbox $record): void {
                        $record->update([
                            'status' => ConversionOutbox::STATUS_PENDING,
                            'attempts' => 0,
                            'last_error' => null,
                            'send_after' => now(),
                        ]);
                        Notification::make()->title('Повернуто в чергу')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConversionOutbox::route('/'),
        ];
    }
}

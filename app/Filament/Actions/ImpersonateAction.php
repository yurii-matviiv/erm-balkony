<?php

namespace App\Filament\Actions;

use App\Models\User;
use App\Services\Impersonation\Impersonator;
use Illuminate\Support\Facades\URL;
use Filament\Actions\Action;

/** «Увійти як» — для таблиці користувачів і для картки користувача. */
class ImpersonateAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'impersonate';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Увійти як користувач')
            ->icon('heroicon-o-arrow-right-end-on-rectangle')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(fn (User $record): string => 'Увійти як ' . $record->name . '?')
            ->modalDescription('Ви побачите систему очима цього користувача. Повернутись у свій акаунт можна кнопкою внизу екрана.')
            ->modalSubmitActionLabel('Увійти')
            ->visible(fn (User $record): bool => Impersonator::canImpersonate(auth()->user(), $record))
            // Сам вхід робить звичайний (не Livewire) запит за короткоживучим підписаним
            // посиланням: зміна користувача посеред Livewire-запиту ламала сесію.
            ->action(function (User $record, $livewire): void {
                abort_unless(Impersonator::canImpersonate(auth()->user(), $record), 403);

                $livewire->redirect(URL::temporarySignedRoute(
                    'impersonate.start',
                    now()->addMinute(),
                    ['user' => $record->id, 'actor' => auth()->id()],
                ));
            });
    }
}

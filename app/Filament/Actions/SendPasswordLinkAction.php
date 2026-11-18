<?php

namespace App\Filament\Actions;

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Support\RolePanels;
use Filament\Actions\Action;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

/**
 * «Надіслати посилання для встановлення пароля» — лист з технічної пошти
 * (MAIL_* у .env) на email користувача. Пароль у листі НЕ передається:
 * лише одноразове посилання, за яким людина сама задає пароль.
 */
class SendPasswordLinkAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'sendPasswordLink';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Надіслати посилання для встановлення пароля')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Надіслати посилання?')
            ->modalDescription(fn (User $record): string => 'На адресу ' . $record->email . ' прийде лист із посиланням, за яким користувач сам задасть пароль. Посилання одноразове й діє обмежений час.')
            ->modalSubmitActionLabel('Надіслати')
            ->visible(fn (User $record): bool => UserResource::canEdit($record) && filled($record->email))
            ->action(function (User $record): void {
                try {
                    $panel = Filament::getPanel(RolePanels::idForUser($record));
                    $token = Password::broker($panel->getAuthPasswordBroker())->createToken($record);

                    $notification = app(ResetPasswordNotification::class, ['token' => $token]);
                    $notification->url = $panel->getResetPasswordUrl($token, $record);
                    $record->notify($notification);

                    Log::info('password-link.sent', ['by' => auth()->id(), 'user_id' => $record->id]);

                    Notification::make()->success()->title('Лист надіслано')->body($record->email)->send();
                } catch (\Throwable $e) {
                    report($e);

                    Notification::make()->danger()->title('Не вдалося надіслати лист')->body($e->getMessage())->send();
                }
            });
    }
}

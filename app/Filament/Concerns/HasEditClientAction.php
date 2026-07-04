<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

/**
 * A pencil-icon "Редагувати клієнта" action that opens the Client's own
 * editable fields in a modal — used as a Section header action on any
 * edit page that displays a read-only Client summary (EditLead, EditOrder)
 * but shouldn't let that page's `form()` edit the Client's own fields
 * directly (a Client can be attached to several Leads/Orders, so editing
 * it here changes THAT PERSON everywhere, not just the record being
 * viewed — see EditLead's class docblock for the original reasoning).
 *
 * Deliberately NOT gated by role: per explicit request, a plain
 * "Менеджер" — who has no menu access to the general Клієнти list/module
 * — should still be able to fix a client's own details (phone, address,
 * ...) from right here, via the modal, without needing that broader
 * access. This is intentionally different from canReassignOwnership()-
 * style checks elsewhere in EditOrder, which DO restrict by role — editing
 * a client's contact info isn't the same kind of decision as reassigning
 * who's responsible for an order.
 *
 * Assumes the consuming page's record has a `client()` relation (true for
 * both Lead and Order).
 */
trait HasEditClientAction
{
    public function editClientAction(): Action
    {
        // Resolved EAGERLY (a plain array), not as a ->fillForm(Closure) —
        // deliberately, after the Closure form silently filled the modal
        // with blanks (only each field's own ->default() showed). This
        // method already runs fresh every time the page's form() schema
        // is rebuilt (including right before the action mounts), so the
        // record is just as current either way — this just avoids
        // whatever timing/evaluation quirk made the deferred Closure not
        // see the loaded $client.
        $client = $this->getRecord()->client;

        $clientData = $client?->only([
            'last_name', 'first_name', 'middle_name', 'phone', 'phone2', 'viber', 'email',
            'city', 'street', 'house_number', 'apartment_number', 'floor', 'comment',
        ]) ?? [];

        return Action::make('editClient')
            ->label('Редагувати клієнта')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->modalHeading(
                $client
                    ? new \Illuminate\Support\HtmlString(
                        'Дані клієнта'
                        . ' <span style="font-size:13px;font-weight:400;opacity:.55;margin-left:8px;">#' . $client->id . '</span>'
                    )
                    : 'Дані клієнта'
            )
            ->fillForm($clientData)
            ->schema([
                TextInput::make('last_name')->label('Прізвище')->maxLength(255),
                TextInput::make('first_name')->label("Ім'я")->required()->maxLength(255),
                TextInput::make('middle_name')->label('По батькові')->maxLength(255),
                TextInput::make('phone')->label('Телефон')->tel()->required()->maxLength(30),
                TextInput::make('phone2')->label('Телефон 2')->tel()->maxLength(30),
                TextInput::make('viber')->label('Viber')->maxLength(30),
                TextInput::make('email')->label('Email')->email()->maxLength(255),
                TextInput::make('city')->label('Місто')->default('Київ')->maxLength(255),
                TextInput::make('street')->label('Вулиця')->maxLength(255),
                TextInput::make('house_number')->label('Будинок')->maxLength(20),
                TextInput::make('apartment_number')->label('Квартира')->maxLength(20),
                TextInput::make('floor')->label('Поверх')->maxLength(20),
                Textarea::make('comment')->label('Коментар')->columnSpanFull(),
            ])
            ->action(function (array $data): void {
                $this->getRecord()->client?->update($data);
            });
    }
}

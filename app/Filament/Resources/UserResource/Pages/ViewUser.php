<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Actions\ImpersonateAction;
use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * Картка користувача. Далі сюди додаватимуться інші пункти (вкладки /
 * relation managers); поки — контактні дані і вхід під користувачем.
 */
class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Картка користувача';
    }

    protected function getHeaderActions(): array
    {
        return [
            ImpersonateAction::make(),
        ];
    }
}

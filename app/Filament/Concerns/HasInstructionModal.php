<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;

/**
 * Standard "Інструкція" button for a page block: a full-screen modal with the
 * block's rules (see docs/INSTRUCTIONS.md for the convention and the text structure).
 *
 * In the page class:
 *
 *     use HasInstructionModal;
 *
 *     public function fooInstructionAction(): Action
 *     {
 *         return $this->instructionAction('fooInstruction', 'Назва блоку — як це працює', 'filament.pages.foo-instruction', [...]);
 *     }
 *
 * In the block's blade (section header slot):
 *
 *     <x-slot name="afterHeader">{{ $this->fooInstructionAction }}</x-slot>
 *
 * Action name must equal the method name without the "Action" suffix.
 */
trait HasInstructionModal
{
    /** @param array<string,mixed> $data variables for the instruction view */
    protected function instructionAction(string $name, string $heading, string $view, array $data = []): Action
    {
        return Action::make($name)
            ->label('Інструкція')
            ->icon('heroicon-o-question-mark-circle')
            ->color('gray')
            ->outlined()
            ->size('sm')
            ->modalHeading($heading)
            ->modalWidth(Width::Screen)
            ->modalContent(fn () => view($view, $data))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Закрити');
    }
}

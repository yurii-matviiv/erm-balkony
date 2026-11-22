<?php

namespace App\Filament\Concerns;

/**
 * Запам'ятовує в сесії користувача стан сторінки (вкладка, вибраний знімок, галочки), щоб після
 * перезавантаження повернутись туди, де працювали. Той самий механізм, що й бар дат (HasDateRangeBar): сесія.
 *
 * Сторінка визначає persistedState(): властивість => допустимі значення (масив) або null (будь-яке скалярне).
 * Відновлення: викликати restorePageState() наприкінці mount(). Збереження — автоматично після кожного запиту (dehydrate).
 */
trait PersistsPageState
{
    /** @return array<string, list<scalar>|null> */
    abstract protected function persistedState(): array;

    protected function pageStateSessionKey(): string
    {
        return 'page_state.'.static::class;
    }

    protected function restorePageState(): void
    {
        $saved = (array) session($this->pageStateSessionKey(), []);

        foreach ($this->persistedState() as $property => $allowed) {
            if (! array_key_exists($property, $saved)) {
                continue;
            }
            $value = $saved[$property];
            if ($allowed !== null && ! in_array($value, $allowed, true)) {
                continue; // значення вже недоступне (напр. видалений знімок) — лишаємо типове
            }
            $this->{$property} = $value;
        }
    }

    public function dehydratePersistsPageState(): void
    {
        $state = [];
        foreach (array_keys($this->persistedState()) as $property) {
            $state[$property] = $this->{$property};
        }
        session([$this->pageStateSessionKey() => $state]);
    }
}

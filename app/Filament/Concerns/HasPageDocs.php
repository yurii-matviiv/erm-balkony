<?php

namespace App\Filament\Concerns;

use App\Models\PageDoc;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;

/**
 * Lets a Filament page embed one or more "Документація сторінки" blocks
 * (see PageDoc / create_page_docs_table migration) — an in-app, editable
 * explanation area that's a single source of truth for how a page or a
 * tricky field should be used, instead of that knowledge living only in
 * Slack threads or a manager's memory.
 *
 * Usage in a page class — render it via the page's getFooter() hook
 * (Filament's base Page renders this automatically, no custom $view
 * needed) or an @include if the page already has a custom view:
 *
 *     public function getFooter(): ?\Illuminate\Contracts\View\View
 *     {
 *         return $this->renderPageDoc('leads', 'statuses', 'Статуси заявок');
 *     }
 *
 * renderPageDoc() deliberately resolves the doc/permission/action data
 * itself and passes them into the Blade partial as plain variables,
 * rather than having the partial call back into `$this`. A View object
 * returned from getFooter() is rendered by Filament's page layout as its
 * own nested view, NOT inside this page's own compiled template — `$this`
 * would not reliably resolve to this Livewire component in there.
 */
trait HasPageDocs
{
    public function renderPageDoc(string $pageKey, string $sectionKey, string $defaultTitle): \Illuminate\Contracts\View\View
    {
        return view('filament.components.page-doc', [
            'doc' => PageDoc::where('page_key', $pageKey)->where('section_key', $sectionKey)->first(),
            'canManage' => auth()->user()?->can('manage-page-docs') ?? false,
            'defaultTitle' => $defaultTitle,
            'action' => $this->pageDocAction($pageKey, $sectionKey, $defaultTitle),
        ]);
    }

    protected function pageDocAction(string $pageKey, string $sectionKey, string $defaultTitle): Action
    {
        return Action::make('editPageDoc_'.$pageKey.'_'.$sectionKey)
            ->label('Редагувати довідку')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->size('sm')
            ->visible(fn (): bool => auth()->user()?->can('manage-page-docs') ?? false)
            ->modalHeading($defaultTitle)
            ->modalWidth('2xl')
            ->fillForm(function () use ($pageKey, $sectionKey, $defaultTitle): array {
                $doc = PageDoc::where('page_key', $pageKey)->where('section_key', $sectionKey)->first();

                return [
                    'title' => $doc?->title ?? $defaultTitle,
                    'content' => $doc?->content,
                ];
            })
            ->schema([
                TextInput::make('title')
                    ->label('Заголовок')
                    ->required()
                    ->maxLength(255),

                RichEditor::make('content')
                    ->label('Текст довідки')
                    ->required()
                    ->toolbarButtons([
                        'bold', 'italic', 'underline', 'h2', 'h3',
                        'bulletList', 'orderedList', 'blockquote', 'link', 'undo', 'redo',
                    ]),
            ])
            ->action(function (array $data) use ($pageKey, $sectionKey): void {
                PageDoc::updateOrCreate(
                    ['page_key' => $pageKey, 'section_key' => $sectionKey],
                    [
                        'title' => $data['title'],
                        'content' => $data['content'],
                        'updated_by' => auth()->id(),
                    ],
                );
            });
    }

    public function getPageDoc(string $pageKey, string $sectionKey): ?PageDoc
    {
        return PageDoc::where('page_key', $pageKey)->where('section_key', $sectionKey)->first();
    }

    public function canManagePageDocs(): bool
    {
        return auth()->user()?->can('manage-page-docs') ?? false;
    }
}

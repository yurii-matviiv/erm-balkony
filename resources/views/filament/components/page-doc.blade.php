{{--
    Reusable "Документація сторінки" block — see
    App\Filament\Concerns\HasPageDocs::renderPageDoc(). All data is passed
    in explicitly (no calls back into `$this`) so this partial renders
    correctly regardless of whether it's included inline in a page's own
    view or returned as its own View object from getFooter()/getHeader().

    Expected variables: $doc (?PageDoc), $canManage (bool),
    $defaultTitle (string), $action (Filament\Actions\Action).
--}}
@if ($doc?->content || $canManage)
    <x-filament::section
        :heading="$doc->title ?? $defaultTitle"
        icon="heroicon-o-information-circle"
        collapsible
    >
        @if ($canManage)
            <x-slot name="headerEnd">
                {{ $action }}
            </x-slot>
        @endif

        @if ($doc?->content)
            <div class="prose dark:prose-invert max-w-none text-sm">
                {!! \Filament\Forms\Components\RichEditor\RichContentRenderer::make($doc->content)->toHtml() !!}
            </div>
        @else
            <p class="text-sm text-gray-400">Довідки ще немає. Натисніть "Редагувати довідку", щоб додати.</p>
        @endif
    </x-filament::section>
@endif

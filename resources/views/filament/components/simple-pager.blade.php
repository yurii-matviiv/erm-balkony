{{--
    Minimal, plain-text pagination control — NOT Laravel's default
    ->links() view. That default view renders raw <svg> prev/next icons
    sized purely by Tailwind utility classes; Filament's admin panel only
    ships its OWN scoped CSS bundle, which doesn't include those classes,
    so the icons rendered completely unstyled — at native SVG size, i.e.
    enormous, making the whole page look broken/frozen. Plain text +
    Filament's own button component sidesteps the problem entirely, since
    those classes ARE part of Filament's bundle (used everywhere else in
    this admin already).

    Expects: $paginator (any Illuminate\Contracts\Pagination\Paginator).
--}}
<div class="flex items-center justify-between gap-3 text-sm">
    @if ($paginator->previousPageUrl())
        <a href="{{ $paginator->previousPageUrl() }}">
            <x-filament::button color="gray" size="sm">« Попередня</x-filament::button>
        </a>
    @else
        <x-filament::button color="gray" size="sm" disabled>« Попередня</x-filament::button>
    @endif

    <span class="text-gray-500 dark:text-gray-400 whitespace-nowrap">
        Сторінка {{ $paginator->currentPage() }} з {{ $paginator->lastPage() }}
        ({{ number_format($paginator->total()) }} записів)
    </span>

    @if ($paginator->nextPageUrl())
        <a href="{{ $paginator->nextPageUrl() }}">
            <x-filament::button color="gray" size="sm">Наступна »</x-filament::button>
        </a>
    @else
        <x-filament::button color="gray" size="sm" disabled>Наступна »</x-filament::button>
    @endif
</div>

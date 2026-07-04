{{--
    Read-only file list for an Order — imported from legacy CRM's Google Drive links.
    Variable: $files  (Collection<OrderFile>)
    Files are grouped by type for cleaner visual scanning.
--}}

@use('App\Models\OrderFile')

@if ($files->isEmpty())
    <p class="text-sm text-gray-400 italic py-2">Файлів не знайдено. Після синхронізації зі старої БД тут з'являться посилання на Google Drive.</p>
@else

@php
    $grouped = $files->groupBy('type');
    $typeOrder = ['specification', 'supplier_invoice', 'paid_invoice', 'commercial', 'other'];
@endphp

<div class="space-y-4">
    @foreach ($typeOrder as $typeKey)
        @if ($grouped->has($typeKey))
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">
                    {{ OrderFile::typeLabel($typeKey) }}
                </div>
                <div class="space-y-1">
                    @foreach ($grouped[$typeKey] as $file)
                        <div class="flex items-center gap-3 rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 px-3 py-2">
                            {{-- File type icon --}}
                            <span class="text-base">{{ OrderFile::typeIcon($typeKey) }}</span>

                            {{-- Link --}}
                            <a href="{{ $file->url }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="flex-1 text-sm text-primary-600 dark:text-primary-400 hover:underline truncate"
                               title="{{ $file->file_name }}">
                                {{ $file->file_name }}
                            </a>

                            {{-- Legacy badge --}}
                            @if ($file->isLegacy())
                                <span class="shrink-0 rounded-full bg-amber-100 dark:bg-amber-900/30 px-2 py-0.5 text-xs text-amber-700 dark:text-amber-400">
                                    Google Drive
                                </span>
                            @endif

                            {{-- External link icon --}}
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach

    <p class="text-xs text-gray-400 mt-3">
        {{ $files->count() }} {{ $files->count() === 1 ? 'файл' : ($files->count() < 5 ? 'файли' : 'файлів') }} · Посилання ведуть на Google Drive (стара система).
    </p>
</div>
@endif

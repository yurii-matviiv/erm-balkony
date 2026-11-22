@php
    /** @var \App\Models\Client|null $client */
    $client = $getRecord();
    $initials = mb_strtoupper(mb_substr(trim((string) ($client->last_name ?: $client->first_name)), 0, 1).mb_substr(trim((string) ($client->last_name ? $client->first_name : $client->middle_name)), 0, 1));
    $fullName = $client->full_name ?: '—';
    $digits = preg_replace('/\D/', '', (string) $client->phone);
    $digits2 = preg_replace('/\D/', '', (string) $client->phone2);
    $digitsViber = preg_replace('/\D/', '', (string) $client->viber);
    $canEdit = \App\Filament\Resources\ClientResource::canEdit($client);
    $editUrl = \App\Filament\Resources\ClientResource::getUrl('edit', ['record' => $client]);
    $callerType = \App\Models\Client::callerTypeOptions()[(string) $client->caller_type] ?? null;
    $address = $client->full_address;
    $showManager = \App\Filament\Resources\OrderResource::canSeeManagers();
    $label = 'display:block;font-size:11px;letter-spacing:.03em;text-transform:uppercase;color:var(--gray-500);margin-bottom:2px;';
    $row = 'display:flex;gap:12px;align-items:flex-start;padding:10px 0;border-bottom:1px solid var(--gray-100);';
    $ico = 'flex:none;width:20px;height:20px;margin-top:14px;color:var(--primary-600);';
@endphp
<div style="max-width:520px;">
    <div style="display:flex;gap:14px;align-items:center;padding-bottom:12px;border-bottom:1px solid var(--gray-200);">
        <div style="flex:none;width:48px;height:48px;display:flex;align-items:center;justify-content:center;background:var(--primary-100);color:var(--primary-700);font-weight:600;font-size:16px;">{{ $initials ?: '—' }}</div>
        <div style="min-width:0;">
            <span class="font-semibold text-gray-950 dark:text-white" style="font-size:16px;">{{ $fullName }}</span>
            <div class="text-xs text-gray-500">клієнт #{{ $client->id }}</div>
        </div>
        @if ($canEdit)
            <a href="{{ $editUrl }}" title="Редагувати клієнта" class="text-primary-600 hover:bg-gray-50" style="margin-left:auto;flex:none;padding:6px;">
                <x-filament::icon icon="heroicon-o-pencil-square" style="width:20px;height:20px;" />
            </a>
        @endif
    </div>

    <div style="{{ $row }}">
        <x-filament::icon icon="heroicon-o-tag" style="{{ $ico }}" />
        <div><span style="{{ $label }}">Тип контакту</span>
            <span class="text-sm text-gray-950 dark:text-white">{{ $callerType ?? '—' }}</span>
        </div>
    </div>

    <div style="{{ $row }}">
        <x-filament::icon icon="heroicon-o-phone" style="{{ $ico }}" />
        <div><span style="{{ $label }}">Телефон</span>
            @if ($digits !== '')<a class="text-sm font-medium text-primary-600 hover:underline" href="tel:+{{ $digits }}">{{ $client->phone }}</a>@else<span class="text-sm text-gray-500">—</span>@endif
        </div>
    </div>

    <div style="{{ $row }}">
        <x-filament::icon icon="heroicon-o-device-phone-mobile" style="{{ $ico }}" />
        <div><span style="{{ $label }}">Телефон (додатковий)</span>
            @if ($digits2 !== '')<a class="text-sm text-primary-600 hover:underline" href="tel:+{{ $digits2 }}">{{ $client->phone2 }}</a>@else<span class="text-sm text-gray-500">—</span>@endif
        </div>
    </div>

    <div style="{{ $row }}">
        <x-filament::icon icon="heroicon-o-chat-bubble-left-right" style="{{ $ico }}" />
        <div><span style="{{ $label }}">Viber</span>
            @if ($digitsViber !== '')<a class="text-sm text-primary-600 hover:underline" href="viber://chat?number=%2B{{ $digitsViber }}">{{ $client->viber }}</a>@elseif (filled($client->viber))<span class="text-sm text-gray-950 dark:text-white">{{ $client->viber }}</span>@else<span class="text-sm text-gray-500">—</span>@endif
        </div>
    </div>

    <div style="{{ $row }}">
        <x-filament::icon icon="heroicon-o-envelope" style="{{ $ico }}" />
        <div><span style="{{ $label }}">Електронна пошта</span>
            @if ($client->email)<a class="text-sm text-primary-600 hover:underline" href="mailto:{{ $client->email }}">{{ $client->email }}</a>@else<span class="text-sm text-gray-500">—</span>@endif
        </div>
    </div>

    <div style="{{ $row }}">
        <x-filament::icon icon="heroicon-o-map-pin" style="{{ $ico }}" />
        <div><span style="{{ $label }}">Адреса клієнта</span>
            <span class="text-sm text-gray-950 dark:text-white">{{ filled($address) ? $address : '—' }}</span>
        </div>
    </div>

    @if ($showManager)
        <div style="{{ $row }}">
            <x-filament::icon icon="heroicon-o-user" style="{{ $ico }}" />
            <div><span style="{{ $label }}">Менеджер</span>
                <span class="text-sm text-gray-950 dark:text-white">{{ $client->manager?->name ?: '—' }}</span>
            </div>
        </div>
    @endif

    <div style="{{ $row }}border-bottom:0;">
        <x-filament::icon icon="heroicon-o-chat-bubble-bottom-center-text" style="{{ $ico }}" />
        <div style="min-width:0;"><span style="{{ $label }}">Коментар</span>
            <span class="text-sm text-gray-950 dark:text-white" style="white-space:pre-line;overflow-wrap:anywhere;">{{ filled($client->comment) ? $client->comment : '—' }}</span>
        </div>
    </div>
</div>

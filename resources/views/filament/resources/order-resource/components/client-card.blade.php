@php
    /** @var \App\Models\Client|null $client */
    $initials = $client
        ? mb_strtoupper(mb_substr(trim((string) ($client->last_name ?: $client->first_name)), 0, 1).mb_substr(trim((string) ($client->last_name ? $client->first_name : $client->middle_name)), 0, 1))
        : '?';
    $digits = preg_replace('/\D/', '', (string) $client?->phone);
    $digits2 = preg_replace('/\D/', '', (string) $client?->phone2);
    // Binotel history is searched by the national format (0XXXXXXXXX), without the "38" country code.
    $binotelNumber = str_starts_with($digits, '380') ? substr($digits, 2) : $digits;
    $address = $client?->full_address;
    // On the lead card the map points at the object address first (where the work is done).
    $mapQuery = ! empty($objectAddress) ? $objectAddress : $address;
    $label = 'display:block;font-size:11px;letter-spacing:.03em;text-transform:uppercase;color:var(--gray-500);margin-bottom:2px;';
    $row = 'padding:9px 0;border-bottom:1px solid var(--gray-100);';
    $btn = 'display:flex;align-items:center;gap:8px;padding:7px 10px;border:1px solid var(--gray-200);font-size:13px;font-weight:500;';
@endphp
@if (! $client)
    <p class="text-sm text-gray-500">Клієнта не вибрано</p>
@else
    <div style="display:flex;flex-wrap:wrap;gap:24px 56px;align-items:flex-start;justify-content:flex-start;">
        {{-- LEFT: who the client is, as a vertical list --}}
        <div style="flex:0 1 420px;min-width:280px;">
            <div style="display:flex;gap:14px;align-items:center;padding-bottom:12px;border-bottom:1px solid var(--gray-200);">
                <div style="flex:none;width:48px;height:48px;display:flex;align-items:center;justify-content:center;background:var(--primary-100);color:var(--primary-700);font-weight:600;font-size:16px;">{{ $initials }}</div>
                <div style="min-width:0;">
                    @if ($clientUrl)
                        <a href="{{ $clientUrl }}" class="font-semibold text-primary-600 hover:underline" style="font-size:16px;">{{ $client->full_name }}</a>
                    @else
                        <span class="font-semibold text-gray-950 dark:text-white" style="font-size:16px;">{{ $client->full_name }}</span>
                    @endif
                    <div class="text-xs text-gray-500">клієнт #{{ $client->id }}</div>
                </div>
                @if ($clientUrl)
                    <a href="{{ $clientUrl }}" title="Редагувати клієнта" class="text-primary-600 hover:bg-gray-50" style="margin-left:auto;flex:none;padding:6px;">
                        <x-filament::icon icon="heroicon-o-pencil-square" style="width:20px;height:20px;" />
                    </a>
                @endif
            </div>

            <div style="{{ $row }}">
                <span style="{{ $label }}">Телефон</span>
                @if ($digits !== '')
                    <a class="text-sm font-medium text-primary-600 hover:underline" href="tel:+{{ $digits }}">{{ $client->phone }}</a>
                @else <span class="text-sm text-gray-500">—</span> @endif
                @if ($digits2 !== '')
                    <span class="text-gray-400"> · </span><a class="text-sm text-primary-600 hover:underline" href="tel:+{{ $digits2 }}">{{ $client->phone2 }}</a>
                @endif
            </div>
            @if ($client->viber)
                <div style="{{ $row }}"><span style="{{ $label }}">Viber</span><span class="text-sm text-gray-950 dark:text-white">{{ $client->viber }}</span></div>
            @endif
            <div style="{{ $row }}">
                <span style="{{ $label }}">Адреса клієнта</span>
                <span class="text-sm text-gray-950 dark:text-white">{{ $address ?: '—' }}</span>
            </div>
            @if (! empty($showObjectAddress))
                <div style="{{ $row }}">
                    <span style="{{ $label }}">Адреса об'єкта (де виконуються роботи)</span>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span class="text-sm text-gray-950 dark:text-white">{{ $objectAddress ?: '—' }}</span>
                        @if (! empty($autoAddress))
                            <span class="text-xs text-gray-500">автовизначено: {{ $autoAddress }}</span>
                        @endif
                        <div style="margin-left:auto;">{{ $editAddressAction ?? '' }}</div>
                    </div>
                </div>
            @endif
            {{-- Email is being collected: always shown (even empty), right after the physical address. --}}
            <div style="{{ $row }}">
                <span style="{{ $label }}">Електронна адреса</span>
                @if ($client->email)
                    <a class="text-sm text-primary-600 hover:underline" href="mailto:{{ $client->email }}">{{ $client->email }}</a>
                @else
                    <span class="text-sm text-gray-400">не вказано</span>
                @endif
            </div>
            @if ($client->comment)
                <div style="padding:9px 0;"><span style="{{ $label }}">Коментар</span><span class="text-sm text-gray-950 dark:text-white">{{ $client->comment }}</span></div>
            @endif
        </div>

        {{-- RIGHT: quick actions --}}
        <div style="flex:0 1 300px;min-width:240px;display:flex;flex-direction:column;gap:8px;">
            <span style="{{ $label }}">Зв'язатися</span>
            @if ($digits !== '')
                <div style="display:flex;gap:8px;">
                    <a target="_blank" href="viber://chat?number=+{{ $digits }}" title="Viber"><img src="{{ asset('images/social-media-icons/viber.png') }}" alt="Viber" width="34"></a>
                    <a target="_blank" href="https://t.me/+{{ $digits }}" title="Telegram"><img src="{{ asset('images/social-media-icons/telegram.png') }}" alt="Telegram" width="34"></a>
                    <a target="_blank" href="https://wa.me/+{{ $digits }}" title="WhatsApp"><img src="{{ asset('images/social-media-icons/whatsapp.png') }}" alt="WhatsApp" width="34"></a>
                </div>
                <div class="text-xs text-gray-500">перевірити, чи є номер у месенджері</div>
                <a style="{{ $btn }}" class="text-primary-600 hover:bg-gray-50" target="_blank" href="https://my.binotel.ua/?module=history&subject={{ $binotelNumber }}">
                    <x-filament::icon icon="heroicon-o-phone-arrow-up-right" style="width:18px;height:18px;" /> Історія дзвінків у Binotel
                </a>
            @endif
            @if ($mapQuery)
                <a style="{{ $btn }}" class="text-primary-600 hover:bg-gray-50" target="_blank" href="https://www.google.com/maps/search/?api=1&query={{ urlencode($mapQuery) }}">
                    <x-filament::icon icon="heroicon-o-map-pin" style="width:18px;height:18px;" /> Відкрити адресу на мапі
                </a>
            @endif
            @if (! empty($leadUrl))
                <a style="{{ $btn }}" class="text-primary-600 hover:bg-gray-50" href="{{ $leadUrl }}">
                    <x-filament::icon icon="heroicon-o-inbox-arrow-down" style="width:18px;height:18px;" /> Заявка №{{ $leadId }}, з якої створено замовлення
                </a>
            @endif
            @if ($clientUrl)
                <a style="{{ $btn }}" class="text-primary-600 hover:bg-gray-50" href="{{ $clientUrl }}">
                    <x-filament::icon icon="heroicon-o-identification" style="width:18px;height:18px;" /> Повна картка клієнта
                </a>
            @endif
        </div>
    </div>
@endif

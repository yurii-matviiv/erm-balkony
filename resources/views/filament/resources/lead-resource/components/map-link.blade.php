@php
    $address = $record?->site_address ?: $record?->client?->full_address;
@endphp
@if ($address)
    <a class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:underline" target="_blank"
       href="https://www.google.com/maps/search/?api=1&query={{ urlencode($address) }}">
        Відкрити на мапі <img src="{{ asset('images/social-media-icons/google-map.png') }}" alt="Map" width="22">
    </a>
@endif

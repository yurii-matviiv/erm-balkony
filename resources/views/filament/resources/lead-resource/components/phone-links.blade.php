@php
    $phone = $record?->client?->phone;
    $digits = preg_replace('/\D/', '', (string) $phone);
    // Binotel history is searched by the national format (0XXXXXXXXX), without the "38" country code.
    $binotelNumber = str_starts_with($digits, '380') ? substr($digits, 2) : $digits;
@endphp
<div class="record-row" style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,2fr);gap:1rem;align-items:center;">
<span class="text-sm font-medium text-gray-950 dark:text-white">Інформація по номеру</span>
<div class="text-sm" style="display:flex;flex-wrap:wrap;align-items:center;gap:6px 12px;">
    @if ($digits !== '')
        <span class="text-gray-500">відкрити в Binotel</span>
        <a class="font-medium text-primary-600 hover:underline" target="_blank" href="https://my.binotel.ua/?module=history&subject={{ $binotelNumber }}">{{ $binotelNumber }}</a>
        <span class="text-gray-400">|</span>
        <span class="text-gray-500">перевірити месенджери:</span>
        <a target="_blank" href="viber://chat?number=+{{ $digits }}" title="Viber"><img src="{{ asset('images/social-media-icons/viber.png') }}" alt="Viber" width="28"></a>
        <a target="_blank" href="https://t.me/+{{ $digits }}" title="Telegram"><img src="{{ asset('images/social-media-icons/telegram.png') }}" alt="Telegram" width="28"></a>
        <a target="_blank" href="https://wa.me/+{{ $digits }}" title="WhatsApp"><img src="{{ asset('images/social-media-icons/whatsapp.png') }}" alt="WhatsApp" width="28"></a>
    @else
        <span class="text-gray-500">—</span>
    @endif
</div>
</div>

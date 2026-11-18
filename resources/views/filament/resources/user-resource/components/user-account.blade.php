@php
    /** @var \App\Models\User $user */
    $user = $getRecord();
    $label = 'display:block;font-size:11px;letter-spacing:.03em;text-transform:uppercase;color:var(--gray-500);margin-bottom:2px;';
    $row = 'display:flex;gap:12px;align-items:flex-start;padding:10px 0;border-bottom:1px solid var(--gray-100);';
    $ico = 'flex:none;width:20px;height:20px;margin-top:14px;color:var(--primary-600);';
@endphp
<div style="max-width:520px;">
    <div style="{{ $row }}">
        <x-filament::icon icon="heroicon-o-envelope" style="{{ $ico }}" />
        <div><span style="{{ $label }}">Логін (електронна пошта)</span>
            <span class="text-sm text-gray-950 dark:text-white">{{ $user->email ?: '—' }}</span>
        </div>
    </div>
    <div style="{{ $row }}border-bottom:0;">
        <x-filament::icon icon="heroicon-o-lock-closed" style="{{ $ico }}" />
        <div><span style="{{ $label }}">Пароль</span>
            <span class="text-sm text-gray-950 dark:text-white" style="letter-spacing:.15em;">••••••••</span>
            <div class="text-xs text-gray-500" style="margin-top:2px;">Пароль зберігається лише у зашифрованому вигляді, переглянути його неможливо.</div>
        </div>
    </div>
</div>

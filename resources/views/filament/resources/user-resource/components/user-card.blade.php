@php
    /** @var \App\Models\User|null $user */
    $user = $getRecord();
    $initials = mb_strtoupper(mb_substr(trim((string) ($user->last_name ?: $user->name)), 0, 1).mb_substr(trim((string) ($user->last_name ? $user->name : $user->middle_name)), 0, 1));
    $fullName = trim(implode(' ', array_filter([$user->last_name, $user->name, $user->middle_name])));
    $digits = preg_replace('/\D/', '', (string) $user->phone);
    $digits2 = preg_replace('/\D/', '', (string) $user->additional_phone);
    $roles = $user->roles->pluck('name');
    $canEdit = \App\Filament\Resources\UserResource::canEdit($user);
    $editUrl = \App\Filament\Resources\UserResource::getUrl('edit', ['record' => $user]);
    $label = 'display:block;font-size:11px;letter-spacing:.03em;text-transform:uppercase;color:var(--gray-500);margin-bottom:2px;';
    $row = 'display:flex;gap:12px;align-items:flex-start;padding:10px 0;border-bottom:1px solid var(--gray-100);';
    $ico = 'flex:none;width:20px;height:20px;margin-top:14px;color:var(--primary-600);';
@endphp
<div style="max-width:520px;">
    <div style="display:flex;gap:14px;align-items:center;padding-bottom:12px;border-bottom:1px solid var(--gray-200);">
        <div style="flex:none;width:48px;height:48px;display:flex;align-items:center;justify-content:center;background:var(--primary-100);color:var(--primary-700);font-weight:600;font-size:16px;">{{ $initials }}</div>
        <div style="min-width:0;">
            <span class="font-semibold text-gray-950 dark:text-white" style="font-size:16px;">{{ $fullName }}</span>
            <div class="text-xs text-gray-500">користувач #{{ $user->id }}</div>
        </div>
        @if ($canEdit)
            <a href="{{ $editUrl }}" title="Редагувати користувача" class="text-primary-600 hover:bg-gray-50" style="margin-left:auto;flex:none;padding:6px;">
                <x-filament::icon icon="heroicon-o-pencil-square" style="width:20px;height:20px;" />
            </a>
        @endif
    </div>

    <div style="{{ $row }}">
        <x-filament::icon icon="heroicon-o-user" style="{{ $ico }}" />
        <div style="flex:1;display:flex;flex-wrap:wrap;gap:0 32px;">
            <div style="min-width:120px;"><span style="{{ $label }}">Ім'я</span><span class="text-sm text-gray-950 dark:text-white">{{ $user->name ?: '—' }}</span></div>
            <div style="min-width:120px;"><span style="{{ $label }}">Прізвище</span><span class="text-sm text-gray-950 dark:text-white">{{ $user->last_name ?: '—' }}</span></div>
            <div style="min-width:120px;"><span style="{{ $label }}">По батькові</span><span class="text-sm text-gray-950 dark:text-white">{{ $user->middle_name ?: '—' }}</span></div>
        </div>
    </div>

    <div style="{{ $row }}">
        <x-filament::icon icon="heroicon-o-phone" style="{{ $ico }}" />
        <div><span style="{{ $label }}">Телефон</span>
            @if ($digits !== '')<a class="text-sm font-medium text-primary-600 hover:underline" href="tel:+{{ $digits }}">{{ $user->phone }}</a>@else<span class="text-sm text-gray-500">—</span>@endif
        </div>
    </div>

    <div style="{{ $row }}">
        <x-filament::icon icon="heroicon-o-device-phone-mobile" style="{{ $ico }}" />
        <div><span style="{{ $label }}">Додатковий телефон</span>
            @if ($digits2 !== '')<a class="text-sm text-primary-600 hover:underline" href="tel:+{{ $digits2 }}">{{ $user->additional_phone }}</a>@else<span class="text-sm text-gray-500">—</span>@endif
        </div>
    </div>

    <div style="{{ $row }}">
        <x-filament::icon icon="heroicon-o-envelope" style="{{ $ico }}" />
        <div><span style="{{ $label }}">Електронна пошта</span>
            @if ($user->email)<a class="text-sm text-primary-600 hover:underline" href="mailto:{{ $user->email }}">{{ $user->email }}</a>@else<span class="text-sm text-gray-500">—</span>@endif
        </div>
    </div>

    <div style="{{ $row }}">
        <x-filament::icon icon="heroicon-o-shield-check" style="{{ $ico }}" />
        <div><span style="{{ $label }}">Роль</span>
            <span class="text-sm text-gray-950 dark:text-white">{{ $roles->isNotEmpty() ? $roles->implode(', ') : '—' }}</span>
        </div>
    </div>

    <div style="{{ $row }}">
        <x-filament::icon :icon="$user->is_active ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'" style="{{ $ico }}" />
        <div><span style="{{ $label }}">Активний</span>
            <span class="text-sm font-medium" style="color:{{ $user->is_active ? 'var(--primary-700)' : 'var(--gray-500)' }};">{{ $user->is_active ? 'Так' : 'Ні' }}</span>
        </div>
    </div>

    <div style="{{ $row }}border-bottom:0;">
        <x-filament::icon icon="heroicon-o-calendar-days" style="{{ $ico }}" />
        <div><span style="{{ $label }}">Коли створений</span>
            <span class="text-sm text-gray-950 dark:text-white">{{ $user->created_at?->format('d.m.Y H:i') ?? '—' }}</span>
        </div>
    </div>
</div>

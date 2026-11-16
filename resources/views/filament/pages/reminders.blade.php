<x-filament-panels::page>

    {{-- Short how-to for managers, always at the very top. --}}
    <div style="padding:.85rem 1rem;margin-bottom:1rem;border:1px solid #cfd8de;background:#f4f8fb;border-radius:3px;font-size:.9rem;line-height:1.5;">
        <strong>Як це працює.</strong> У картці заявки натисни «Створити нагадування», обери дату й потрібну годину — і в цей час браузер сам нагадає про це спливаючим сповіщенням, навіть якщо вкладка з CRM закрита (головне — щоб браузер був запущений і ти дозволив сповіщення нижче). Усі свої нагадування бачиш тут; олівець справа дозволяє змінити текст чи час.
    </div>

    {{-- Compact warning only while this browser is not subscribed; the actual switch lives behind the gear (top right). --}}
    <div x-data="pushState(@js(config('webpush.public_key')), @js(csrf_token()))" x-init="init()" x-cloak>
        <div x-show="['prompt','denied','misconfigured'].includes(status)" style="padding:.6rem 1rem;margin-bottom:1rem;border:1px solid #f0c36d;background:#fff8e6;border-radius:3px;font-size:.875rem;">
            ⚠ Сповіщення в цьому браузері не увімкнені — нагадування не спливатимуть.
            <button type="button" wire:click="mountAction('notificationSettings')" style="margin-left:.5rem;padding:.25rem .75rem;background:#2874A6;color:#fff;border:0;border-radius:3px;cursor:pointer;">Відкрити налаштування ⚙</button>
        </div>
    </div>

    <script>
        function pushState(publicKey, csrf) {
            const b64ToBytes = (b64) => {
                const pad = '='.repeat((4 - b64.length % 4) % 4);
                const raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
                return Uint8Array.from(raw, (c) => c.charCodeAt(0));
            };
            const send = (method, body) => fetch('/push/subscribe', {
                method,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(body),
                credentials: 'same-origin',
            });

            return {
                status: 'loading',

                async init() {
                    if (! publicKey) { this.status = 'misconfigured'; return; }
                    if (! ('serviceWorker' in navigator) || ! ('PushManager' in window) || ! ('Notification' in window)) { this.status = 'unsupported'; return; }
                    if (Notification.permission === 'denied') { this.status = 'denied'; return; }

                    const reg = await navigator.serviceWorker.register('/sw.js');
                    const sub = await reg.pushManager.getSubscription();

                    if (Notification.permission === 'granted' && sub) {
                        // Re-send on every visit: idempotent upsert keeps the server in sync (e.g. after a DB reset).
                        await send('POST', sub.toJSON());
                        this.status = 'ok';
                        return;
                    }
                    this.status = 'prompt';
                },

                async enable() {
                    const permission = await Notification.requestPermission();
                    if (permission !== 'granted') { this.status = permission === 'denied' ? 'denied' : 'prompt'; return; }

                    const reg = await navigator.serviceWorker.ready;
                    const sub = (await reg.pushManager.getSubscription())
                        || await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToBytes(publicKey) });
                    await send('POST', sub.toJSON());
                    this.status = 'ok';
                },
            };
        }
    </script>

    {{-- Tab bar: a line across the top; one button per reminder type. --}}
    <div style="display:flex;gap:.25rem;border-bottom:1px solid #cfd8de;margin-bottom:1rem;">
        @foreach ($this->getTabs() as $key => $label)
            @php $active = $this->activeTab === $key; $open = $this->openCount($key); @endphp
            <button type="button" wire:click="setTab('{{ $key }}')"
                style="padding:.6rem 1.1rem;font-size:.9rem;cursor:pointer;background:none;border:0;border-bottom:3px solid {{ $active ? '#2874A6' : 'transparent' }};margin-bottom:-1px;font-weight:{{ $active ? '600' : '400' }};color:{{ $active ? '#2874A6' : 'inherit' }};">
                {{ $label }}@if ($open > 0) <span style="margin-left:.35rem;font-size:.75rem;background:#2874A6;color:#fff;border-radius:3px;padding:1px 6px;">{{ $open }}</span>@endif
            </button>
        @endforeach
    </div>

    {{ $this->table }}

</x-filament-panels::page>

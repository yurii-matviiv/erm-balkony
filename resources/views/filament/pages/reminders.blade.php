<x-filament-panels::page>

    <div>
    {{-- Short how-to for managers: its own text per tab, always at the very top. --}}
    <div style="padding:.85rem 1rem;margin-bottom:1rem;border:1px solid #cfd8de;background:#f4f8fb;border-radius:3px;font-size:.9rem;line-height:1.5;">
        @if ($this->activeTab === 'measurements')
            <strong>Нагадування про заміри.</strong> Створюються автоматично, коли в заявці створюєш запис на замір: ввечері напередодні та в день заміру. Поки не натиснеш «Побачив», сповіщення повторюється. Повні правила та години — кнопка «Інструкція» справа вгорі.
        @else
            <strong>Як це працює.</strong> У картці заявки натисни «Створити нагадування», обери дату й потрібну годину — і в цей час браузер сам нагадає про це спливаючим сповіщенням, навіть якщо вкладка з CRM закрита (головне — щоб браузер був запущений і ти дозволив сповіщення нижче). Усі свої нагадування бачиш тут; олівець справа дозволяє змінити текст чи час.
        @endif
    </div>

    {{-- Unconfirmed measurement reminders: visible on any tab. --}}
    @if (($attention = $this->attentionCount()) > 0)
        <div style="padding:.6rem 1rem;margin-bottom:1rem;border:1px solid #e6a5a5;background:#fdeeee;border-radius:3px;font-size:.9rem;">
            🔔 Нагадувань про заміри без підтвердження: <strong>{{ $attention }}</strong>. Відкрий вкладку «Заміри» і натисни «Побачив».
        </div>
    @endif

    {{-- Compact warning only while this browser is not subscribed; the actual switch lives behind the gear (top right). --}}
    <div x-data="pushState(@js(config('webpush.public_key')), @js(csrf_token()))" x-init="init()" x-cloak>
        <div x-show="['prompt','denied','misconfigured'].includes(status)" style="padding:.6rem 1rem;margin-bottom:1rem;border:1px solid #f0c36d;background:#fff8e6;border-radius:3px;font-size:.875rem;">
            ⚠ Сповіщення в цьому браузері не увімкнені — нагадування не спливатимуть. Увімкни їх через шестерню ⚙ справа вгорі.
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
    </div>

    {{-- Standard Filament tabs: the look comes from the global tabs-styles (same as the client card). --}}
    <x-filament::tabs>
        @foreach ($this->getTabs() as $key => $label)
            @php $open = $this->openCount($key); $red = $key === 'measurements' && $this->attentionCount() > 0; @endphp
            <x-filament::tabs.item
                :active="$this->activeTab === $key"
                :badge="$open > 0 ? $open : null"
                :badge-color="$red ? 'danger' : 'primary'"
                wire:click="setTab('{{ $key }}')">
                {{ $label }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    {{ $this->table }}

</x-filament-panels::page>

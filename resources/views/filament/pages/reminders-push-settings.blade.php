{{-- Content of the "Налаштування сповіщень" modal (gear on the reminders page). pushState() is defined in reminders.blade.php. --}}
<div x-data="pushState(@js(config('webpush.public_key')), @js(csrf_token()))" x-init="init()" style="display:flex;flex-direction:column;gap:1rem;font-size:.9rem;">

    <div x-show="status === 'ok'" style="color:#2e7d32;">✔ Сповіщення в цьому браузері увімкнені</div>

    <div x-show="status === 'prompt'" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
        <span>Сповіщення в цьому браузері вимкнені.</span>
        <button type="button" x-on:click="enable()" style="padding:.4rem .9rem;background:#2874A6;color:#fff;border:0;border-radius:3px;cursor:pointer;font-weight:600;">Дозволити сповіщення</button>
    </div>

    <div x-show="status === 'denied'" style="padding:.6rem .8rem;border:1px solid #e0a3a3;background:#fdeeee;border-radius:3px;">
        Браузер заблокував сповіщення для цього сайту. Увімкни їх у налаштуваннях сайту (значок біля адреси → «Сповіщення» → «Дозволити») і онови сторінку.
    </div>

    <div x-show="status === 'unsupported'" style="padding:.6rem .8rem;border:1px solid #cfd8de;background:#f4f6f8;border-radius:3px;">
        Цей браузер не підтримує push-сповіщення (на iPhone потрібно додати CRM на головний екран).
    </div>

    <div x-show="status === 'misconfigured'" style="padding:.6rem .8rem;border:1px solid #e0a3a3;background:#fdeeee;border-radius:3px;">
        На сервері не налаштовано ключі VAPID (див. docs/SCHEDULER.md) — сповіщення поки неможливі.
    </div>

    <div>
        <button type="button" wire:click="sendTestPush" style="padding:.4rem .9rem;background:#fff;color:#2874A6;border:1px solid #2874A6;border-radius:3px;cursor:pointer;">Надіслати тестове сповіщення</button>
        <div style="margin-top:.35rem;color:#6b7b86;font-size:.8rem;">Надсилає сповіщення в усі твої підписані браузери, щоб перевірити, що все працює.</div>
    </div>
</div>

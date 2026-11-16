// Service worker for Web Push reminders. Lives at the site root so its scope covers
// /admin and /manager. Payload (JSON): { title, body, url }.
self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = { body: event.data ? event.data.text() : '' }; }

    event.waitUntil(self.registration.showNotification(data.title || 'Нагадування', {
        body: data.body || '',
        icon: '/favicon.ico',
        data: { url: data.url || '/' },
        // One notification per reminder; a repeat replaces instead of stacking.
        tag: data.tag || undefined,
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
        for (const client of clients) {
            if ('focus' in client) { client.navigate(url); return client.focus(); }
        }
        return self.clients.openWindow(url);
    }));
});

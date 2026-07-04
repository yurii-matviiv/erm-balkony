<x-filament-panels::page>

{{-- FullCalendar v6 from CDN --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

{{-- ───────────────────────────────────────────────────────────────────────
     VIEW-MODE TOGGLE
     ─────────────────────────────────────────────────────────────────────── --}}
<div class="flex flex-wrap items-center gap-2 mb-4">
    <button id="btn-address"
            onclick="setViewMode('address')"
            class="mc-toggle-btn">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" class="mc-btn-icon"><path d="M12 2C8.134 2 5 5.134 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.866-3.134-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
        По адресі
    </button>
    <button id="btn-installer"
            onclick="setViewMode('installer')"
            class="mc-toggle-btn">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" class="mc-btn-icon"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        По монтажниках
    </button>
</div>

{{-- ───────────────────────────────────────────────────────────────────────
     CALENDAR — wire:ignore prevents Livewire morphing the FullCalendar DOM
     ─────────────────────────────────────────────────────────────────────── --}}
<div wire:ignore class="mc-calendar-wrap">
    <div id="fc-calendar"></div>
</div>

{{-- ───────────────────────────────────────────────────────────────────────
     HOVER TOOLTIP
     ─────────────────────────────────────────────────────────────────────── --}}
<div id="fc-tooltip" role="tooltip">
    <div id="fc-tt-id"    class="mc-tt-title"></div>
    <div id="fc-tt-stage" class="mc-tt-stage"></div>
    <div class="mc-tt-row"><span class="mc-tt-lbl">👤</span><span id="fc-tt-client"></span></div>
    <div class="mc-tt-row"><span class="mc-tt-lbl">📍</span><span id="fc-tt-addr"></span></div>
    <div class="mc-tt-row"><span class="mc-tt-lbl">👷</span><span id="fc-tt-inst"></span></div>
    <div class="mc-tt-row"><span class="mc-tt-lbl">💰</span><span id="fc-tt-price"></span></div>
    <div class="mc-tt-row"><span class="mc-tt-lbl">📅</span><span id="fc-tt-date"></span></div>
</div>

{{-- ───────────────────────────────────────────────────────────────────────
     CLICK MODAL
     ─────────────────────────────────────────────────────────────────────── --}}
<div id="fc-modal" onclick="if(event.target===this)closeModal()" aria-modal="true" role="dialog">
    <div id="fc-modal-inner">

        {{-- Header --}}
        <div class="mc-modal-header">
            <div>
                <span class="mc-modal-order-id">Замовлення <span id="mc-oid"></span></span>
                <span id="mc-stage" class="mc-stage-badge"></span>
            </div>
            <button onclick="closeModal()" class="mc-modal-close" title="Закрити">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.5" width="20" height="20">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        {{-- Body --}}
        <div class="mc-modal-body">
            <div class="mc-info-grid">
                <div class="mc-info-card">
                    <div class="mc-info-label">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mc-info-icon"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        Клієнт
                    </div>
                    <div class="mc-info-value" id="mc-client"></div>
                    <div class="mc-info-sub"  id="mc-phone"></div>
                </div>

                <div class="mc-info-card">
                    <div class="mc-info-label">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mc-info-icon"><path d="M12 2C8.134 2 5 5.134 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.866-3.134-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
                        Адреса монтажу
                    </div>
                    <div class="mc-info-value" id="mc-addr"></div>
                </div>

                <div class="mc-info-card">
                    <div class="mc-info-label">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mc-info-icon"><rect x="2" y="7" width="20" height="15" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
                        Монтажник
                    </div>
                    <div class="mc-info-value" id="mc-inst"></div>
                </div>

                <div class="mc-info-card">
                    <div class="mc-info-label">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mc-info-icon"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        Вартість
                    </div>
                    <div class="mc-info-value mc-price" id="mc-price"></div>
                </div>

                <div class="mc-info-card">
                    <div class="mc-info-label">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mc-info-icon"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Дата монтажу
                    </div>
                    <div class="mc-info-value" id="mc-date"></div>
                </div>

                <div class="mc-info-card">
                    <div class="mc-info-label">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mc-info-icon"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Менеджер
                    </div>
                    <div class="mc-info-value" id="mc-manager"></div>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="mc-modal-footer">
            <button onclick="closeModal()" class="mc-btn-close-modal">
                Закрити вікно
            </button>
            <a id="mc-edit-link" href="#" class="mc-btn-goto">
                Перейти в замовлення
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.5" width="16" height="16">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>
        </div>

    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════
     STYLES
     ═══════════════════════════════════════════════════════════════════════ --}}
<style>
/* ── Toggle buttons ──────────────────────────────────────────────────── */
.mc-toggle-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 16px; border-radius: 8px; font-size: 13px; font-weight: 500;
    cursor: pointer; border: 1.5px solid #d1d5db; background: #f9fafb;
    color: #374151; transition: all .15s;
}
.dark .mc-toggle-btn { background:#1e293b; border-color:#334155; color:#cbd5e1; }
.mc-toggle-btn:hover { background:#f3f4f6; border-color:#9ca3af; }
.dark .mc-toggle-btn:hover { background:#263347; }
.mc-toggle-btn.mc-active {
    background: #f59e0b; border-color: #d97706; color: #1c1917; font-weight: 600;
}
.dark .mc-toggle-btn.mc-active { background:#f59e0b; border-color:#d97706; color:#1c1917; }
.mc-btn-icon { width:15px; height:15px; flex-shrink:0; }

/* ── Calendar wrapper ────────────────────────────────────────────────── */
.mc-calendar-wrap {
    background: white; border-radius: 12px; padding: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,.08);
}
.dark .mc-calendar-wrap { background: #1e293b; }

/* FullCalendar dark mode overrides */
.dark .fc { color: #e2e8f0; }
.dark .fc-theme-standard td, .dark .fc-theme-standard th, .dark .fc-theme-standard .fc-scrollgrid { border-color: #334155; }
.dark .fc-col-header-cell { background: #0f172a; }
.dark .fc-daygrid-day { background: #1e293b; }
.dark .fc-daygrid-day:hover { background: #263347; }
.dark .fc-day-other .fc-daygrid-day-number { color: #475569; }
.dark .fc-button-primary { background:#334155 !important; border-color:#475569 !important; color:#e2e8f0 !important; }
.dark .fc-button-primary:hover { background:#475569 !important; }
.dark .fc-button-primary.fc-button-active { background:#f59e0b !important; border-color:#d97706 !important; color:#1c1917 !important; }
.dark .fc-today-button { opacity: .6; }
.dark .fc-day-today { background: rgba(245,158,11,.08) !important; }

/* Event chips */
.fc-event { cursor: pointer; border-radius: 4px !important; font-size: 12px !important; font-weight: 500 !important; }
.fc-daygrid-event { padding: 2px 5px !important; }
.fc-event-title { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ── Tooltip ─────────────────────────────────────────────────────────── */
#fc-tooltip {
    display: none; position: fixed; z-index: 9000;
    background: #0f172a; color: #f1f5f9;
    border-radius: 10px; padding: 12px 14px;
    font-size: 13px; line-height: 1.7; min-width: 220px; max-width: 300px;
    box-shadow: 0 8px 30px rgba(0,0,0,.4); pointer-events: none;
}
.mc-tt-title { font-weight: 700; font-size: 14px; margin-bottom: 2px; }
.mc-tt-stage { font-size: 11px; opacity: .6; margin-bottom: 8px; }
.mc-tt-row   { display: flex; gap: 8px; }
.mc-tt-lbl   { width: 20px; text-align: center; flex-shrink: 0; }

/* ── Modal overlay ───────────────────────────────────────────────────── */
#fc-modal {
    display: none; position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,.6); backdrop-filter: blur(3px);
    align-items: center; justify-content: center; padding: 16px;
}
#fc-modal.open { display: flex; }

#fc-modal-inner {
    background: white; border-radius: 16px; width: 100%; max-width: 560px;
    max-height: calc(100vh - 32px); display: flex; flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,.4); overflow: hidden;
}
.dark #fc-modal-inner { background: #1e293b; color: #e2e8f0; }

/* Modal header */
.mc-modal-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    padding: 20px 20px 14px; border-bottom: 1px solid #e5e7eb;
    flex-shrink: 0;
}
.dark .mc-modal-header { border-bottom-color: #334155; }
.mc-modal-order-id { font-size: 18px; font-weight: 700; display: block; margin-bottom: 4px; }
.mc-stage-badge {
    display: inline-block; font-size: 11px; font-weight: 600; letter-spacing: .02em;
    padding: 2px 8px; border-radius: 99px; background: #fef3c7; color: #92400e;
}
.dark .mc-stage-badge { background: #451a03; color: #fcd34d; }
.mc-modal-close {
    background: none; border: none; cursor: pointer; padding: 4px;
    color: #6b7280; border-radius: 6px; flex-shrink: 0; margin-left: 12px;
    transition: color .15s, background .15s;
}
.mc-modal-close:hover { background: #f3f4f6; color: #111827; }
.dark .mc-modal-close:hover { background: #334155; color: #f1f5f9; }

/* Modal body */
.mc-modal-body { flex: 1; overflow-y: auto; padding: 16px 20px; }
.mc-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.mc-info-card {
    background: #f9fafb; border-radius: 10px; padding: 12px 14px;
    border: 1px solid #f3f4f6;
}
.dark .mc-info-card { background: #0f172a; border-color: #1e293b; }
.mc-info-label {
    display: flex; align-items: center; gap: 6px;
    font-size: 11px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase;
    color: #9ca3af; margin-bottom: 6px;
}
.mc-info-icon { width: 13px; height: 13px; flex-shrink: 0; }
.mc-info-value { font-size: 14px; font-weight: 600; color: #111827; word-break: break-word; }
.dark .mc-info-value { color: #f1f5f9; }
.mc-info-sub  { font-size: 12px; color: #6b7280; margin-top: 2px; }
.mc-price     { font-size: 16px; color: #10b981 !important; }

/* Modal footer */
.mc-modal-footer {
    display: flex; align-items: center; justify-content: flex-end; gap: 10px;
    padding: 14px 20px; border-top: 1px solid #e5e7eb; flex-shrink: 0;
}
.dark .mc-modal-footer { border-top-color: #334155; }
.mc-btn-close-modal {
    padding: 9px 18px; border-radius: 8px; font-size: 14px; font-weight: 500;
    border: 1.5px solid #d1d5db; background: white; color: #374151; cursor: pointer;
    transition: all .15s;
}
.mc-btn-close-modal:hover { background: #f3f4f6; }
.dark .mc-btn-close-modal { background: #1e293b; border-color: #334155; color: #cbd5e1; }
.dark .mc-btn-close-modal:hover { background: #263347; }
.mc-btn-goto {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 18px; border-radius: 8px; font-size: 14px; font-weight: 600;
    background: #f59e0b; color: #1c1917; text-decoration: none;
    transition: background .15s;
}
.mc-btn-goto:hover { background: #d97706; }

/* ── Mobile — 100% screen ────────────────────────────────────────────── */
@media (max-width: 640px) {
    #fc-modal { padding: 0; align-items: flex-end; }
    #fc-modal-inner {
        max-width: 100%; border-radius: 16px 16px 0 0;
        max-height: 92vh;
    }
    .mc-info-grid { grid-template-columns: 1fr; }
    #fc-tooltip { display: none !important; } /* no hover on mobile */
}
</style>

{{-- ═══════════════════════════════════════════════════════════════════════
     JAVASCRIPT
     ═══════════════════════════════════════════════════════════════════════ --}}
<script>
let calendar  = null;
let allEvents = [];
let viewMode  = localStorage.getItem('montageViewMode') || 'address';

/* ── Helpers ────────────────────────────────────────────────────────────── */
function getTitle(props) {
    return viewMode === 'address'
        ? (props.address  || `Замовлення #${props.orderId}`)
        : (props.installerName || 'Без монтажника');
}

function applyViewMode(events) {
    return events.map(e => ({ ...e, title: getTitle(e.extendedProps) }));
}

function updateToggleButtons() {
    document.getElementById('btn-address')?.classList.toggle('mc-active', viewMode === 'address');
    document.getElementById('btn-installer')?.classList.toggle('mc-active', viewMode === 'installer');
}

/* ── View mode toggle ────────────────────────────────────────────────────── */
function setViewMode(mode) {
    viewMode = mode;
    localStorage.setItem('montageViewMode', mode);
    updateToggleButtons();
    if (calendar) {
        calendar.getEvents().forEach(ev => {
            ev.setProp('title', getTitle(ev.extendedProps));
        });
    }
}

/* ── Tooltip ─────────────────────────────────────────────────────────────── */
function showTooltip({ event, jsEvent }) {
    const p = event.extendedProps;
    const t = document.getElementById('fc-tooltip');
    document.getElementById('fc-tt-id').textContent    = `Замовлення #${p.orderId}`;
    document.getElementById('fc-tt-stage').textContent = p.stageLabel;
    document.getElementById('fc-tt-client').textContent = p.clientName;
    document.getElementById('fc-tt-addr').textContent   = p.address;
    document.getElementById('fc-tt-inst').textContent   = p.installerName;
    document.getElementById('fc-tt-price').textContent  = p.totalPrice;
    document.getElementById('fc-tt-date').textContent   = p.montageDate;
    t.style.display = 'block';
    positionTooltip(jsEvent);
}

function hideTooltip() {
    document.getElementById('fc-tooltip').style.display = 'none';
}

function positionTooltip(e) {
    const t = document.getElementById('fc-tooltip');
    const x = e.clientX + 14;
    const y = e.clientY + 14;
    t.style.left = Math.min(x, window.innerWidth  - 320) + 'px';
    t.style.top  = Math.min(y, window.innerHeight - 220) + 'px';
}

document.addEventListener('mousemove', e => {
    const t = document.getElementById('fc-tooltip');
    if (t && t.style.display === 'block') positionTooltip(e);
});

/* ── Modal ───────────────────────────────────────────────────────────────── */
function showModal({ event }) {
    hideTooltip();
    const p = event.extendedProps;

    document.getElementById('mc-oid').textContent      = `#${p.orderId}`;
    document.getElementById('mc-stage').textContent    = p.stageLabel;
    document.getElementById('mc-client').textContent   = p.clientName;
    document.getElementById('mc-phone').textContent    = p.clientPhone;
    document.getElementById('mc-addr').textContent     = p.address;
    document.getElementById('mc-inst').textContent     = p.installerName;
    document.getElementById('mc-price').textContent    = p.totalPrice;
    document.getElementById('mc-date').textContent     = p.montageDate;
    document.getElementById('mc-manager').textContent  = p.managerName;
    document.getElementById('mc-edit-link').href       = p.editUrl;

    document.getElementById('fc-modal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('fc-modal').classList.remove('open');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

/* ── Calendar init ──────────────────────────────────────────────────────── */
function initCalendar() {
    const el = document.getElementById('fc-calendar');
    if (!el) return;
    if (calendar) { calendar.destroy(); calendar = null; }

    updateToggleButtons();

    const csrfToken = document.querySelector('meta[name=csrf-token]')?.content ?? '';

    calendar = new FullCalendar.Calendar(el, {
        initialView:   'dayGridMonth',
        locale:        'uk',
        firstDay:      1,        // Monday
        height:        'auto',
        handleWindowResize: true,

        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth,timeGridWeek,listMonth',
        },

        buttonText: {
            today:   'Сьогодні',
            month:   'Місяць',
            week:    'Тиждень',
            list:    'Список',
        },

        events: function(info, success, failure) {
            fetch(`/admin/api/montage-events?start=${info.startStr}&end=${info.endStr}`, {
                headers: {
                    'Accept':       'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
            })
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(data => {
                allEvents = data;
                success(applyViewMode(data));
            })
            .catch(err => {
                console.error('Calendar events error:', err);
                failure(err);
            });
        },

        eventMouseEnter: showTooltip,
        eventMouseLeave: hideTooltip,
        eventClick:      showModal,

        /* On mobile, tapping an event should only open the modal, not navigate */
        eventDidMount(info) {
            info.el.title = ''; // remove default browser tooltip
        },
    });

    calendar.render();
}

/* ── Lifecycle ──────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded',    initCalendar);
document.addEventListener('livewire:navigated',  () => { calendar = null; setTimeout(initCalendar, 80); });
</script>

</x-filament-panels::page>

{{--
    «Залипаюча» шапка ВСІХ таблиць Filament (глобально, підключено в AppServiceProvider → STYLES_AFTER, разом із
    table-header-styles). Нічого підключати на сторінках не треба: будь-яка таблиця (`.fi-ta-content-ctn`) отримує поведінку сама.

    Два режими (вибираються автоматично для кожної таблиці):
    1) Таблиця вміщується по ширині → справжній `position: sticky` (плавно, без стрибків). Для цього контейнери-обгортки,
       які створюють scroll-контейнер (overflow auto/hidden), отримують `overflow: clip` (кути картки лишаються обрізаними).
    2) Таблиця ширша за картку (горизонтальна прокрутка) → sticky неможливий (липне до картки, а не до сторінки), тому
       шапка зсувається вниз (translateY) на величину прокрутки сторінки; такий режим може злегка «смикатись».
    Таблиці у модальних вікнах не чіпаємо.
--}}
<style>
    .fi-ta-content-ctn.sticky-managed table.fi-ta-table thead th {
        position: relative;
        z-index: 5;
        transform: translateY(var(--thead-offset, 0px));
        background-color: var(--table-header-bg);
    }

    .fi-ta-content-ctn.sticky-managed table.fi-ta-table thead th::after {
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: 1px;
        background: var(--table-header-line);
    }

    /* Режим 1: нативний sticky. */
    .sticky-clip {
        overflow: clip !important;
    }

    .fi-ta-content-ctn.sticky-native table.fi-ta-table thead th {
        position: sticky;
        top: var(--sticky-top, 0px);
        transform: none;
    }
</style>

<script>
    (function () {
        if (window.__stickyTableHeader) {
            return;
        }

        window.__stickyTableHeader = true;

        var queued = false;

        // Ancestors that would act as scroll containers (and break sticky) between the table box and the page.
        function scrollAncestors(box) {
            var list = [];

            for (var el = box; el && el !== document.body; el = el.parentElement) {
                var cs = getComputedStyle(el);

                if (/(auto|scroll|hidden)/.test(cs.overflowX + cs.overflowY) && ! el.classList.contains('fi-layout')) {
                    list.push(el);
                }
            }

            return list;
        }

        function update() {
            queued = false;

            var topbar = document.querySelector('.fi-topbar');
            var tbPos = topbar ? getComputedStyle(topbar).position : '';
            var top = (tbPos === 'sticky' || tbPos === 'fixed') ? Math.round(topbar.getBoundingClientRect().height) : 0;

            document.querySelectorAll('.fi-ta-content-ctn').forEach(function (box) {
                var table = box.querySelector('table.fi-ta-table');
                var head = table && table.tHead;

                if (! head || box.closest('.fi-modal')) {
                    return;
                }

                box.classList.add('sticky-managed');
                table.style.setProperty('--sticky-top', top + 'px');

                var fits = table.offsetWidth <= box.clientWidth + 1;

                if (fits !== box.classList.contains('sticky-native')) {
                    box.classList.toggle('sticky-native', fits);

                    if (fits) {
                        scrollAncestors(box).forEach(function (el) {
                            el.classList.add('sticky-clip');
                        });
                    } else {
                        document.querySelectorAll('.sticky-clip').forEach(function (el) {
                            if (el.contains(box)) {
                                el.classList.remove('sticky-clip');
                            }
                        });
                    }
                }

                if (fits) {
                    table.style.setProperty('--thead-offset', '0px');

                    return;
                }

                var scrolledPast = -box.getBoundingClientRect().top;
                var room = table.offsetHeight - head.offsetHeight - 8;
                var offset = Math.min(Math.max(scrolledPast, 0), Math.max(room, 0));

                table.style.setProperty('--thead-offset', Math.round(offset) + 'px');
            });
        }

        function queue() {
            if (! queued) {
                queued = true;
                requestAnimationFrame(update);
            }
        }

        window.addEventListener('scroll', queue, { passive: true });
        window.addEventListener('resize', queue);
        // Livewire перемальовує таблицю (фільтри, сторінки, вкладки) — перераховуємо.
        new MutationObserver(queue).observe(document.documentElement, { childList: true, subtree: true });
        document.addEventListener('DOMContentLoaded', queue);
        queue();
    })();
</script>

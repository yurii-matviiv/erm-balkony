{{--
    «Залипаюча» шапка таблиці: при гортанні СТОРІНКИ заголовки колонок лишаються вгорі.
    Використання: обгорнути таблицю в <div class="sticky-table-header">…</div> і підключити цей партіал.

    Чому через JS, а не position: sticky: таблиця Filament має горизонтальну прокрутку всередині картки
    (overflow-x: auto), а sticky у такому контейнері липне до НЬОГО, а не до сторінки. Прибрати прокрутку
    не можна — широкі таблиці вилізуть за картку; обмежити висоту картки — це «віконце» в таблиці.
    Тому шапка просто зсувається вниз (translateY) на стільки, на скільки сторінку прогорнули;
    ширина шапки й горизонтальна прокрутка лишаються рідними, шапка завжди відповідає колонкам.
--}}
<style>
    .sticky-table-header .fi-ta-table thead th {
        position: relative;
        z-index: 5;
        transform: translateY(var(--thead-offset, 0px));
        background-color: var(--table-header-bg);
    }

    .sticky-table-header .fi-ta-table thead th::after {
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: 1px;
        background: var(--table-header-line);
    }


    /* Таблиця вміщується по ширині → справжній position: sticky (без стрибків: браузер сам тримає шапку на compositor-рівні).
       Для цього контейнери між таблицею й обгорткою не мають бути scroll-контейнерами: overflow: clip (кути картки лишаються обрізаними). */
    .sticky-table-header .sticky-clip {
        overflow: clip !important;
    }

    .sticky-table-header .sticky-native table.fi-ta-table thead th {
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

        function update() {
            queued = false;

            document.querySelectorAll('.sticky-table-header .fi-ta-content-ctn').forEach(function (box) {
                var table = box.querySelector('table.fi-ta-table');
                var head = table && table.tHead;

                if (! head) {
                    return;
                }

                // Вміщується по ширині — вмикаємо нативний sticky (плавно); широка таблиця лишається на зсуві (translateY).
                var wrap = box.closest('.sticky-table-header');
                var fits = table.offsetWidth <= box.clientWidth + 1;
                var topbar = document.querySelector('.fi-topbar');
                // Відступ зверху = висота закріпленого топбара (якщо він є й залипає), інакше 0.
                var tbPos = topbar ? getComputedStyle(topbar).position : '';
                table.style.setProperty('--sticky-top', ((tbPos === 'sticky' || tbPos === 'fixed') ? Math.round(topbar.getBoundingClientRect().height) : 0) + 'px');

                if (fits !== box.classList.contains('sticky-native')) {
                    box.classList.toggle('sticky-native', fits);
                    for (var el = box; el && el !== wrap; el = el.parentElement) {
                        el.classList.toggle('sticky-clip', fits);
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
        // Livewire перемальовує таблицю (фільтри, сторінки) — перераховуємо.
        new MutationObserver(queue).observe(document.documentElement, { childList: true, subtree: true });
        queue();
    })();
</script>

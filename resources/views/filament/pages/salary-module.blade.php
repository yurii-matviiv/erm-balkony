{{--
    Стандартна таблиця Filament. Стилі нижче лише відділяють періоди («місяць · 1–15 / 16–кінець»):
    між групами — помітний проміжок кольору сторінки, а рядок періоду — кольорова смуга з акцентом ліворуч.
--}}
<x-filament-panels::page>
    <div class="salary-module">
        {{ $this->table }}
    </div>

    <style>
    /* Зарплату виплачено повністю — увесь рядок світло-зелений. */
    .salary-module tr.salary-row-paid > td { background-color: color-mix(in srgb, var(--success-500) 10%, transparent); }
    /* Колонка з кнопкою дій не потрібна: вікно відкривається кліком по рядку. */
    .salary-module th.fi-ta-actions-header-cell,
    .salary-module td.fi-ta-cell:has(.fi-ta-actions) { display: none; }

        /* Фільтри й таблиця — два окремі блоки (картки) з проміжком між ними, а не одна склеєна картка. */
        .salary-module .fi-ta-ctn {
            background: transparent;
            box-shadow: none;
            --tw-ring-shadow: 0 0 #0000;
            --tw-shadow: 0 0 #0000;
        }

        .salary-module .fi-ta-header-ctn,
        .salary-module .fi-ta-content-ctn {
            background-color: #fff;
            border-radius: .75rem;
            box-shadow: 0 0 0 1px rgb(3 7 18 / .06), 0 1px 2px 0 rgb(0 0 0 / .05);
        }

        .salary-module .fi-ta-header-ctn {
            margin-bottom: 1.5rem;
        }

        /* Смужка «Active filters» дублює фільтри, які й так завжди видно, — прибрана. */
        .salary-module .fi-ta-filter-indicators {
            display: none;
        }

        .dark .salary-module .fi-ta-header-ctn,
        .dark .salary-module .fi-ta-content-ctn {
            background-color: var(--gray-900);
            box-shadow: 0 0 0 1px rgb(255 255 255 / .1);
        }

        /* Проміжок між періодами: товста верхня межа кольору фону сторінки (таблиця лишається однією, але візуально розірвана). */
        .salary-module .fi-ta-group-header-row > td {
            border-top: 2rem solid var(--gray-50);
            background-color: color-mix(in srgb, var(--primary-600) 14%, white);
            box-shadow: inset 5px 0 0 var(--primary-600);
        }

        /* Перший період — без проміжку зверху. */
        .salary-module tbody > .fi-ta-group-header-row:first-child > td {
            border-top-width: 0;
        }

        .salary-module .fi-ta-group-header-cell {
            font-weight: 600;
            color: var(--primary-800);
        }

        .dark .salary-module .fi-ta-group-header-row > td {
            border-top-color: var(--gray-950);
            background-color: color-mix(in srgb, var(--primary-600) 28%, transparent);
        }

        .dark .salary-module .fi-ta-group-header-cell {
            color: var(--primary-200);
        }
    </style>
</x-filament-panels::page>

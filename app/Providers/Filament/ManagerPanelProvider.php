<?php

namespace App\Providers\Filament;

use App\Filament\Manager\Pages\AddExpense;
use App\Filament\Manager\Pages\ManagerPayments;
use App\Filament\Manager\Pages\SalaryModule;
use App\Filament\Manager\Pages\UnacceptedCalls;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\MontageCalendar;
use App\Filament\Pages\Reminders;
use App\Filament\Resources\ClientResource;
use App\Filament\Resources\InstallerResource;
use App\Filament\Resources\LeadResource;
use App\Filament\Resources\OrderResource;
use Filament\Panel;
use Filament\PanelProvider;

/**
 * Робоче місце менеджера з продажу (/manager).
 *
 * Свідомо окрема панель, а не "адмінка з урізаним меню": у менеджера інше
 * меню й інший сценарій роботи (повторює стару CRM), а адмінські сторінки
 * (інтеграції, синхронізація, фінансова аналітика) тут просто НЕ ЗАРЕЄСТРОВАНІ —
 * їх неможливо відкрити навіть за прямим URL.
 *
 * Все спільне (логін, тема, бокове меню за ролями, меню користувача, хуки,
 * middleware) — AdminPanelProvider::configureCommon(). Тут лише ідентичність
 * панелі та ЯВНИЙ список того, що в ній є. Нова сторінка/ресурс для менеджера
 * з'являється в цій панелі тільки після додавання сюди (а не автоматично, як
 * в адмін-панелі, що сама все знаходить) — це і є ізоляція.
 *
 * Ресурси Lead/Order спільні з адмін-панеллю (одна модель, одні політики
 * Shield) — відрізняється лише URL (/manager/...) і меню. Хто в яку панель
 * потрапляє — config/panels.php.
 */
class ManagerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return AdminPanelProvider::configureCommon($panel)
            ->id('manager')
            ->path('manager')
            ->resources([
                // Client card is opened from the orders table (cell «Клієнт»); scoped to own clients.
                ClientResource::class,
                // Installer card (read-only) is opened from the «Монтажник» cell of the orders table.
                InstallerResource::class,
                LeadResource::class,
                OrderResource::class,
            ])
            ->pages([
                Dashboard::class,
                MontageCalendar::class,
                Reminders::class,
                // Stubs for the rest of the old CRM menu (see PlaceholderPage).
                UnacceptedCalls::class,
                ManagerPayments::class,
                AddExpense::class,
                SalaryModule::class,
            ]);
    }
}

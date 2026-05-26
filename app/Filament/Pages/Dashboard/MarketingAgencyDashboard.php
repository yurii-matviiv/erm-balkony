<?php

namespace App\Filament\Pages\Dashboard;

use Filament\Pages\Page;
use App\Services\Leads\LeadQueryService;

class MarketingAgencyDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Marketing Agency Dashboard';

    protected static ?string $title = 'Marketing Agency Dashboard';

    protected string $view = 'filament.pages.dashboard.marketing-agency-dashboard';

    public array $leads = [];

    /**
     * ---------------------------------------------------------
     * ACCESS
     * ---------------------------------------------------------
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('marketing_agency') ?? false;
    }

    /**
     * ---------------------------------------------------------
     * SIDEBAR NAVIGATION
     * ---------------------------------------------------------
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('marketing_agency') ?? false;
    }

    /**
     * ---------------------------------------------------------
     * LOAD DATA
     * ---------------------------------------------------------
     */
    public function mount(
        LeadQueryService $leadQueryService
    ): void {

        $this->leads = $leadQueryService
            ->getLeads()
            ->toArray();
    }
}

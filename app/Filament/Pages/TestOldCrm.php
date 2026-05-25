<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class TestOldCrm extends Page
{
    protected string $view = 'filament.pages.test-old-crm';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationLabel = 'Test old CRM';

    protected static ?string $title = 'Test old CRM';

    protected static ?int $navigationSort = 2;

    public array $data = [];

    public function mount(): void
    {
        $this->data = [
            'users_count' => DB::connection('old_crm')->table('users')->count(),
            'clients_count' => DB::connection('old_crm')->table('clients')->count(),
            'leads_count' => DB::connection('old_crm')->table('leads')->count(),
            'orders_count' => DB::connection('old_crm')->table('orders')->count(),
            'latest_leads' => DB::connection('old_crm')
                ->table('leads')
                ->select('*')
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->toArray(),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin');
    }
}

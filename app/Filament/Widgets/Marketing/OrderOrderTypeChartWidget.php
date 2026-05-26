<?php

namespace App\Filament\Widgets\Marketing;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Livewire\Attributes\On;

class OrderOrderTypeChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;
    
    protected ?string $heading = 'Тренд за типами замовлень (на основі замовлень)';
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('View:MarketingAgencyDashboard') ?? false;
    }

    protected function getData(): array
    {


        $filters = $this->pageFilters ?? $this->filters ?? [];
        $preset = $filters['preset'] ?? 'this_year';
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        
        // Типи замовлень для відображення
        $orderTypes = [
            'window_in_cottage' => 'Приватний будинок',
            'balcony' => 'Балкон',
            'balcony_with_takeout' => 'Балкон з виносом',
            'turnkey_balcony' => 'Балкон під ключ',
            'balcony_cladding' => 'Обшивка балкона',
            'window' => 'Вікна',
            'windows_plus_works' => 'Вікна + роботи',
            'aluminium_window' => 'Алюмінієві вікна',
            'entrance_group_pvc' => 'Вхідна група ПВХ',
            'entrance_group_aluminium' => 'Вхідна група алюміній',
            'glazing_terrace_pvc' => 'Скління тераси ПВХ',
            'glazing_terrace_aluminium' => 'Скління тераси алюміній',
            'frameless_glazing' => 'Безрамне скління',
            'sliding_system_cold' => 'Розсувна система холодна',
            'sliding_system_warm' => 'Розсувна система тепла',
        ];
        
        $datasets = [];
        
        foreach ($orderTypes as $typeKey => $typeLabel) {
            $query = Order::query()
                ->selectRaw('DATE(create_date) as date')
                ->selectRaw('COUNT(*) as total')
                ->where('order_type', $typeKey);
            
            // Застосовуємо фільтри дат (використовуємо create_date)
            if ($preset === 'today') {
                $query->whereDate('create_date', today());
            } elseif ($preset === 'yesterday') {
                $query->whereDate('create_date', today()->subDay());
            } elseif ($preset === 'this_month') {
                $query->whereDate('create_date', '>=', now()->startOfMonth())
                      ->whereDate('create_date', '<=', now());
            } elseif ($preset === 'last_30_days') {
                $query->whereDate('create_date', '>=', now()->subDays(30))
                      ->whereDate('create_date', '<=', now());
            } elseif ($preset === 'custom' && $dateFrom && $dateTo) {
                $query->whereDate('create_date', '>=', Carbon::parse($dateFrom))
                      ->whereDate('create_date', '<=', Carbon::parse($dateTo));
            } else {
                $query->whereDate('create_date', '>=', now()->startOfYear())
                      ->whereDate('create_date', '<=', now());
            }
            
            $data = $query->groupByRaw('DATE(create_date)')
                          ->orderByRaw('DATE(create_date) ASC')
                          ->get();
            
            if ($data->isNotEmpty()) {
                $datasets[] = [
                    'label' => $typeLabel,
                    'data' => $data->pluck('total')->toArray(),
                    'borderColor' => $this->getColorForType($typeKey),
                    'backgroundColor' => 'transparent',
                    'tension' => 0.3,
                ];
            }
        }
        
        // Отримуємо всі дати для labels
        $labels = [];
        $query = Order::query()
            ->selectRaw('DATE(create_date) as date')
            ->whereNotNull('order_type');
        
        if ($preset === 'today') {
            $query->whereDate('create_date', today());
        } elseif ($preset === 'yesterday') {
            $query->whereDate('create_date', today()->subDay());
        } elseif ($preset === 'this_month') {
            $query->whereDate('create_date', '>=', now()->startOfMonth())
                  ->whereDate('create_date', '<=', now());
        } elseif ($preset === 'last_30_days') {
            $query->whereDate('create_date', '>=', now()->subDays(30))
                  ->whereDate('create_date', '<=', now());
        } elseif ($preset === 'custom' && $dateFrom && $dateTo) {
            $query->whereDate('create_date', '>=', Carbon::parse($dateFrom))
                  ->whereDate('create_date', '<=', Carbon::parse($dateTo));
        } else {
            $query->whereDate('create_date', '>=', now()->startOfYear())
                  ->whereDate('create_date', '<=', now());
        }
        
        $labels = $query->groupByRaw('DATE(create_date)')
                        ->orderByRaw('DATE(create_date) ASC')
                        ->pluck('date')
                        ->toArray();
        
        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }
    
    private function getColorForType(string $type): string
    {
        return match ($type) {
            'window_in_cottage' => '#10b981',
            'balcony' => '#f59e0b',
            'balcony_with_takeout' => '#ef4444',
            'turnkey_balcony' => '#8b5cf6',
            'balcony_cladding' => '#ec4898',
            'window' => '#3b82f6',
            'windows_plus_works' => '#06b6d4',
            'aluminium_window' => '#84cc16',
            'entrance_group_pvc' => '#d946ef',
            'entrance_group_aluminium' => '#f97316',
            'glazing_terrace_pvc' => '#14b8a6',
            'glazing_terrace_aluminium' => '#6366f1',
            'frameless_glazing' => '#a855f7',
            'sliding_system_cold' => '#71717a',
            'sliding_system_warm' => '#f43f5e',
            default => '#6b7280',
        };
    }
    
    protected function getType(): string
    {
        return 'line';
    }
}
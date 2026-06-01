<?php

namespace App\Filament\Widgets\Marketing;

use App\Models\Lead;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Livewire\Attributes\On;

class LeadOrderTypeChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    
    protected ?string $heading = 'Trend за типами замовлень на основі ліда';
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('View:MarketingAgencyDashboard') || auth()->user()?->can('View:FounderDashboard') ?? false;
    }

    protected function getData(): array
    {

        $filters = $this->pageFilters ?? $this->filters ?? [];
        $preset = $filters['preset'] ?? 'this_year';
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        
        // Отримуємо всі унікальні типи замовлень
        $orderTypes = [
            'window_in_cottage' => 'Приватний будинок',
            'balcony' => 'Балкон',
            'balcony_with_takeout' => 'Балкон з виносом',
            'turnkey_balcony' => 'Балкон під ключ',
            'balcony_cladding' => 'Обшивка балкона',
            'window' => 'Вікна',
        ];
        
        $datasets = [];
        
        foreach ($orderTypes as $typeKey => $typeLabel) {
            $query = Lead::query()
                ->selectRaw('DATE(created_at) as date')
                ->selectRaw('COUNT(*) as total')
                ->where('order_type', $typeKey);
            
            // Застосовуємо фільтри дат
            if ($preset === 'today') {
                $query->whereDate('created_at', today());
            } elseif ($preset === 'yesterday') {
                $query->whereDate('created_at', today()->subDay());
            } elseif ($preset === 'this_month') {
                $query->whereDate('created_at', '>=', now()->startOfMonth())
                      ->whereDate('created_at', '<=', now());
            } elseif ($preset === 'last_30_days') {
                $query->whereDate('created_at', '>=', now()->subDays(30))
                      ->whereDate('created_at', '<=', now());
            } elseif ($preset === 'custom' && $dateFrom && $dateTo) {
                $query->whereDate('created_at', '>=', Carbon::parse($dateFrom))
                      ->whereDate('created_at', '<=', Carbon::parse($dateTo));
            } else {
                $query->whereDate('created_at', '>=', now()->startOfYear())
                      ->whereDate('created_at', '<=', now());
            }
            
            $data = $query->groupByRaw('DATE(created_at)')
                          ->orderByRaw('DATE(created_at) ASC')
                          ->get();
            
            $datasets[] = [
                'label' => $typeLabel,
                'data' => $data->pluck('total')->toArray(),
                'borderColor' => $this->getColorForType($typeKey),
                'backgroundColor' => 'transparent',
                'tension' => 0.3,
            ];
        }
        
        // Отримуємо всі дати для labels (беремо з першого датасету, який не пустий)
        $labels = [];
        foreach ($datasets as $dataset) {
            if (!empty($dataset['data'])) {
                // Потрібно отримати labels з оригінального запиту
                $query = Lead::query()
                    ->selectRaw('DATE(created_at) as date')
                    ->whereNotNull('order_type');
                
                // Застосовуємо ті самі фільтри дат
                if ($preset === 'today') {
                    $query->whereDate('created_at', today());
                } elseif ($preset === 'yesterday') {
                    $query->whereDate('created_at', today()->subDay());
                } elseif ($preset === 'this_month') {
                    $query->whereDate('created_at', '>=', now()->startOfMonth())
                          ->whereDate('created_at', '<=', now());
                } elseif ($preset === 'last_30_days') {
                    $query->whereDate('created_at', '>=', now()->subDays(30))
                          ->whereDate('created_at', '<=', now());
                } elseif ($preset === 'custom' && $dateFrom && $dateTo) {
                    $query->whereDate('created_at', '>=', Carbon::parse($dateFrom))
                          ->whereDate('created_at', '<=', Carbon::parse($dateTo));
                } else {
                    $query->whereDate('created_at', '>=', now()->startOfYear())
                          ->whereDate('created_at', '<=', now());
                }
                
                $labels = $query->groupByRaw('DATE(created_at)')
                                ->orderByRaw('DATE(created_at) ASC')
                                ->pluck('date')
                                ->toArray();
                break;
            }
        }
        
        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }
    
    private function getColorForType(string $type): string
    {
        return match ($type) {
            'window_in_cottage' => '#10b981', // зелений
            'balcony' => '#f59e0b', // помаранчевий
            'balcony_with_takeout' => '#ef4444', // червоний
            'turnkey_balcony' => '#8b5cf6', // фіолетовий
            'balcony_cladding' => '#ec4898', // рожевий
            'window' => '#3b82f6', // синій
            default => '#6b7280', // сірий
        };
    }
    
    protected function getType(): string
    {
        return 'line';
    }
}
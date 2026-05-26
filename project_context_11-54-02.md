# Контекст проекту
**Дата збору:** 2026-05-26 11:54:02
---

## Файл: app/Models/Lead.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    /**
     * ---------------------------------------------------------
     * OLD CRM CONNECTION
     * ---------------------------------------------------------
     */
    protected $connection = 'old_crm';

    /**
     * ---------------------------------------------------------
     * TABLE
     * ---------------------------------------------------------
     */
    protected $table = 'leads';

    /**
     * ---------------------------------------------------------
     * PRIMARY KEY
     * ---------------------------------------------------------
     */
    protected $primaryKey = 'id';

    /**
     * ---------------------------------------------------------
     * TIMESTAMPS
     * ---------------------------------------------------------
     */
    public $timestamps = false;

    /**
     * ---------------------------------------------------------
     * SECURITY
     * ---------------------------------------------------------
     * READ ONLY MODEL
     * ---------------------------------------------------------
     */
    protected $guarded = [];

    /**
     * ---------------------------------------------------------
     * BLOCK WRITE OPERATIONS
     * ---------------------------------------------------------
     */
    public function save(array $options = [])
    {
        return false;
    }

    public function delete()
    {
        return false;
    }
}```

## Файл: app/Filament/Pages/Dashboard/AdminDashboard.php
```php
<?php

namespace App\Filament\Pages\Dashboard;

use App\Services\Leads\LeadQueryService;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Support\Enums\MaxWidth;



class AdminDashboard extends Page implements HasTable
{
       use InteractsWithTable;
  protected \Filament\Support\Enums\Width|string|null $maxContentWidth = \Filament\Support\Enums\Width::Full;
 
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Admin Dashboard';

    protected static ?string $title = 'Admin Dashboard';

    protected string $view = 'filament.pages.dashboard.admin-dashboard';

    /**
     * ---------------------------------------------------------
     * ACCESS
     * ---------------------------------------------------------
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    /**
     * ---------------------------------------------------------
     * SIDEBAR NAVIGATION
     * ---------------------------------------------------------
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    /**
     * ---------------------------------------------------------
     * TABLE
     * ---------------------------------------------------------
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(
                app(LeadQueryService::class)->getQuery()
            )

            ->defaultSort('leads.id', 'desc')

            ->paginated([25, 50, 100])

            ->striped()

            ->columns([

                /**
                 * ---------------------------------------------------------
                 * №
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('id')
                    ->label('№')
                    ->sortable(),

                /**
                 * ---------------------------------------------------------
                 * ДАТА
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('created_at')
                    ->label('дата')
                    ->date('d.m.Y')
                    ->sortable(),

                /**
                 * ---------------------------------------------------------
                 * ЧАС
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('created_at')
                    ->label('час')
                    ->time('H:i:s'),

                /**
                 * ---------------------------------------------------------
                 * ДЖЕРЕЛО
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('utm_source')
                    ->label('джерело')
                    ->searchable(),

                /**
                 * ---------------------------------------------------------
                 * ПОДІЯ
                 * ---------------------------------------------------------
                 */
               Tables\Columns\TextColumn::make('source')
                  ->label('подія')
                  ->formatStateUsing(function (?string $state): string {

                      return match ($state) {

                          'call' => 'Дзвінок',
                          'office-visit' => 'Візит в офіс',
                          'binotel_chat' => 'Binotel chat',
                          'site' => 'Заявка з сайту',
                          'get_call_binotel' => 'Зворотній дзвінок',
                          'fb_lid' => 'Facebook lead',
                          'fb_chat' => 'Facebook chat',

                          default => '-',
                      };
                  })
                  ->badge(),

                /**
                 * ---------------------------------------------------------
                 * ПРИМІТКИ
                 * ---------------------------------------------------------
                 */
               Tables\Columns\TextColumn::make('comment')
                  ->label('примітки')
                  ->wrap()
                  ->limit(30)
                  ->toggleable(isToggledHiddenByDefault: true),

                /**
                 * ---------------------------------------------------------
                 * ABCD
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('abcd')
                    ->label('ABCD')
                    ->state('-'),

                /**
                 * ---------------------------------------------------------
                 * ЦІЛЬОВИЙ
                 * ---------------------------------------------------------
                 */
              Tables\Columns\TextColumn::make('lead_status')
    ->label('цільовий')
    ->formatStateUsing(function (?string $state): string {

        return match ($state) {

            'processing',
            'zamir',
            'vizyt_ofis',
            'accepted',
            'measuring' => 'цільовий',

            'not_targeted',
            'another_city',
            'reklamatsiya_amtech',
            'reklamatsiya' => 'не цільовий',

            'new' => 'невідомо',

            default => 'інше',
        };
    })
    ->badge()
    ->color(function (?string $state): string {

        return match ($state) {

            'processing',
            'zamir',
            'vizyt_ofis',
            'accepted',
            'measuring' => 'success',

            'not_targeted',
            'another_city',
            'reklamatsiya_amtech',
            'reklamatsiya' => 'danger',

            'new' => 'gray',

            default => 'warning',
        };
    }),

                /**
                 * ---------------------------------------------------------
                 * ТИП
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('type')
                    ->label('тип')
                    ->state('-'),

                /**
                 * ---------------------------------------------------------
                 * ІМʼЯ
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('name')
                    ->label('імʼя')
                    ->searchable(),

                /**
                 * ---------------------------------------------------------
                 * CALLBACK
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('comment_callback')
                    ->label('callback')
                    ->wrap()
                    ->limit(60),

                /**
                 * ---------------------------------------------------------
                 * EMAIL
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('email')
                    ->label('email')
                    ->searchable(),

                /**
                 * ---------------------------------------------------------
                 * ТЕЛЕФОН
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('phone')
                    ->label('телефон')
                    ->searchable(),

                /**
                 * ---------------------------------------------------------
                 * FOLLOW
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('follow')
                    ->label('follow')
                    ->state('-'),

                /**
                 * ---------------------------------------------------------
                 * ДАТА ПРОДАЖУ
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('success_date')
                    ->label('дата продажу')
                    ->date('d.m.Y'),

                /**
                 * ---------------------------------------------------------
                 * СТАТУС
                 * ---------------------------------------------------------
                 */ 
                 Tables\Columns\BadgeColumn::make('lead_status')
    ->label('статус')

    ->formatStateUsing(function (?string $state): string {

        return match ($state) {

            'new' => 'новий',

            'processing',
            'zamir',
            'vizyt_ofis',
            'measuring' => 'в роботі',

            'accepted' => 'продано',

            'canceled',
            'not_targeted',
            'another_city',
            'propushcheno',
            'reklamatsiya',
            'reklamatsiya_amtech' => 'скасовано',

            default => 'невідомо',
        };
    })

    ->color(function (?string $state): string {

        return match ($state) {

            'new' => 'gray',

            'processing',
            'zamir',
            'vizyt_ofis',
            'measuring' => 'warning',

            'accepted' => 'success',

            'canceled',
            'not_targeted',
            'another_city',
            'propushcheno',
            'reklamatsiya',
            'reklamatsiya_amtech' => 'danger',

            default => 'gray',
        };
    }),


                /**
                 * ---------------------------------------------------------
                 * СУМА
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('total_price')
                    ->label('сума')
                    ->money('UAH', divideBy: 1),

                /**
                 * ---------------------------------------------------------
                 * КОМЕНТАР
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('comment_sales_head')
                    ->label('коментар')
                    ->wrap()
                    ->limit(80),

                /**
                 * ---------------------------------------------------------
                 * ДЖЕРЕЛО ТРАФІКУ
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('utm_source')
                    ->label('джерело трафіку'),

                /**
                 * ---------------------------------------------------------
                 * КАМПАНІЯ
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('utm_campaign')
                    ->label('кампанія')
                    ->wrap()
                    ->limit(40),

                /**
                 * ---------------------------------------------------------
                 * ЦІЛЬ
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('utm_term')
                    ->label('ціль'),

                /**
                 * ---------------------------------------------------------
                 * GCLID
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('gclid')
                    ->label('gclid')
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}```

## Файл: app/Services/Leads/LeadQueryService.php
```php
<?php

namespace App\Services\Leads;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;

class LeadQueryService
{
    /**
     * ---------------------------------------------------------
     * GET QUERY
     * ---------------------------------------------------------
     * ONLY READ DATA
     * NO INSERT / UPDATE / DELETE
     * ---------------------------------------------------------
     */
    public function getQuery(): Builder
    {
        return Lead::query()

            ->from('leads')

            ->leftJoin(
                'clients',
                'clients.id',
                '=',
                'leads.client_id'
            )

            ->leftJoin(
                'orders',
                'orders.lead_id',
                '=',
                'leads.id'
            )

            ->select([

                'leads.id',

                'leads.source',

                'leads.created_at',

                'leads.status as lead_status',

                'leads.comment',

                'leads.comment_callback',

                'leads.utm_source',

                'leads.utm_campaign',

                'leads.utm_medium',

                'leads.gclid',

                'clients.name',

                'clients.phone',

                'clients.email',

                'orders.total_price',

                'orders.success_date',

                'orders.status as order_status',
            ]);
    }
}```


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'legacy_id', 'client_id', 'manager_id', 'source', 'application_type', 'stage', 'status', 'lost_reason', 'legacy_status', 'comment', 'comment_call', 'comment_callback', 'comment_sales_head', 'profile', 'is_restoration_program', 'restoration_status', 'advance_calculation1', 'advance_calculation2', 'advance_calculation3',
    'site_city', 'site_street', 'auto_address', 'site_house_number', 'site_apartment_number', 'site_floor',
])]
class Lead extends Model
{
    use \App\Models\Concerns\HasProcessSteps;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            // NULL = не вказано, 0 = ні, 1 = так (restorationProgramOptions)
            'is_restoration_program' => 'integer',
            // NULL, коли програма не «так»; 1–7 — статус по програмі (restorationStatusOptions)
            'restoration_status' => 'integer',
        ];
    }

    /**
     * The address of the OBJECT this lead is about — NOT the client's own
     * address (see Client::getFullAddressAttribute()). A client has one
     * fixed home/contact address; a given lead can be about a different
     * property entirely (a rental, a relative's place, ...). Falls back
     * to "—" rather than the client's address — silently substituting one
     * for the other would hide the fact that nobody has entered it yet.
     */
    public function getSiteAddressAttribute(): ?string
    {
        $parts = array_filter([
            $this->site_city,
            $this->site_street,
            $this->site_house_number ? 'буд. '.$this->site_house_number : null,
            $this->site_apartment_number ? 'кв. '.$this->site_apartment_number : null,
        ]);

        return $parts !== [] ? implode(', ', $parts) : null;
    }

    public function reminders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ManagerReminder::class)->orderBy('reminder_datetime');
    }

    public function marketingData(): HasOne
    {
        return $this->hasOne(LeadMarketingData::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(LeadCall::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(LeadMeasurement::class);
    }

    /**
     * The most recently created measurement appointment, if any — used by
     * the status/action bar on the Lead's edit page to decide whether to
     * still show "Створити заявку на замір" or the already-scheduled
     * details instead. Re-scheduling creates a NEW row rather than
     * editing the old one, so history of past appointments is kept.
     */
    public function latestMeasurement(): ?LeadMeasurement
    {
        return $this->measurements()->latest()->first();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * In normal flow a lead converts into exactly one order — but the FK
     * is not enforced as a strict 1:1 at the DB level (see
     * create_orders_table migration), so this mirrors latestMeasurement()
     * rather than assuming there can only ever be one.
     */
    public function latestOrder(): ?Order
    {
        return $this->orders()->latest()->first();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function serviceTypes(): BelongsToMany
    {
        return $this->belongsToMany(LeadServiceType::class);
    }

    /**
     * Manual-entry sources only (this lead was typed in by a manager, not
     * generated automatically). Website/ads sources (UTM, Google Ads, the
     * old system's site_source/utm_* columns) belong to a separate future
     * "сайт" module and will extend this list, not replace it.
     *
     * @return array<string, string>
     */
    public static function sourceOptions(): array
    {
        // First three = original manual options; the rest are the old CRM's
        // "Спосіб звернення" values that automatic channels write.
        return [
            'call' => 'Дзвінок',
            'site' => 'Заявка з сайту',
            'office_visit' => 'Візит в офіс',
            'referral' => 'Передали контакт',
            'binotel_chat' => 'Чат Бінотел',
            'binotel_call' => 'Замовили callback',
            'fb_lead_ads' => 'Заявка - Fb лід',
            'fb_chat' => 'Заявка - Fb чат',
        ];
    }

    /**
     * «Програма Відновлення» (leads.is_restoration_program). NULL = ще не питали клієнта.
     * Ті самі числа зберігає стара CRM — не змінювати без узгодження з нею.
     * 0 = звичайне замовлення; 1 = заявка йде по програмі (її статус — у restoration_status).
     *
     * @return array<int, string>
     */
    public static function restorationProgramOptions(): array
    {
        return [
            0 => 'ні, не по програмі',
            1 => 'так, по програмі',
        ];
    }

    /**
     * Статус заявки по програмі (leads.restoration_status), має сенс лише коли is_restoration_program = 1.
     * Ті самі числа зберігає стара CRM — не змінювати без узгодження з нею.
     *
     * @return array<int, string>
     */
    public static function restorationStatusOptions(): array
    {
        return [
            1 => 'нова: ще не оброблена',
            2 => 'думає',
            3 => 'чекає комісію або гроші',
            4 => 'в роботі (гроші є)',
            5 => 'ігнорує',
            6 => 'відмова: дорого',
            7 => 'відмова: не підходить програма',
        ];
    }

    /**
     * Window profile types (old lead card "Тип профілю").
     *
     * @return array<string, string>
     */
    public static function profileOptions(): array
    {
        return [
            'rehau_60' => 'rehau 60', 'rehau_70' => 'rehau 70', 'rehau_80' => 'rehau 80',
            'rehau_sliding' => 'rehau sliding', 'openteck_delux_60' => 'OpenTeck De-Lux 60',
            'openteck_elite_70' => 'OpenTeck ELITE 70', 'openteck_pro_76' => 'OpenTeck Pro 76',
            'salamander_proevolution_72' => 'Salamander proEvolution 72', 'salamander_bluevolution_82' => 'Salamander bluEvolution 82',
            'salamander_bluevolution_92' => 'Salamander bluEvolution 92',
            'ukr_teple' => 'ukr teple', 'ukr_holodne' => 'ukr holodne', 'ukr_sliding' => 'ukr sliding',
            'ne_vikna' => 'не вікна',
            // Послуги без профілю (напр. внутрішня обшивка балкону): профілю немає, у полі стоїть «Послуга».
            'service' => 'Послуга',
        ];
    }

    /**
     * Site/brand of origin (lives in lead_marketing_data.site_source).
     *
     * @return array<string, array<string, string>>
     */
    public static function siteSourceOptions(): array
    {
        return [
            'Балкони та Вікна' => [
                'btv' => 'сайт "Балкони та Вікна"', 'application_site_btv' => 'Заявка з сайту "Балкони та Вікна"',
                'chat_binotel_btv' => 'чат з сайту "Балкони та Вікна"', 'recommendation_btv' => 'по рекомендації "Балкони та Вікна"',
                'repeated-appeal_btv' => 'повторне звернення "Балкони та Вікна"', 'fb_btv' => 'фейсбук "Балкони та Вікна"',
            ],
            'Авікон' => [
                'avicon' => 'сайт "Авікон"', 'application_site_avicon' => 'Заявка з сайту "Авікон"',
                'chat_binotel_avicon' => 'чат з сайту "Авікон"', 'recommendation_avicon' => 'по рекомендації "Авікон"',
                'repeated-appeal_avicon' => 'повторне звернення "Авікон"', 'fb_avicon' => 'фейсбук "Авікон"',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function applicationTypeOptions(): array
    {
        return [
            'new' => 'Нова заявка',
            'repeat' => 'Повторне звернення',
        ];
    }

    /**
     * Sales funnel position. Deliberately designed fresh rather than
     * copied from the old system's messy `leads.status` enum — see the
     * create_leads_table migration docblock for why. Always moves
     * forward; whether the lead is actually still open/won/lost is the
     * separate `status` field.
     *
     * @return array<string, string>
     */
    /**
     * Етапи, на яких заявка ще не кваліфікована (до «Контакт»): у Google Ads конверсія й кваліфікований клієнт для аудиторій
     * з'являються лише ПІСЛЯ них. Єдине джерело для ConversionPlanner, AudienceClassify та експорту лідів.
     */
    public const PRE_QUALIFIED_STAGES = ['new', 'assigned', 'unreachable'];

    public static function stageOptions(): array
    {
        return [
            'new' => 'Новий',
            // Менеджер натиснув «Прийняти заявку» (EditLead::acceptLeadAction). Це ще НЕ кваліфікація для реклами.
            'assigned' => 'Прийнято',
            // Після 2 невдалих дзвінків менеджер натискає «Клієнт не відповідає» (EditLead). У воронці стоїть замість «Контакт», кваліфікацією НЕ є.
            'unreachable' => 'Клієнт не відповідає',
            'contacted' => 'Контакт',
            'measurement_scheduled' => 'Замір',
            'measurement_done' => 'Заміряно',
            'proposal_sent' => 'КП',
            'negotiation' => 'Узгодження',
            // Final stage of the lead funnel — once the "Замовлення"
            // module exists, reaching this stage should actually create/
            // link an Order; for now it's just the last step of the
            // status bar, set manually like any other stage.
            'closing' => 'Замовлення',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'open' => 'В роботі',
            'won' => 'Угода укладена',
            'lost' => 'Втрачено',
        ];
    }
}

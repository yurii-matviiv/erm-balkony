<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'legacy_id', 'client_id', 'manager_id', 'source', 'application_type', 'stage', 'status', 'lost_reason', 'legacy_status', 'comment',
    'site_city', 'site_street', 'site_house_number', 'site_apartment_number', 'site_floor',
])]
class Lead extends Model
{
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
        return [
            'call' => 'Дзвінок',
            'office_visit' => 'Візит в офіс',
            'referral' => 'Передали контакт',
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
    public static function stageOptions(): array
    {
        return [
            'new' => 'Новий лід',
            'contacted' => 'Контакт встановлено',
            'measurement_scheduled' => 'Замір призначено',
            'measurement_done' => 'Замір проведено',
            'proposal_sent' => 'КП надіслано',
            'negotiation' => 'Узгодження умов',
            // Final stage of the lead funnel — once the "Замовлення"
            // module exists, reaching this stage should actually create/
            // link an Order; for now it's just the last step of the
            // status bar, set manually like any other stage.
            'closing' => 'Конвертовано в замовлення',
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

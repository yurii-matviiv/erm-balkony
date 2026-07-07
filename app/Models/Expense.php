<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * General company expense / income entry (not tied to a specific order).
 *
 * Mirrors the old CRM's `orders_payments` rows where `order_id` was NULL
 * (office costs, salaries, taxes, marketing spend, etc.).
 *
 * @see database/migrations/2026_07_02_100000_create_expenses_table.php
 */
class Expense extends Model
{
    protected $fillable = [
        'direction', 'payment_method', 'amount', 'status', 'classification_status',
        'category', 'sub_category', 'comment', 'created_by', 'paid_at',
        'privatbank_num', 'fop_account_id', 'user_id', 'legacy_id',
    ];

    protected $casts = [
        'paid_at' => 'date',
        'amount'  => 'decimal:2',
    ];

    // ──────────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Author of the entry — see CLAUDE.md "Платежі — принципи", принцип 4. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ──────────────────────────────────────────────
    // Label dictionaries (for UI selects / display)
    // ──────────────────────────────────────────────

    public static function directionOptions(): array
    {
        return [
            'outgo'  => 'Витрата',
            'income' => 'Прихід',
        ];
    }

    public static function paymentMethodOptions(): array
    {
        return [
            'cash'     => 'Готівка',
            'cashless' => 'Безготівковий',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'received' => 'Підтверджено',
            'pending'  => 'Заплановано',
        ];
    }

    /** Top-level category options */
    public static function categoryOptions(): array
    {
        return [
            'telephone'   => 'Телефонія',
            'office'      => 'Офіс',
            'marketing'   => 'Маркетинг',
            'tax'         => 'Податки',
            'salary'      => 'Зарплата',
            'recruitment' => 'Рекрутинг',
            'order'       => 'Замовлення (інше)',
        ];
    }

    /**
     * Sub-category options keyed by parent category.
     * Covers ALL known values from the old CRM (expense_add.php + payment-types.php).
     * Used in UI selects and as display labels in analytics.
     */
    public static function subCategoryOptions(): array
    {
        return [
            'telephone' => [
                'binotel'   => 'Бінотел',
                'sim_cards' => 'Сім-карти',
            ],
            'office' => [
                'rent'         => 'Оренда',
                'electricity'  => 'Електроенергія',
                'internet'     => 'Інтернет',
                'water'        => 'Вода',
                'coffee'       => 'Кава',
                'stationery'   => 'Канцелярія',
                'collection'   => 'Інкасація',
                'other_office' => 'Інше (офіс)',
            ],
            'marketing' => [
                'google'               => 'Google',
                // Automatic Google Ads card charges imported from the old
                // google_ads_pay bank journal — kept SEPARATE from manual
                // 'google' contractor invoices, per explicit user request.
                'google_ads'           => 'Google Ads (списання)',
                'facebook'             => 'Facebook',
                'instagram'            => 'Instagram',
                'outsourced_marketing' => 'Аутсорс-маркетинг',
                'videographer'         => 'Відеограф',
            ],
            'tax' => [
                'single_tax' => 'Єдиний податок',
            ],
            'salary' => [
                'head_of_sales' => 'Керівник відділу продажів',
                'manager'       => 'Менеджер',
            ],
            'recruitment' => [
                'hiring_manager'            => 'Найм менеджера',
                'hiring_installer'          => 'Найм монтажника',
                'hiring_assistant_director' => 'Найм помічника керівника',
                'videographer'              => 'Відеограф (рекрутинг)',
            ],
            'order' => [
                'measurement' => 'Замір без замовлення',
                'other'       => 'Інше',
            ],
        ];
    }

    /**
     * Human-readable label for a category/sub_category combination.
     * Example: categoryLabel('office', 'rent') → 'Офіс / Оренда'
     */
    public static function categoryLabel(?string $category, ?string $subCategory = null): string
    {
        $cats = self::categoryOptions();
        $subs = self::subCategoryOptions();

        $cat = $cats[$category] ?? ($category ?? '—');
        $sub = $subCategory ? ($subs[$category][$subCategory] ?? $subCategory) : null;

        return $sub ? "{$cat} / {$sub}" : $cat;
    }
}

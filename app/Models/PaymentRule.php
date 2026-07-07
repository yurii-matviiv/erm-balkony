<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One admin-editable payment-distribution rule — see the
 * create_payment_rules_table migration docblock and the "Платіжні
 * правила" page. Not consumed by any pipeline yet (foundation for the
 * upcoming bank-transactions import).
 */
#[Fillable([
    'name', 'is_active', 'priority',
    'match_field', 'match_type', 'pattern',
    'set_category', 'set_sub_category', 'note',
])]
class PaymentRule extends Model
{
    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /** @return array<string, string> */
    public static function matchFieldOptions(): array
    {
        return [
            'comment' => 'Призначення платежу (коментар)',
            'counterparty_name' => 'Назва контрагента',
            'counterparty_iban' => 'IBAN контрагента',
        ];
    }

    /** @return array<string, string> */
    public static function matchTypeOptions(): array
    {
        return [
            'contains' => 'Містить',
            'starts_with' => 'Починається з',
            'equals' => 'Дорівнює',
            'regex' => 'Регулярний вираз',
        ];
    }
}

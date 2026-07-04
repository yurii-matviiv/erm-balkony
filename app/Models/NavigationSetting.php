<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One row = an override of a navigation item's group/order/visibility for
 * one role. See create_navigation_settings_table migration and
 * App\Services\Navigation\NavigationResolver.
 */
#[Fillable(['role', 'item_key', 'group_label', 'group_sort', 'item_sort', 'is_active'])]
class NavigationSetting extends Model
{
    protected $casts = [
        'is_active' => 'boolean',
    ];
}

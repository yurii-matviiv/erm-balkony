<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Global rename of a navigation item — see create_navigation_labels_table
 * migration docblock for why this is separate from the per-role
 * NavigationSetting.
 */
#[Fillable(['item_key', 'label'])]
class NavigationLabel extends Model
{
    //
}

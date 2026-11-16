<?php

namespace App\Filament\Manager\Pages;

use App\Filament\Concerns\RequiresViewPermission;
use Filament\Pages\Page;

/**
 * Empty stand-in for a manager-panel page that exists in the OLD CRM menu but
 * is not rebuilt yet. Lets the sidebar match the old system 1:1 from day one;
 * each subclass is replaced by the real page later (keep the class name, so
 * its `View:<ClassName>` permission and sidebar settings survive).
 *
 * Lives outside App\Filament\Pages on purpose: the admin panel auto-discovers
 * that folder, and these pages belong to the manager panel only.
 */
abstract class PlaceholderPage extends Page
{
    use RequiresViewPermission;

    protected string $view = 'filament.pages.placeholder';

    /** Old-CRM route this page stands in for (shown on the stub for orientation). */
    protected static string $legacyRoute = '';

    public function getLegacyRoute(): string
    {
        return static::$legacyRoute;
    }
}

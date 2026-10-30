<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\MaxWidth;

class Dashboard extends BaseDashboard
{
    public function getColumns(): int|string|array
    {
        return 1;
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }
}

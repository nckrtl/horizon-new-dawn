<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Jobs;

use NckRtl\HorizonNewDawn\Support\NavigationItem;

enum JobListType: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Silenced = 'silenced';

    public function title(): string
    {
        return match ($this) {
            self::Pending => 'Pending Jobs',
            self::Completed => 'Completed Jobs',
            self::Silenced => 'Silenced Jobs',
        };
    }

    public function navigation(): NavigationItem
    {
        return match ($this) {
            self::Pending => NavigationItem::Pending,
            self::Completed => NavigationItem::Completed,
            self::Silenced => NavigationItem::Silenced,
        };
    }
}

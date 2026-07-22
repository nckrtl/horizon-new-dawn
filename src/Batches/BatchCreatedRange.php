<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Batches;

use Illuminate\Support\Facades\Date;

enum BatchCreatedRange: string
{
    case LastHour = 'hour';
    case Last24Hours = 'day';
    case Last7Days = 'week';
    case Last30Days = 'month';

    public function cutoffTimestamp(): int
    {
        $hours = match ($this) {
            self::LastHour => 1,
            self::Last24Hours => 24,
            self::Last7Days => 24 * 7,
            self::Last30Days => 24 * 30,
        };

        return Date::now()->subHours($hours)->getTimestamp();
    }
}

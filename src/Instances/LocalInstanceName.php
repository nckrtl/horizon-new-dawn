<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Instances;

use Laravel\Horizon\MasterSupervisor;

final class LocalInstanceName
{
    public static function matches(string $instance): bool
    {
        $prefix = MasterSupervisor::basename().'-';

        if (! str_starts_with($instance, $prefix)) {
            return false;
        }

        return preg_match('/\A[A-Za-z0-9]{4}\z/', substr($instance, strlen($prefix))) === 1;
    }
}

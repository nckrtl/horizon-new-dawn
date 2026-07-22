<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Laravel\Horizon\Http\Controllers\HomeController as HorizonHomeController;

final class HomeController extends HorizonHomeController
{
    public function index(?string $view = null): never
    {
        abort(404);
    }
}

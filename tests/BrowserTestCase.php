<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithWorkbench;

abstract class BrowserTestCase extends TestCase
{
    use RefreshDatabase, WithWorkbench;
}

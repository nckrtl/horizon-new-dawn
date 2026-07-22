<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use NckRtl\HorizonNewDawn\Support\FrameworkCapabilities;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureQueuePausingIsSupported
{
    public function __construct(
        private FrameworkCapabilities $capabilities,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->capabilities->queuePausing, 404);

        return $next($request);
    }
}

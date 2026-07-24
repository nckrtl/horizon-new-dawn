<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\TagRepository;
use Laravel\Horizon\Http\Controllers\MonitoringController as HorizonMonitoringController;
use NckRtl\HorizonNewDawn\Monitoring\Actions\MonitorTag;
use NckRtl\HorizonNewDawn\Monitoring\Actions\StopMonitoringTag;

final class MonitoringApiController extends HorizonMonitoringController
{
    public function __construct(
        JobRepository $jobs,
        TagRepository $tags,
        private readonly MonitorTag $monitor,
        private readonly StopMonitoringTag $stop,
    ) {
        parent::__construct($jobs, $tags);
    }

    public function store(Request $request): void
    {
        $tag = $request->input('tag');

        if (! is_string($tag)) {
            throw ValidationException::withMessages([
                'tag' => 'The tag must be a string.',
            ]);
        }

        try {
            $this->monitor->handle($tag);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'tag' => $exception->getMessage(),
            ]);
        }
    }

    public function destroy(mixed $tag): void
    {
        try {
            $this->stop->handle($tag);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'tag' => $exception->getMessage(),
            ]);
        }
    }
}

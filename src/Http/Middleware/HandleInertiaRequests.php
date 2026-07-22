<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Middleware;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use NckRtl\HorizonNewDawn\Assets\AssetManifest;
use NckRtl\HorizonNewDawn\Monitoring\MonitoringData;
use NckRtl\HorizonNewDawn\Support\Data\HorizonShellData;
use NckRtl\HorizonNewDawn\Support\Data\NavigationCountsData;
use NckRtl\HorizonNewDawn\Support\FrameworkCapabilities;
use NckRtl\HorizonNewDawn\Support\HorizonRuntime;
use NckRtl\HorizonNewDawn\Support\NavigationCounts;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'horizon-new-dawn::app';

    public function __construct(
        private readonly HorizonRuntime $runtime,
        private readonly NavigationCounts $navigationCounts,
        private readonly MonitoringData $monitoring,
        private readonly Application $application,
        private readonly AssetManifest $assets,
        private readonly FrameworkCapabilities $capabilities,
    ) {}

    public function version(Request $request): string
    {
        return $this->assets->version();
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'flash' => [
                'success' => fn (): ?string => $this->flashMessage($request, 'toast.success'),
                'error' => fn (): ?string => $this->flashMessage($request, 'toast.error'),
            ],
            'horizon' => function (): HorizonShellData {
                $status = $this->runtime->status();

                return new HorizonShellData(
                    baseUrl: route('horizon-new-dawn.dashboard'),
                    pollInterval: (int) config('horizon-new-dawn.poll_interval'),
                    status: $status,
                    processing: $this->runtime->isProcessing($status),
                    maintenanceMode: $this->application->isDownForMaintenance(),
                    capabilities: $this->capabilities,
                );
            },
            'monitoredTags' => fn (): array => $this->monitoring->monitoredTags(),
            'navigationCounts' => Inertia::defer(
                fn (): NavigationCountsData => $this->navigationCounts->get(),
                'navigation',
            ),
        ];
    }

    private function flashMessage(Request $request, string $key): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $message = $request->session()->get($key);

        return is_string($message) ? $message : null;
    }
}

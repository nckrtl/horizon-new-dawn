<?php

declare(strict_types=1);

use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Collection;
use Laravel\Horizon\Contracts\JobRepository;
use NckRtl\HorizonNewDawn\FailedJobs\Actions\ClearFailedJobs;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardExpects;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

it('scans raw failed-job windows before clearing unique hydrated jobs', function (): void {
    $jobs = mockDashboardContract(JobRepository::class);
    $failedJobs = mockDashboardContract(FailedJobProviderInterface::class);

    dashboardExpects($jobs, 'countFailed', times: 'once', value: 51, ordered: true);
    dashboardExpects($jobs, 'getFailed', ['-1'], 'once', new Collection, ordered: true);
    dashboardExpects($jobs, 'getFailed', ['49'], 'once', new Collection([
        horizonJob(50, 'failed-50'),
        horizonJob(51, 'failed-50'),
    ]), ordered: true);
    dashboardExpects($jobs, 'deleteFailed', ['failed-50'], 'once', null, ordered: true);
    dashboardExpects($failedJobs, 'forget', ['failed-50'], 'once', null, ordered: true);

    expect((new ClearFailedJobs($jobs, $failedJobs))->handle())->toBe(1);
});

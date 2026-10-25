<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('fails clearly before reading Horizon when the baseline manifest is missing', function (): void {
    withSyncCase(function (HorizonSyncCase $case): void {
        $result = runHorizonSync($case->project, $case->remote);

        expect($result->getExitCode())->toBe(64)
            ->and($result->getErrorOutput())->toContain('Missing horizon-sync.json. Record a baseline before detection.')
            ->and($result->getOutput())->toBe('')
            ->and(file_exists($case->project.'/horizon-sync.json'))->toBeFalse();
    });
});

it('rejects a non-detect invocation without creating a manifest', function (): void {
    withSyncCase(function (HorizonSyncCase $case): void {
        file_put_contents($case->project.'/horizon-sync.json', '{"synced_tag":"v5.50.1"}');
        $result = runHorizonSync($case->project, $case->remote, ['detect', 'now']);

        expect($result->getExitCode())->toBe(64)
            ->and($result->getErrorOutput())->toContain('Usage: scripts/horizon-sync detect')
            ->and(file_get_contents($case->project.'/horizon-sync.json'))->toBe('{"synced_tag":"v5.50.1"}');
    });
});

it('stops when the synced tag is not a Horizon tag', function (): void {
    withSyncCase(function (HorizonSyncCase $case): void {
        tagHorizonSnapshot($case->remote, 'v5.50.0', 'release/v5.50.0');
        writeSyncTag($case->project, 'v5.49.0');
        $result = runHorizonSync($case->project, $case->remote);

        expect($result->getExitCode())->toBe(70)
            ->and($result->getErrorOutput())->toContain('Synced tag v5.49.0 is not a Horizon tag');
    });
});

it('reports no candidate when the synced tag is already the newest stable tag', function (): void {
    withSyncCase(function (HorizonSyncCase $case): void {
        tagHorizonSnapshot($case->remote, 'v5.50.0', 'release/v5.50.0');
        tagHorizonSnapshot($case->remote, 'v5.50.1', 'release/v5.50.1');
        writeSyncTag($case->project, 'v5.50.1');

        expect(syncCandidates($case->project, $case->remote))->toBe([
            'schema' => 1,
            'synced_tag' => 'v5.50.1',
            'tag' => null,
            'candidates' => [],
        ]);
    });
});

it('emits one release candidate for the real v5.50.1 tag when its dashboard surface is unchanged', function (): void {
    withSyncCase(function (HorizonSyncCase $case): void {
        tagHorizonSnapshot($case->remote, 'v5.50.0', 'release/v5.50.0');
        tagHorizonSnapshot($case->remote, 'v5.50.1', 'release/v5.50.1');
        writeSyncTag($case->project, 'v5.50.0');

        expect(syncCandidates($case->project, $case->remote)['candidates'])->toBe([
            ['kind' => 'release', 'tag' => 'v5.50.1', 'surface' => null, 'change' => null, 'id' => 'v5.50.1'],
        ]);
    });
});

it('detects only the next tag and the real watch config key added in v5.43.0', function (): void {
    withSyncCase(function (HorizonSyncCase $case): void {
        tagHorizonSnapshot($case->remote, 'v5.42.0', 'watch/v5.42.0');
        tagHorizonSnapshot($case->remote, 'v5.43.0', 'watch/v5.43.0');
        tagHorizonSnapshot($case->remote, 'v5.50.0', 'release/v5.50.0');
        writeSyncTag($case->project, 'v5.42.0');
        $decoded = syncCandidates($case->project, $case->remote);

        expect($decoded['tag'])->toBe('v5.43.0')
            ->and($decoded['candidates'])->toBe([
                ['kind' => 'feature', 'tag' => 'v5.43.0', 'surface' => 'config', 'change' => 'add', 'id' => 'watch'],
            ]);
    });
});

it('keeps an unchanged earlier tag ahead of the real v5.50.0 screen diff', function (): void {
    withSyncCase(function (HorizonSyncCase $case): void {
        tagHorizonSnapshot($case->remote, 'v5.48.3', 'screens/v5.49.0');
        tagHorizonSnapshot($case->remote, 'v5.49.0', 'screens/v5.49.0');
        tagHorizonSnapshot($case->remote, 'v5.50.0', 'screens/v5.50.0');
        writeSyncTag($case->project, 'v5.48.3');

        expect(syncCandidates($case->project, $case->remote)['candidates'])->toBe([
            ['kind' => 'release', 'tag' => 'v5.49.0', 'surface' => null, 'change' => null, 'id' => 'v5.49.0'],
        ]);

        writeSyncTag($case->project, 'v5.49.0');

        expect(syncCandidates($case->project, $case->remote)['candidates'])->toBe([
            ['kind' => 'feature', 'tag' => 'v5.50.0', 'surface' => 'screen', 'change' => 'modify', 'id' => 'resources/js/screens/recentJobs/job-row.vue'],
            ['kind' => 'feature', 'tag' => 'v5.50.0', 'surface' => 'screen', 'change' => 'modify', 'id' => 'resources/js/screens/recentJobs/job.vue'],
        ]);
    });
});

it('splits the real v5.12.0 surface diff into one candidate per changed item', function (): void {
    withSyncCase(function (HorizonSyncCase $case): void {
        tagHorizonSnapshot($case->remote, 'v5.11.0', 'silenced/v5.11.0');
        tagHorizonSnapshot($case->remote, 'v5.12.0', 'silenced/v5.12.0');
        writeSyncTag($case->project, 'v5.11.0');

        expect(syncCandidates($case->project, $case->remote)['candidates'])->toBe([
            ['kind' => 'feature', 'tag' => 'v5.12.0', 'surface' => 'route', 'change' => 'add', 'id' => 'GET horizon.silenced-jobs.index'],
            ['kind' => 'feature', 'tag' => 'v5.12.0', 'surface' => 'controller', 'change' => 'add', 'id' => 'SilencedJobsController@index'],
            ['kind' => 'feature', 'tag' => 'v5.12.0', 'surface' => 'screen', 'change' => 'modify', 'id' => 'resources/js/screens/recentJobs/index.vue'],
            ['kind' => 'feature', 'tag' => 'v5.12.0', 'surface' => 'screen', 'change' => 'modify', 'id' => 'resources/js/screens/recentJobs/job-row.vue'],
            ['kind' => 'feature', 'tag' => 'v5.12.0', 'surface' => 'screen', 'change' => 'modify', 'id' => 'resources/js/screens/recentJobs/job.vue'],
            ['kind' => 'feature', 'tag' => 'v5.12.0', 'surface' => 'config', 'change' => 'add', 'id' => 'silenced'],
        ]);
    });
});

final class HorizonSyncCase
{
    public function __construct(public string $directory, public string $project, public string $remote) {}
}

function withSyncCase(callable $callback): void
{
    $directory = sys_get_temp_dir().'/horizon-sync-'.bin2hex(random_bytes(6));
    $project = $directory.'/project';
    $remote = $directory.'/horizon with spaces';
    mkdir($project.'/scripts', 0755, true);
    mkdir($remote);
    copy(dirname(__DIR__, 2).'/scripts/horizon-sync', $project.'/scripts/horizon-sync');
    chmod($project.'/scripts/horizon-sync', 0755);
    gitSync(['init'], $remote);
    gitSync(['config', 'user.email', 'horizon-sync@example.com'], $remote);
    gitSync(['config', 'user.name', 'Horizon Sync'], $remote);
    gitSync(['config', 'commit.gpgsign', 'false'], $remote);
    try {
        $callback(new HorizonSyncCase($directory, $project, $remote));
    } finally {
        (new Process(['rm', '-rf', '--', $directory]))->mustRun();
    }
}

function writeSyncTag(string $project, string $tag): void
{
    file_put_contents($project.'/horizon-sync.json', json_encode(['synced_tag' => $tag], JSON_THROW_ON_ERROR));
}

/**
 * @param  list<string>  $arguments
 */
function gitSync(array $arguments, string $repo): void
{
    $process = new Process(['git', '-C', $repo, ...$arguments]);
    $process->mustRun();
}

function tagHorizonSnapshot(string $repo, string $tag, string $snapshot): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($repo, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $file) {
        $path = $file->getPathname();
        if (str_contains($path, '/.git/') || str_ends_with($path, '/.git')) {
            continue;
        }
        if ($file->isDir()) {
            @rmdir($path);
        } else {
            unlink($path);
        }
    }
    $source = dirname(__DIR__).'/Fixtures/horizon-sync/'.$snapshot;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (! $file->isFile()) {
            continue;
        }
        $relative = substr($file->getPathname(), strlen($source) + 1);
        $relative = preg_replace('/\.snapshot$/', '', $relative) ?? $relative;
        $target = $repo.'/'.$relative;
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        copy($file->getPathname(), $target);
    }
    gitSync(['add', '-A'], $repo);
    $status = new Process(['git', '-C', $repo, 'status', '--porcelain']);
    $status->mustRun();
    if ($status->getOutput() !== '') {
        $commit = new Process(['git', '-c', 'commit.gpgsign=false', '-C', $repo, 'commit', '-m', $tag]);
        $commit->mustRun();
    }
    gitSync(['tag', $tag], $repo);
}

/**
 * @param  list<string>|null  $arguments
 */
function runHorizonSync(string $project, string $remote, ?array $arguments = null): Process
{
    $process = new Process(
        [$project.'/scripts/horizon-sync', ...($arguments ?? ['detect'])],
        $project,
        ['HORIZON_SYNC_REMOTE' => $remote],
    );
    $process->run();

    return $process;
}

/**
 * @return array<string, mixed>
 */
function syncCandidates(string $project, string $remote): array
{
    $before = file_get_contents($project.'/horizon-sync.json');
    $result = runHorizonSync($project, $remote);
    expect($result->getExitCode())->toBe(0, $result->getErrorOutput())
        ->and(file_get_contents($project.'/horizon-sync.json'))->toBe($before);
    $decoded = json_decode($result->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    if (! is_array($decoded)) {
        throw new RuntimeException('Detection did not return an object.');
    }

    return $decoded;
}

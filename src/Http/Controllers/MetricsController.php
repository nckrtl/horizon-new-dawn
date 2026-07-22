<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use NckRtl\HorizonNewDawn\Metrics\MetricsData;
use NckRtl\HorizonNewDawn\Metrics\MetricType;
use NckRtl\HorizonNewDawn\Support\Data\PageMetaData;
use NckRtl\HorizonNewDawn\Support\NavigationItem;

final class MetricsController
{
    public function redirect(): RedirectResponse
    {
        return redirect()->route('horizon-new-dawn.metrics.index', [
            'type' => MetricType::Jobs->value,
        ]);
    }

    public function index(MetricsData $metrics, string $type): Response
    {
        $metricType = MetricType::from($type);
        $page = $metrics->index($metricType);

        return Inertia::render('Metrics/Index', [
            'meta' => new PageMetaData('Metrics', NavigationItem::Metrics),
            'type' => $metricType->value,
            'metrics' => [
                'data' => $page->metrics,
                'available' => $page->available,
                'message' => $page->message,
            ],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use NckRtl\HorizonNewDawn\Metrics\MetricsData;
use NckRtl\HorizonNewDawn\Metrics\MetricType;
use NckRtl\HorizonNewDawn\Support\Data\PageMetaData;
use NckRtl\HorizonNewDawn\Support\NavigationItem;

final class MetricController
{
    public function show(MetricsData $metrics, string $type, string $slug): Response
    {
        $metricType = MetricType::from($type);
        $preview = $metrics->preview($metricType, $slug);

        return Inertia::render('Metrics/Show', [
            'meta' => new PageMetaData("Metrics for {$slug}", NavigationItem::Metrics),
            'type' => $metricType->value,
            'name' => $slug,
            'preview' => [
                'data' => $preview->snapshots,
                'available' => $preview->available,
                'message' => $preview->message,
            ],
        ]);
    }
}

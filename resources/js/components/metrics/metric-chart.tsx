import { useMemo } from "react";
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from "recharts";

import { MetricsNavigationIcon } from "@/components/navigation-icons";
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from "@/components/ui/chart";
import {
  Empty,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty";
import type { MetricSnapshot } from "@/types/metrics";

type MetricKind = "throughput" | "runtime";

const metricChartConfigs = {
  throughput: {
    value: {
      label: "Jobs per minute",
      color: "var(--chart-1)",
    },
  },
  runtime: {
    value: {
      label: "Seconds",
      color: "var(--chart-1)",
    },
  },
} satisfies Record<MetricKind, ChartConfig>;

const timeFormatter = new Intl.DateTimeFormat(undefined, {
  hour: "2-digit",
  minute: "2-digit",
});
const runtimeFormatter = new Intl.NumberFormat(undefined, { maximumFractionDigits: 3 });

function formatTime(timestamp: number) {
  return timeFormatter.format(new Date(timestamp * 1000));
}

type MetricTooltipPayload = readonly {
  payload?: {
    timestamp?: unknown;
  };
}[];

export function formatMetricTooltipLabel(value: unknown, payload?: MetricTooltipPayload) {
  const timestamp = Number(payload?.[0]?.payload?.timestamp);

  if (Number.isFinite(timestamp)) {
    return formatTime(timestamp);
  }

  return typeof value === "string" ? value : "";
}

export function MetricChart({
  kind,
  snapshots,
}: {
  kind: MetricKind;
  snapshots: readonly MetricSnapshot[];
}) {
  const title = kind === "throughput" ? "Throughput" : "Runtime";
  const chartConfig = metricChartConfigs[kind];
  const data = useMemo(
    () =>
      snapshots.map((snapshot) => ({
        timestamp: snapshot.timestamp,
        value: snapshot[kind],
      })),
    [kind, snapshots],
  );

  if (snapshots.length === 0) {
    return (
      <Empty className="min-h-64">
        <EmptyHeader>
          <EmptyMedia variant="icon">
            <MetricsNavigationIcon aria-hidden="true" />
          </EmptyMedia>
          <EmptyTitle>Not Enough Data</EmptyTitle>
          <EmptyDescription>
            Horizon needs at least one metrics snapshot to draw this chart.
          </EmptyDescription>
        </EmptyHeader>
      </Empty>
    );
  }

  const latest = snapshots.at(-1);
  const latestDescription =
    kind === "throughput"
      ? `Latest throughput: ${integer(latest?.throughput ?? 0)} jobs per minute.`
      : latest?.runtime === null || latest?.runtime === undefined
        ? "Latest runtime: no observation."
        : `Latest runtime: ${runtimeFormatter.format(latest.runtime)} seconds.`;

  return (
    <div>
      <span className="sr-only">{latestDescription}</span>
      <ChartContainer
        config={chartConfig}
        className="h-64 w-full aspect-auto"
        role="img"
        aria-label={`${title} metric chart`}
        data-animation="disabled"
        initialDimension={{ width: 960, height: 256 }}
      >
        <BarChart
          accessibilityLayer
          data={data}
          margin={{ top: 12, right: 16, bottom: 0, left: 0 }}
          barCategoryGap="20%"
        >
          <CartesianGrid vertical={false} strokeDasharray="3 3" />
          <XAxis
            dataKey="timestamp"
            axisLine={false}
            tickLine={false}
            tickMargin={10}
            minTickGap={32}
            tickFormatter={(value: number) => formatTime(value)}
          />
          <YAxis
            axisLine={false}
            tickLine={false}
            tickMargin={10}
            width={64}
            tick={{ dx: -24, textAnchor: "start" }}
            tickFormatter={(value: number) =>
              kind === "throughput" ? integer(value) : `${runtimeFormatter.format(value)}s`
            }
          />
          <ChartTooltip
            cursor={false}
            content={
              <ChartTooltipContent indicator="line" labelFormatter={formatMetricTooltipLabel} />
            }
          />
          <Bar
            dataKey="value"
            fill="var(--color-value)"
            fillOpacity={0.82}
            maxBarSize={28}
            radius={[3, 3, 0, 0]}
            isAnimationActive={false}
          />
        </BarChart>
      </ChartContainer>
    </div>
  );
}

function integer(value: number) {
  return Math.round(value).toLocaleString();
}

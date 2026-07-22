import { describe, expect, it } from "vitest";

import { store as retryBatch } from "@/generated/routes/horizon-new-dawn/batches/retry";
import { index as dashboardIndex } from "@/generated/routes/horizon-new-dawn/dashboard";
import { store as retryAllFailed } from "@/generated/routes/horizon-new-dawn/failed-jobs/retry-all";
import { store as retryFailed } from "@/generated/routes/horizon-new-dawn/failed-jobs/retry";
import { show as jobShow } from "@/generated/routes/horizon-new-dawn/jobs";
import { show as metricShow } from "@/generated/routes/horizon-new-dawn/metrics";
import { show as monitoringShow } from "@/generated/routes/horizon-new-dawn/monitoring";
import { resolveHorizonRoute } from "@/lib/horizon-route";

describe("resolveHorizonRoute", () => {
  const routes = [
    [dashboardIndex({ query: { period: 60 } }), "/dashboard?period=60"],
    [
      monitoringShow({ tag: "mail", status: "completed" }, { query: { starting_at: "cursor" } }),
      "/monitoring/mail/completed?starting_at=cursor",
    ],
    [metricShow({ type: "jobs", slug: "SendInvoice" }), "/metrics/jobs/SendInvoice"],
    [jobShow({ type: "failed", job: "job-123" }), "/jobs/failed/job-123"],
    [retryFailed("job-123"), "/failed/job-123/retry"],
    [retryAllFailed(), "/failed/retry-all"],
    [retryBatch("batch-123"), "/batches/batch-123/retry"],
  ] as const;

  it.each([
    ["relative", "/operations/queues", "/operations/queues"],
    [
      "absolute",
      "https://queues.example.test/operations/queues",
      "https://queues.example.test/operations/queues",
    ],
  ])("rebases every %s generated route", (_kind, baseUrl, expectedBaseUrl) => {
    for (const [route, suffix] of routes) {
      expect(resolveHorizonRoute(route, baseUrl)).toEqual({
        ...route,
        url: `${expectedBaseUrl}${suffix}`,
      });
    }
  });

  it("retains the route method, query string, and hash", () => {
    const route = {
      ...retryFailed("job-123", { query: { source: "detail" } }),
      url: `${retryFailed.url("job-123", { query: { source: "detail" } })}#actions`,
    };

    expect(resolveHorizonRoute(route, "/operations/queues")).toEqual({
      method: "post",
      url: "/operations/queues/failed/job-123/retry?source=detail#actions",
    });
  });

  it("rejects routes outside Horizon's generated path", () => {
    expect(() =>
      resolveHorizonRoute({ url: "/login", method: "get" }, "/operations/queues"),
    ).toThrow("Cannot resolve a non-Horizon route");
  });
});

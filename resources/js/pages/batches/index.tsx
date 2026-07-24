import { Head, InfiniteScroll, router } from "@inertiajs/react";
import { SearchIcon, XIcon } from "lucide-react";
import { useEffect, useMemo, useState } from "react";

import { BatchTable } from "@/components/batches/batch-table";
import { BatchFilters } from "@/components/batches/batch-filters";
import { BatchesActions } from "@/components/batches/batches-actions";
import { ListPageHeader } from "@/components/shell/list-page-header";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { Field, FieldLabel } from "@/components/ui/field";
import {
  InputGroup,
  InputGroupAddon,
  InputGroupButton,
  InputGroupInput,
} from "@/components/ui/input-group";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { index as batchesIndex } from "@/generated/routes/horizon-new-dawn/batches";
import { useAutoLoad } from "@/hooks/use-auto-load";
import { useAutoLoadPreference } from "@/layouts/horizon-layout";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import { urlWithCurrentQuery } from "@/lib/url-query";
import type {
  BatchClearCounts,
  BatchFilterValues,
  BatchesPageProps,
  BatchStatus,
} from "@/types/batches";

type BatchTab = "all" | BatchStatus;

const batchTabs: Array<{ label: string; value: BatchTab }> = [
  { label: "All", value: "all" },
  { label: "In progress", value: "pending" },
  { label: "Complete", value: "finished" },
  { label: "Incomplete", value: "failures" },
  { label: "Cancelled", value: "cancelled" },
];
const numberFormatter = new Intl.NumberFormat();
const emptyBatchFilters: BatchFilterValues = { queue: null, connection: null, created: null };
const emptyBatchClearCounts: BatchClearCounts = {
  incomplete: 0,
  complete: 0,
  finished: 0,
  cancelled: 0,
  available: false,
};
const batchRefreshProps = ["batchClearCounts"];

function BatchesIndex({
  horizon,
  query,
  filters = emptyBatchFilters,
  batches,
  batchClearCounts = emptyBatchClearCounts,
}: BatchesPageProps) {
  const [search, setSearch] = useState(query);
  const [activeTab, setActiveTab] = useState<BatchTab>("all");
  const [knownQueues, setKnownQueues] = useState<string[]>(() =>
    batchFilterOptions(
      batches.data.map((batch) => batch.queue ?? null),
      filters.queue,
    ),
  );
  const [knownConnections, setKnownConnections] = useState<string[]>(() =>
    batchFilterOptions(
      batches.data.map((batch) => batch.connection ?? null),
      filters.connection,
    ),
  );
  const { autoLoad } = useAutoLoadPreference();
  const filterScope = `${query}:${filters.queue ?? ""}:${filters.connection ?? ""}:${filters.created ?? ""}`;
  const refreshedBatches = useAutoLoad({
    enabled: autoLoad,
    prop: "batches",
    interval: horizon.pollInterval,
    polling: query === "" && search.trim() === "",
    cursor: "before_id",
    items: batches.data,
    additionalProps: batchRefreshProps,
    scope: filterScope,
  });
  const batchCounts = useMemo(() => {
    const counts: Record<BatchTab, number> = {
      all: refreshedBatches.items.length,
      pending: 0,
      finished: 0,
      failures: 0,
      cancelled: 0,
    };

    refreshedBatches.items.forEach((batch) => {
      counts[batch.status] += 1;
    });

    return counts;
  }, [refreshedBatches.items]);
  const visibleBatches = useMemo(
    () =>
      activeTab === "all"
        ? refreshedBatches.items
        : refreshedBatches.items.filter((batch) => batch.status === activeTab),
    [activeTab, refreshedBatches.items],
  );

  useEffect(() => {
    setKnownQueues((current) =>
      batchFilterOptions(
        [...current, ...refreshedBatches.items.map((batch) => batch.queue ?? null)],
        filters.queue,
      ),
    );
    setKnownConnections((current) =>
      batchFilterOptions(
        [...current, ...refreshedBatches.items.map((batch) => batch.connection ?? null)],
        filters.connection,
      ),
    );
  }, [filters.connection, filters.queue, refreshedBatches.items]);

  useEffect(() => {
    const value = search.trim();

    if (value === query) {
      return;
    }

    const timer = window.setTimeout(() => {
      const route = resolveHorizonRoute(batchesIndex(), horizon.baseUrl);
      const url = urlWithCurrentQuery(route.url, { query: value }, ["before_id"]);

      router.get(
        url,
        {},
        {
          only: ["query", "filters", "batchClearCounts", "batches", "horizon"],
          preserveScroll: true,
          preserveState: true,
          replace: true,
          reset: ["batches"],
        },
      );
    }, 500);

    return () => window.clearTimeout(timer);
  }, [horizon.baseUrl, query, search]);

  const updateFilters = (nextFilters: BatchFilterValues) => {
    const route = resolveHorizonRoute(batchesIndex(), horizon.baseUrl);
    const url = urlWithCurrentQuery(
      route.url,
      {
        queue: nextFilters.queue,
        connection: nextFilters.connection,
        created: nextFilters.created,
      },
      ["before_id"],
    );

    router.get(
      url,
      {},
      {
        only: ["filters", "batchClearCounts", "batches", "horizon"],
        preserveScroll: true,
        preserveState: true,
        replace: true,
        reset: ["batches"],
      },
    );
  };

  return (
    <>
      <Head title="Batches" />
      <Card>
        <ListPageHeader
          title="Batches"
          separated={false}
          actions={<BatchesActions horizonBaseUrl={horizon.baseUrl} counts={batchClearCounts} />}
        />
        <CardContent className="p-0">
          <Tabs
            value={activeTab}
            onValueChange={(value) => setActiveTab(value as BatchTab)}
            className="gap-0"
          >
            <TabsList
              variant="line"
              aria-label="Batch status"
              className="w-full justify-start gap-2 overflow-x-auto rounded-none px-3 py-0"
            >
              {batchTabs.map((tab) => (
                <TabsTrigger
                  key={tab.value}
                  value={tab.value}
                  className="h-auto flex-none rounded-none px-3 pt-0 pb-3 text-[13.5px]"
                >
                  <span>{tab.label}</span>
                  <Badge className="h-4 min-w-4 px-1.5 text-[10.5px]" variant="secondary">
                    {numberFormatter.format(batchCounts[tab.value])}
                  </Badge>
                </TabsTrigger>
              ))}
            </TabsList>

            <div className="flex min-h-10 items-center gap-2 border-y border-separator px-6 py-1.5">
              <Field className="min-w-0 flex-1">
                <FieldLabel className="sr-only">Search batches by name or ID</FieldLabel>
                <InputGroup className="gap-1.5 border-0 bg-transparent! shadow-none has-[[data-slot=input-group-control]:focus-visible]:ring-0!">
                  <InputGroupInput
                    className="px-0!"
                    type="text"
                    role="searchbox"
                    inputMode="search"
                    value={search}
                    aria-label="Search batches by name or ID"
                    placeholder="Search batches by name or ID"
                    onChange={(event) => setSearch(event.target.value)}
                  />
                  <InputGroupAddon align="inline-start" className="pl-0">
                    <SearchIcon aria-hidden="true" />
                  </InputGroupAddon>
                  {search ? (
                    <InputGroupAddon align="inline-end" className="py-0 pr-0">
                      <InputGroupButton
                        size="icon-xs"
                        aria-label="Clear search"
                        onClick={() => setSearch("")}
                      >
                        <XIcon data-icon="inline-start" />
                      </InputGroupButton>
                    </InputGroupAddon>
                  ) : null}
                </InputGroup>
              </Field>
              <BatchFilters
                queues={knownQueues}
                connections={knownConnections}
                values={filters}
                onChange={updateFilters}
              />
            </div>

            <h2 className="sr-only">
              {batchTabs.find((tab) => tab.value === activeTab)?.label} batches
            </h2>
            <TabsContent value={activeTab}>
              <InfiniteScroll data="batches" onlyNext buffer={600} loading={<LoadingRows />}>
                <BatchTable
                  batches={visibleBatches}
                  horizonBaseUrl={horizon.baseUrl}
                  available={batches.available}
                  message={batches.message}
                  hasNewEntries={refreshedBatches.hasNewEntries}
                  onLoadNewEntries={refreshedBatches.loadNewEntries}
                  emptyTitle={activeTab === "all" ? undefined : "No matching batches"}
                  emptyDescription={
                    activeTab === "all" ? undefined : "No loaded batches match the selected status."
                  }
                  showBatchActions
                />
              </InfiniteScroll>
            </TabsContent>
          </Tabs>
        </CardContent>
      </Card>
    </>
  );
}

function batchFilterOptions(values: Array<string | null>, active: string | null): string[] {
  const options = active === null ? values : [...values, active];

  return Array.from(
    new Set(options.filter((value): value is string => value !== null && value !== "")),
  ).sort((left, right) => left.localeCompare(right, undefined, { sensitivity: "base" }));
}

function LoadingRows() {
  return (
    <div className="flex flex-col gap-2 border-t p-4" aria-label="Loading more batches">
      <Skeleton className="h-12 w-full" />
      <Skeleton className="h-12 w-full" />
    </div>
  );
}

export default BatchesIndex;

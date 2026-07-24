import { Head, InfiniteScroll, router, usePage } from "@inertiajs/react";
import { SearchIcon, XIcon } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";

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
import { currentQueryParameter, replaceCurrentQuery, urlWithCurrentQuery } from "@/lib/url-query";
import type {
  BatchClearCounts,
  BatchFilterCatalog,
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
const emptyBatchFilterCatalog: BatchFilterCatalog = {
  available: false,
  queues: [],
  connections: [],
};
const batchRefreshProps = ["batchClearCounts", "batchFilterCatalog"];

function BatchesIndex({
  horizon,
  query,
  filters = emptyBatchFilters,
  batches,
  batchClearCounts = emptyBatchClearCounts,
  batchFilterCatalog = emptyBatchFilterCatalog,
}: BatchesPageProps) {
  const page = usePage();
  const [search, setSearch] = useState(query);
  const [activeTab, setActiveTab] = useState<BatchTab>("all");
  const [hasClientSort, setHasClientSort] = useState(() => currentQueryParameter("sort") !== null);
  const batchItemsRef = useRef<HTMLTableSectionElement>(null);
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
  const visibleBatchIds = useMemo(
    () =>
      activeTab === "all"
        ? undefined
        : new Set(
            refreshedBatches.items
              .filter((batch) => batch.status === activeTab)
              .map((batch) => batch.id),
          ),
    [activeTab, refreshedBatches.items],
  );
  const queueOptions = useMemo(
    () => batchFilterOptions(batchFilterCatalog.queues, filters.queue),
    [batchFilterCatalog.queues, filters.queue],
  );
  const connectionOptions = useMemo(
    () => batchFilterOptions(batchFilterCatalog.connections, filters.connection),
    [batchFilterCatalog.connections, filters.connection],
  );

  useEffect(() => {
    if (
      !hasClientSort ||
      currentQueryParameter("sort") === null ||
      currentQueryParameter("before_id") === null
    ) {
      return;
    }

    replaceCurrentQuery({ before_id: null });
  }, [hasClientSort, page.url]);

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
          only: [
            "query",
            "filters",
            "batchClearCounts",
            "batchFilterCatalog",
            "batches",
            "horizon",
          ],
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
        only: ["filters", "batchClearCounts", "batchFilterCatalog", "batches", "horizon"],
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
                queues={queueOptions}
                connections={connectionOptions}
                values={filters}
                onChange={updateFilters}
              />
            </div>

            <h2 className="sr-only">
              {batchTabs.find((tab) => tab.value === activeTab)?.label} batches
            </h2>
            <TabsContent value={activeTab}>
              <InfiniteScroll
                key={`${filterScope}:${batches.available}`}
                data="batches"
                itemsElement={batchItemsRef}
                onlyNext
                preserveUrl={hasClientSort}
                buffer={600}
                loading={<LoadingRows />}
              >
                <BatchTable
                  batches={refreshedBatches.items}
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
                  visibleBatchIds={visibleBatchIds}
                  onSortedChange={setHasClientSort}
                  bodyRef={batchItemsRef}
                />
              </InfiniteScroll>
            </TabsContent>
          </Tabs>
        </CardContent>
      </Card>
    </>
  );
}

function batchFilterOptions(values: readonly string[], active: string | null): string[] {
  const options = active === null ? values : [...values, active];

  return Array.from(new Set(options.filter((value): value is string => value !== ""))).sort(
    (left, right) => left.localeCompare(right, undefined, { sensitivity: "base" }),
  );
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

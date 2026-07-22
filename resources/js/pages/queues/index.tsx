import { Head } from "@inertiajs/react";
import { SearchIcon, XIcon } from "lucide-react";
import { useMemo, useState } from "react";

import {
  emptyQueueFilterValues,
  matchesQueueFilters,
  QueueFilters,
  type QueueFilterValues,
} from "@/components/queues/queue-filters";
import { QueueTable } from "@/components/queues/queue-table";
import { QueuesActions } from "@/components/queues/queues-actions";
import { ListPageHeader } from "@/components/shell/list-page-header";
import { Card, CardContent } from "@/components/ui/card";
import { Field, FieldLabel } from "@/components/ui/field";
import {
  InputGroup,
  InputGroupAddon,
  InputGroupButton,
  InputGroupInput,
} from "@/components/ui/input-group";
import { usePageRefresh } from "@/hooks/use-dashboard-refresh";
import { useAutoLoadPreference } from "@/layouts/horizon-layout";
import type { QueuesPageProps } from "@/types/queues";

function QueuesIndex({ horizon, queues }: QueuesPageProps) {
  const { autoLoad } = useAutoLoadPreference();
  const [search, setSearch] = useState("");
  const [filters, setFilters] = useState<QueueFilterValues>(emptyQueueFilterValues);
  const normalizedSearch = search.trim().toLocaleLowerCase();
  const visibleQueues = useMemo(
    () =>
      queues.queues.filter((queue) => {
        const matchesSearch =
          normalizedSearch === "" ||
          [queue.name, ...queue.connections]
            .join(" ")
            .toLocaleLowerCase()
            .includes(normalizedSearch);

        return matchesSearch && matchesQueueFilters(queue, filters);
      }),
    [filters, normalizedSearch, queues.queues],
  );
  const hasFilters = Object.values(filters).some((value) => value !== null);
  const hasQuery = normalizedSearch !== "" || hasFilters;
  const pendingJobs = queues.queues.reduce(
    (total, queue) => total + queue.ready + queue.reserved + queue.delayed,
    0,
  );

  usePageRefresh(horizon.pollInterval, ["queues"], autoLoad && !hasQuery);

  return (
    <>
      <Head title="Queues" />
      <Card>
        <ListPageHeader
          title="Queues"
          separated={false}
          actions={
            <QueuesActions
              horizonBaseUrl={horizon.baseUrl}
              queueCount={queues.queues.length}
              pendingJobs={pendingJobs}
            />
          }
        />
        <CardContent className="p-0">
          <div className="flex min-h-10 items-center border-y border-separator px-6 py-1.5">
            <Field className="min-w-0 flex-1">
              <FieldLabel className="sr-only">Search queues</FieldLabel>
              <InputGroup className="gap-1.5 border-0 bg-transparent! shadow-none has-[[data-slot=input-group-control]:focus-visible]:ring-0!">
                <InputGroupInput
                  className="px-0!"
                  type="text"
                  role="searchbox"
                  inputMode="search"
                  value={search}
                  aria-label="Search queues"
                  placeholder="Search queues"
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
            <div className="flex min-h-8 shrink-0 items-center justify-end">
              <QueueFilters queues={queues.queues} values={filters} onChange={setFilters} />
            </div>
          </div>
          <QueueTable
            queues={{ ...queues, queues: visibleQueues }}
            horizonBaseUrl={horizon.baseUrl}
            queuePausing={horizon.capabilities?.queuePausing ?? false}
            emptyTitle={hasQuery ? "No matching queues" : undefined}
            emptyDescription={
              hasQuery ? "No supervised queues match your search and filters." : undefined
            }
          />
        </CardContent>
      </Card>
    </>
  );
}

export default QueuesIndex;

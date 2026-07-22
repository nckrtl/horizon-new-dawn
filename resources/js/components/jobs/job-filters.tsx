import { FilterIcon } from "lucide-react";
import { useId, useMemo } from "react";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import type { JobListType, JobRow } from "@/types/jobs";

const ALL_OPTIONS = "__all__";
const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: "base" });

export type JobFilterKey = "job" | "queue" | "connection" | "tag" | "state" | "retry";
export type JobFilterScope = JobListType | "failed" | "monitoring";
export type JobFilterValues = Record<JobFilterKey, string | null>;

type FilterOption = {
  label: string;
  value: string;
};

const filterKeysByScope = {
  monitoring: ["job", "queue"],
  pending: ["job", "queue", "connection", "tag", "state"],
  failed: ["job", "queue", "connection", "tag", "retry"],
  completed: ["job", "queue", "connection", "tag"],
  silenced: ["job", "queue", "connection", "tag"],
} as const satisfies Record<JobFilterScope, readonly JobFilterKey[]>;

const filterLabels = {
  job: { label: "Job class", allLabel: "All job classes" },
  queue: { label: "Queue", allLabel: "All queues" },
  connection: { label: "Connection", allLabel: "All connections" },
  tag: { label: "Tag", allLabel: "All tags" },
  state: { label: "State", allLabel: "All pending states" },
  retry: { label: "Retry status", allLabel: "All retry statuses" },
} satisfies Record<JobFilterKey, { label: string; allLabel: string }>;

export const emptyJobFilterValues: JobFilterValues = {
  job: null,
  queue: null,
  connection: null,
  tag: null,
  state: null,
  retry: null,
};

export function jobFilterKeys(scope: JobFilterScope): readonly JobFilterKey[] {
  return filterKeysByScope[scope];
}

export function matchesJobFilters(
  job: JobRow,
  filterKeys: readonly JobFilterKey[],
  values: JobFilterValues,
): boolean {
  return filterKeys.every((filterKey) => {
    const filterValue = values[filterKey];

    if (filterValue === null) {
      return true;
    }

    if (filterKey === "tag") {
      return job.tags.includes(filterValue);
    }

    return jobFilterValue(job, filterKey) === filterValue;
  });
}

export function JobFilters({
  jobs,
  filterKeys,
  values,
  onFilterChange,
  description = "Narrow the loaded jobs using filters available for this view.",
}: {
  jobs: readonly JobRow[];
  filterKeys: readonly JobFilterKey[];
  values: JobFilterValues;
  onFilterChange: (filterKey: JobFilterKey, value: string | null) => void;
  description?: string;
}) {
  const options = useMemo(
    () =>
      Object.fromEntries(
        filterKeys.map((filterKey) => [filterKey, filterOptions(jobs, filterKey)]),
      ) as Partial<Record<JobFilterKey, FilterOption[]>>,
    [filterKeys, jobs],
  );
  const activeFilterCount = filterKeys.filter((filterKey) => values[filterKey] !== null).length;

  return (
    <Dialog>
      <DialogTrigger
        render={
          <Button
            type="button"
            variant="action"
            size="icon-sm"
            className="relative"
            aria-label={
              activeFilterCount > 0 ? `Filter jobs, ${activeFilterCount} active` : "Filter jobs"
            }
            title="Filter jobs"
          />
        }
      >
        <FilterIcon />
        {activeFilterCount > 0 ? (
          <span
            aria-hidden="true"
            className="absolute top-0.5 right-0.5 size-2 rounded-full bg-primary"
          />
        ) : null}
      </DialogTrigger>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Filter jobs</DialogTitle>
          <DialogDescription>{description}</DialogDescription>
        </DialogHeader>
        <FieldGroup>
          {filterKeys.map((filterKey) => (
            <FilterSelect
              key={filterKey}
              label={filterLabels[filterKey].label}
              allLabel={filterLabels[filterKey].allLabel}
              options={options[filterKey] ?? []}
              value={values[filterKey]}
              onValueChange={(value) => onFilterChange(filterKey, value)}
            />
          ))}
        </FieldGroup>
        <DialogFooter className="sm:justify-between">
          <Button
            type="button"
            variant="ghost"
            disabled={activeFilterCount === 0}
            onClick={() => filterKeys.forEach((filterKey) => onFilterChange(filterKey, null))}
          >
            Clear filters
          </Button>
          <DialogClose render={<Button type="button" />}>Done</DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function filterOptions(jobs: readonly JobRow[], filterKey: JobFilterKey): FilterOption[] {
  if (filterKey === "state") {
    return [
      { label: "Ready", value: "ready" },
      { label: "Reserved", value: "reserved" },
      { label: "Delayed", value: "delayed" },
    ];
  }

  if (filterKey === "retry") {
    return [
      { label: "Not retried", value: "not-retried" },
      { label: "Retried", value: "retried" },
    ];
  }

  const options = jobs.flatMap((job) => {
    if (filterKey === "job") {
      return [{ label: job.shortName, value: job.name }];
    }

    if (filterKey === "tag") {
      return job.tags.map((tag) => ({ label: tag, value: tag }));
    }

    const value = jobFilterValue(job, filterKey);

    return value === null ? [] : [{ label: value, value }];
  });

  return Array.from(new Map(options.map((option) => [option.value, option])).values()).sort(
    (left, right) => collator.compare(left.label, right.label),
  );
}

function jobFilterValue(job: JobRow, filterKey: Exclude<JobFilterKey, "tag">): string | null {
  if (filterKey === "job") {
    return job.name;
  }

  if (filterKey === "queue") {
    return job.queue;
  }

  if (filterKey === "connection") {
    return job.connection;
  }

  if (filterKey === "retry") {
    return job.retried ? "retried" : "not-retried";
  }

  if (job.status === "reserved") {
    return "reserved";
  }

  if (job.delay !== null && job.delay > 0) {
    return "delayed";
  }

  return "ready";
}

function FilterSelect({
  label,
  allLabel,
  options,
  value,
  onValueChange,
}: {
  label: string;
  allLabel: string;
  options: FilterOption[];
  value: string | null;
  onValueChange: (value: string | null) => void;
}) {
  const triggerId = useId();
  const items = [{ label: allLabel, value: ALL_OPTIONS }, ...options];

  return (
    <Field>
      <FieldLabel htmlFor={triggerId}>{label}</FieldLabel>
      <Select
        items={items}
        value={value ?? ALL_OPTIONS}
        onValueChange={(nextValue) => onValueChange(nextValue === ALL_OPTIONS ? null : nextValue)}
      >
        <SelectTrigger id={triggerId} className="w-full">
          <SelectValue />
        </SelectTrigger>
        <SelectContent alignItemWithTrigger={false}>
          <SelectGroup>
            {items.map((item) => (
              <SelectItem key={item.value} value={item.value}>
                {item.label}
              </SelectItem>
            ))}
          </SelectGroup>
        </SelectContent>
      </Select>
    </Field>
  );
}

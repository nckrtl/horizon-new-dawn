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
import type { QueueRow, QueueWaitThresholdStatus } from "@/types/queues";

const ALL_OPTIONS = "__all__";

export type QueueFilterValues = {
  connection: string | null;
  pause: "active" | "paused" | null;
  waitThreshold: QueueWaitThresholdStatus | null;
};

export const emptyQueueFilterValues: QueueFilterValues = {
  connection: null,
  pause: null,
  waitThreshold: null,
};

const pauseOptions = [
  { label: "Not paused", value: "active" },
  { label: "Paused", value: "paused" },
];

const waitThresholdOptions: Array<{ label: string; value: QueueWaitThresholdStatus }> = [
  { label: "Exceeded", value: "exceeded" },
  { label: "Calculating", value: "calculating" },
  { label: "Within bounds", value: "within_bounds" },
  { label: "Disabled", value: "disabled" },
];

export function QueueFilters({
  queues,
  values,
  onChange,
}: {
  queues: readonly QueueRow[];
  values: QueueFilterValues;
  onChange: (values: QueueFilterValues) => void;
}) {
  const connectionId = useId();
  const pauseId = useId();
  const waitThresholdId = useId();
  const connections = useMemo(
    () =>
      Array.from(new Set(queues.flatMap((queue) => queue.connections))).sort((left, right) =>
        left.localeCompare(right, undefined, { numeric: true, sensitivity: "base" }),
      ),
    [queues],
  );
  const activeFilterCount = Object.values(values).filter((value) => value !== null).length;

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
              activeFilterCount > 0 ? `Filter queues, ${activeFilterCount} active` : "Filter queues"
            }
            title="Filter queues"
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
          <DialogTitle>Filter queues</DialogTitle>
          <DialogDescription>Narrow the supervised queues shown in this view.</DialogDescription>
        </DialogHeader>
        <FieldGroup>
          <FilterSelect
            id={connectionId}
            label="Connection"
            allLabel="All connections"
            options={connections.map((connection) => ({ label: connection, value: connection }))}
            value={values.connection}
            onValueChange={(connection) => onChange({ ...values, connection })}
          />
          <FilterSelect
            id={pauseId}
            label="Pause status"
            allLabel="All pause states"
            options={pauseOptions}
            value={values.pause}
            onValueChange={(pause) =>
              onChange({ ...values, pause: pause as QueueFilterValues["pause"] })
            }
          />
          <FilterSelect
            id={waitThresholdId}
            label="Wait threshold"
            allLabel="All wait thresholds"
            options={waitThresholdOptions}
            value={values.waitThreshold}
            onValueChange={(waitThreshold) =>
              onChange({
                ...values,
                waitThreshold: waitThreshold as QueueWaitThresholdStatus | null,
              })
            }
          />
        </FieldGroup>
        <DialogFooter className="sm:justify-between">
          <Button
            type="button"
            variant="ghost"
            disabled={activeFilterCount === 0}
            onClick={() => onChange(emptyQueueFilterValues)}
          >
            Clear filters
          </Button>
          <DialogClose render={<Button type="button" />}>Done</DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function FilterSelect({
  id,
  label,
  allLabel,
  options,
  value,
  onValueChange,
}: {
  id: string;
  label: string;
  allLabel: string;
  options: Array<{ label: string; value: string }>;
  value: string | null;
  onValueChange: (value: string | null) => void;
}) {
  const items = [{ label: allLabel, value: ALL_OPTIONS }, ...options];

  return (
    <Field>
      <FieldLabel htmlFor={id}>{label}</FieldLabel>
      <Select
        items={items}
        value={value ?? ALL_OPTIONS}
        onValueChange={(nextValue) => onValueChange(nextValue === ALL_OPTIONS ? null : nextValue)}
      >
        <SelectTrigger id={id} className="w-full">
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

export function matchesQueueFilters(queue: QueueRow, values: QueueFilterValues): boolean {
  if (values.connection !== null && !queue.connections.includes(values.connection)) {
    return false;
  }

  const paused = queue.pauseTargets.some((target) => target.paused);

  if (values.pause === "paused" && !paused) {
    return false;
  }

  if (values.pause === "active" && paused) {
    return false;
  }

  return values.waitThreshold === null || queue.waitThreshold.status === values.waitThreshold;
}

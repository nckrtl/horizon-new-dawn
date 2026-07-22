import { FilterIcon } from "lucide-react";
import { useId } from "react";

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
import type { MetricType } from "@/types/metrics";

const ALL_OPTIONS = "all";

export type MetricFilterValues = {
  runtime: "subsecond" | "one-second-plus" | null;
  throughput: "active" | "idle" | null;
};

export const emptyMetricFilterValues: MetricFilterValues = {
  runtime: null,
  throughput: null,
};

export function MetricsFilters({
  type,
  values,
  onChange,
}: {
  type: MetricType;
  values: MetricFilterValues;
  onChange: (values: MetricFilterValues) => void;
}) {
  const activeFilterCount = Object.values(values).filter((value) => value !== null).length;
  const singular = type === "jobs" ? "job" : "queue";

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
              activeFilterCount > 0
                ? `Filter ${singular} metrics, ${activeFilterCount} active`
                : `Filter ${singular} metrics`
            }
            title={`Filter ${singular} metrics`}
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
          <DialogTitle>Filter {singular} metrics</DialogTitle>
          <DialogDescription>
            Narrow the recorded {singular} metrics by the values available in this view.
          </DialogDescription>
        </DialogHeader>
        <FieldGroup>
          <MetricFilterSelect
            label="Throughput"
            allLabel="Any throughput"
            options={[
              { label: "Active", value: "active" },
              { label: "Idle", value: "idle" },
            ]}
            value={values.throughput}
            onValueChange={(throughput) => onChange({ ...values, throughput })}
          />
          <MetricFilterSelect
            label="Runtime"
            allLabel="Any runtime"
            options={[
              { label: "Under one second", value: "subsecond" },
              { label: "One second or longer", value: "one-second-plus" },
            ]}
            value={values.runtime}
            onValueChange={(runtime) => onChange({ ...values, runtime })}
          />
        </FieldGroup>
        <DialogFooter className="sm:justify-between">
          <Button
            type="button"
            variant="ghost"
            disabled={activeFilterCount === 0}
            onClick={() => onChange({ ...emptyMetricFilterValues })}
          >
            Clear filters
          </Button>
          <DialogClose render={<Button type="button" />}>Done</DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function MetricFilterSelect<Value extends string>({
  label,
  allLabel,
  options,
  value,
  onValueChange,
}: {
  label: string;
  allLabel: string;
  options: readonly { label: string; value: Value }[];
  value: Value | null;
  onValueChange: (value: Value | null) => void;
}) {
  const triggerId = useId();
  const items = [{ label: allLabel, value: ALL_OPTIONS }, ...options];

  return (
    <Field>
      <FieldLabel htmlFor={triggerId}>{label}</FieldLabel>
      <Select
        items={items}
        value={value ?? ALL_OPTIONS}
        onValueChange={(nextValue) =>
          onValueChange(nextValue === ALL_OPTIONS ? null : (nextValue as Value))
        }
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

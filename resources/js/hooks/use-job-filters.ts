import { usePage } from "@inertiajs/react";
import { useCallback, useEffect, useMemo, useState } from "react";

import {
  emptyJobFilterValues,
  jobFilterKeys,
  matchesJobFilters,
  type JobFilterKey,
  type JobFilterScope,
  type JobFilterValues,
} from "@/components/jobs/job-filters";
import { currentQueryParameter, replaceCurrentQuery } from "@/lib/url-query";
import type { JobRow } from "@/types/jobs";

const filterQueryParameters = {
  job: "filter_job",
  queue: "filter_queue",
  connection: "filter_connection",
  tag: "filter_tag",
  state: "filter_state",
  retry: "filter_retry",
} satisfies Record<JobFilterKey, string>;

export function useJobFilters(
  scope: JobFilterScope,
  jobs: readonly JobRow[],
  now = Date.now() / 1000,
) {
  const page = usePage();
  const availableFilterKeys = jobFilterKeys(scope);
  const [values, setValues] = useState<JobFilterValues>(() =>
    filterValuesFromCurrentQuery(availableFilterKeys),
  );

  useEffect(() => {
    setValues(filterValuesFromCurrentQuery(availableFilterKeys));
  }, [availableFilterKeys, page.url, scope]);

  const setFilterValue = useCallback(
    (filterKey: JobFilterKey, value: string | null) => {
      if (!availableFilterKeys.includes(filterKey)) {
        return;
      }

      setValues((current) => ({ ...current, [filterKey]: value }));
      replaceCurrentQuery({ [filterQueryParameters[filterKey]]: value });
    },
    [availableFilterKeys],
  );
  const clearAllFilters = useCallback(() => {
    setValues({ ...emptyJobFilterValues });
    replaceCurrentQuery(
      Object.fromEntries(
        availableFilterKeys.map((filterKey) => [filterQueryParameters[filterKey], null]),
      ),
    );
  }, [availableFilterKeys]);

  const filteredJobs = useMemo(
    () => jobs.filter((job) => matchesJobFilters(job, availableFilterKeys, values, now)),
    [availableFilterKeys, jobs, now, values],
  );
  const activeFilterCount = availableFilterKeys.filter(
    (filterKey) => values[filterKey] !== null,
  ).length;

  return {
    activeFilterCount,
    availableFilterKeys,
    clearAllFilters,
    filteredJobs,
    setFilterValue,
    values,
  };
}

function filterValuesFromCurrentQuery(filterKeys: readonly JobFilterKey[]): JobFilterValues {
  return {
    ...emptyJobFilterValues,
    ...Object.fromEntries(
      filterKeys.map((filterKey) => [
        filterKey,
        currentQueryParameter(filterQueryParameters[filterKey]),
      ]),
    ),
  };
}

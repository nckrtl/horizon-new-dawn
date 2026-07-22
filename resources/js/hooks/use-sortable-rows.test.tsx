import { act, renderHook } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import { useSortableRows, type SortColumn } from "@/hooks/use-sortable-rows";

type Row = {
  id: string;
  name: string;
  count: number;
  duration: number;
  timestamp: number | null;
  children?: string[];
};

const rows: Row[] = [
  { id: "alpha", name: "Queue 10", count: 1_200, duration: 4.5, timestamp: null },
  { id: "beta", name: "queue 2", count: 12, duration: 0.4, timestamp: 20 },
  { id: "gamma", name: "Queue 2", count: 12, duration: 3, timestamp: 10 },
];

const columns: SortColumn<Row>[] = [
  { key: "name", value: (row) => row.name },
  { key: "count", value: (row) => row.count },
  { key: "duration", value: (row) => row.duration },
  { key: "timestamp", value: (row) => row.timestamp },
];

describe("useSortableRows", () => {
  it("toggles stable, case-insensitive text sorting", () => {
    const { result } = renderHook(() => useSortableRows(rows, columns));

    act(() => result.current.toggle("name"));
    expect(result.current.rows.map((row) => row.id)).toEqual(["beta", "gamma", "alpha"]);
    expect(result.current.sort).toEqual({ key: "name", direction: "asc" });

    act(() => result.current.toggle("name"));
    expect(result.current.rows.map((row) => row.id)).toEqual(["alpha", "beta", "gamma"]);
    expect(result.current.sort).toEqual({ key: "name", direction: "desc" });
  });

  it("sorts normalized counts, durations, and timestamps with nulls last", () => {
    const { result } = renderHook(() => useSortableRows(rows, columns));

    act(() => result.current.toggle("count"));
    expect(result.current.rows.map((row) => row.count.toLocaleString())).toEqual([
      "12",
      "12",
      "1,200",
    ]);

    act(() => result.current.toggle("duration"));
    expect(result.current.rows.map((row) => row.duration)).toEqual([0.4, 3, 4.5]);

    act(() => result.current.toggle("timestamp"));
    expect(result.current.rows.map((row) => row.timestamp)).toEqual([10, 20, null]);
    act(() => result.current.toggle("timestamp"));
    expect(result.current.rows.map((row) => row.timestamp)).toEqual([20, 10, null]);
  });

  it("sorts workload parents without detaching their children", () => {
    const grouped = [
      { ...rows[0], children: ["redis:one", "redis:two"] },
      { ...rows[1], children: ["database:default"] },
    ];
    const { result } = renderHook(() => useSortableRows(grouped, columns));

    act(() => result.current.toggle("count"));

    expect(result.current.rows[0]).toMatchObject({ id: "beta", children: ["database:default"] });
    expect(result.current.rows[1]).toMatchObject({
      id: "alpha",
      children: ["redis:one", "redis:two"],
    });
  });
});

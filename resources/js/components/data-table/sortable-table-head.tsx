import { TableHead } from "@/components/ui/table";
import { cn } from "@/lib/utils";
import type { SortDirection } from "@/hooks/use-sortable-rows";

export function SortableTableHead({
  label,
  columnKey,
  direction,
  onSort,
  className,
}: {
  label: string;
  columnKey?: string;
  direction?: SortDirection;
  onSort?: (key: string) => void;
  className?: string;
}) {
  const sortable = columnKey !== undefined && onSort !== undefined;
  const nextDirection = direction === "asc" ? "descending" : "ascending";

  return (
    <TableHead
      className={cn(className)}
      scope="col"
      aria-sort={sortable ? (direction ? `${direction}ending` : "none") : undefined}
    >
      {sortable ? (
        <button
          className="inline-flex h-10 items-center font-medium outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
          type="button"
          aria-label={`Sort by ${label} ${nextDirection}`}
          onClick={() => onSort(columnKey)}
        >
          <span>{label}</span>
          {direction === "asc" ? (
            <span className="ml-1.5 text-[10px]" aria-hidden="true">
              ↑
            </span>
          ) : null}
          {direction === "desc" ? (
            <span className="ml-1.5 text-[10px]" aria-hidden="true">
              ↓
            </span>
          ) : null}
        </button>
      ) : (
        label
      )}
    </TableHead>
  );
}

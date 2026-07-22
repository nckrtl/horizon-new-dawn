import { CardAction, CardHeader, CardTitle } from "@/components/ui/card";
import { cn } from "@/lib/utils";

export function ListPageHeader({
  title,
  actions,
  separated = true,
}: {
  title: React.ReactNode;
  actions?: React.ReactNode;
  separated?: boolean;
}) {
  return (
    <CardHeader
      className={cn(
        "flex h-[54px] items-center justify-between gap-3 px-6 py-0",
        !separated && "border-b-0",
      )}
    >
      <CardTitle>{title}</CardTitle>
      {actions ? (
        <CardAction className="flex min-h-8 items-center justify-end gap-3 self-center">
          {actions}
        </CardAction>
      ) : null}
    </CardHeader>
  );
}

import { ClockAlertIcon, TriangleAlertIcon } from "lucide-react";

import { DashboardNavigationIcon } from "@/components/navigation-icons";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import {
  Card,
  CardAction,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Empty,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty";
import type { RecentFailures } from "@/types/dashboard";

const dateFormatter = new Intl.DateTimeFormat(undefined, {
  dateStyle: "medium",
  timeStyle: "short",
});

export function RecentFailuresPanel({ failures }: { failures: RecentFailures }) {
  return (
    <Card className="h-full">
      <CardHeader>
        <CardTitle>Recent failures</CardTitle>
        <CardDescription>Safe previews from Horizon’s failed jobs repository.</CardDescription>
        <CardAction>
          <ClockAlertIcon className="text-muted-foreground" aria-hidden="true" />
        </CardAction>
      </CardHeader>
      <CardContent>
        {!failures.available ? (
          <Alert variant="destructive">
            <TriangleAlertIcon aria-hidden="true" />
            <AlertTitle>Failures unavailable</AlertTitle>
            <AlertDescription>
              {failures.message ?? "Recent Horizon failures are currently unavailable."}
            </AlertDescription>
          </Alert>
        ) : failures.items.length === 0 ? (
          <Empty className="min-h-48 border">
            <EmptyHeader>
              <EmptyMedia variant="icon">
                <DashboardNavigationIcon aria-hidden="true" />
              </EmptyMedia>
              <EmptyTitle>No recent failures</EmptyTitle>
              <EmptyDescription>Jobs in Horizon’s recent window are healthy.</EmptyDescription>
            </EmptyHeader>
          </Empty>
        ) : (
          <div className="flex flex-col gap-2">
            {failures.items.map((failure) => {
              const failedAt = new Date(failure.failedAt * 1000);

              return (
                <article
                  className="grid gap-2 rounded-lg border p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
                  key={failure.id}
                >
                  <div className="min-w-0">
                    <p className="truncate font-mono text-xs font-medium" title={failure.name}>
                      {failure.name}
                    </p>
                    <time
                      className="mt-1 block text-xs text-muted-foreground"
                      dateTime={failedAt.toISOString()}
                    >
                      {dateFormatter.format(failedAt)}
                    </time>
                  </div>
                  <Badge variant="secondary">{failure.queue}</Badge>
                </article>
              );
            })}
          </div>
        )}
      </CardContent>
    </Card>
  );
}

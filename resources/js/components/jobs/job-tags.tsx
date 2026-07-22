import { Link, router, usePage } from "@inertiajs/react";
import { useState } from "react";

import { Badge } from "@/components/ui/badge";
import {
  show as monitoringShow,
  store as monitorTag,
} from "@/generated/routes/horizon-new-dawn/monitoring";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import { cn } from "@/lib/utils";
import type { HorizonPageProps } from "@/types/page";

export function JobTags({
  tags,
  interactive = true,
  limit,
  className,
  badgeClassName,
}: {
  tags: readonly string[];
  interactive?: boolean;
  limit?: number;
  className?: string;
  badgeClassName?: string;
}) {
  const page = usePage<HorizonPageProps>();
  const horizonBaseUrl = page.props.horizon.baseUrl;
  const monitoredTags = new Set(page.props.monitoredTags ?? []);

  if (tags.length === 0) {
    return <span className="text-xs text-muted-foreground">—</span>;
  }

  const visibleTags = limit === undefined ? tags : tags.slice(0, limit);
  const hiddenTagCount = tags.length - visibleTags.length;

  return (
    <div className={cn("flex flex-wrap gap-2", className)}>
      {visibleTags.map((tag) => {
        if (!interactive) {
          return (
            <Badge
              className={cn("h-auto px-2.5 py-[3px] text-xs", badgeClassName)}
              variant="secondary"
              key={tag}
              title={tag}
            >
              {tag}
            </Badge>
          );
        }

        return (
          <JobTagBadge
            key={tag}
            tag={tag}
            monitored={monitoredTags.has(tag)}
            horizonBaseUrl={horizonBaseUrl}
            badgeClassName={badgeClassName}
          />
        );
      })}
      {hiddenTagCount > 0 ? (
        <span className="text-[12.5px] text-muted-foreground">+{hiddenTagCount} more</span>
      ) : null}
    </div>
  );
}

function JobTagBadge({
  tag,
  monitored,
  horizonBaseUrl,
  badgeClassName,
}: {
  tag: string;
  monitored: boolean;
  horizonBaseUrl: string;
  badgeClassName?: string;
}) {
  const [working, setWorking] = useState(false);
  const className = cn("h-auto px-2.5 py-[3px] text-xs", badgeClassName);

  if (monitored) {
    const monitoringUrl = resolveHorizonRoute(
      monitoringShow({ tag: encodeURIComponent(tag), status: "jobs" }),
      horizonBaseUrl,
    ).url;

    return (
      <Badge
        className={className}
        variant="secondary"
        title={tag}
        render={<Link href={monitoringUrl} prefetch />}
      >
        {tag}
      </Badge>
    );
  }

  const monitor = () => {
    const route = resolveHorizonRoute(monitorTag(), horizonBaseUrl);

    router.post(
      route.url,
      { tag },
      {
        preserveScroll: true,
        onStart: () => setWorking(true),
        onFinish: () => setWorking(false),
      },
    );
  };

  return (
    <Badge
      className={className}
      variant="secondary"
      title={`Monitor ${tag}`}
      render={
        <button type="button" aria-label={`Monitor ${tag}`} disabled={working} onClick={monitor} />
      }
    >
      {tag}
    </Badge>
  );
}

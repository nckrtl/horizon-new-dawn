import { Head, useForm } from "@inertiajs/react";
import { EllipsisIcon, TagIcon } from "lucide-react";
import { useState } from "react";

import { MonitoredTagsTable } from "@/components/monitoring/monitored-tags-table";
import { ListPageHeader } from "@/components/shell/list-page-header";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Field, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { store as monitorTag } from "@/generated/routes/horizon-new-dawn/monitoring";
import { usePageRefresh } from "@/hooks/use-dashboard-refresh";
import { useAutoLoadPreference } from "@/layouts/horizon-layout";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import type { MonitoringPageProps } from "@/types/monitoring";

function MonitoringIndex({ horizon, tags }: MonitoringPageProps) {
  const { autoLoad } = useAutoLoadPreference();

  usePageRefresh(horizon.pollInterval, monitoringRefreshProps, autoLoad);

  const [dialogOpen, setDialogOpen] = useState(false);
  const form = useForm({ tag: "" });

  const submit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const route = resolveHorizonRoute(monitorTag(), horizon.baseUrl);

    form.post(route.url, {
      preserveScroll: true,
      onSuccess: () => {
        setDialogOpen(false);
        form.reset();
      },
    });
  };

  return (
    <>
      <Head title="Monitoring" />
      <Card>
        <ListPageHeader
          title="Monitoring"
          actions={
            <DropdownMenu>
              <DropdownMenuTrigger
                render={
                  <Button type="button" variant="ghost" size="icon-sm" aria-label="Monitor Tag" />
                }
              >
                <EllipsisIcon />
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-44">
                <DropdownMenuItem onSelect={() => setDialogOpen(true)}>
                  <TagIcon />
                  Create tag
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          }
        />
        <CardContent className="p-0">
          <MonitoredTagsTable
            tags={tags.data}
            horizonBaseUrl={horizon.baseUrl}
            available={tags.available}
            message={tags.message}
          />
        </CardContent>
      </Card>
      <Dialog
        open={dialogOpen}
        onOpenChange={(open) => {
          setDialogOpen(open);

          if (!open) {
            form.clearErrors();
          }
        }}
      >
        <DialogContent>
          <form onSubmit={submit}>
            <DialogHeader>
              <DialogTitle>Monitor New Tag</DialogTitle>
              <DialogDescription>
                Keep a dedicated history of recent and failed jobs for an exact tag match. History
                starts after monitoring is enabled and is retained by Horizon&apos;s trim settings.
              </DialogDescription>
            </DialogHeader>
            <FieldGroup className="py-5">
              <Field data-invalid={form.errors.tag ? true : undefined}>
                <FieldLabel htmlFor="monitoring-tag">Tag</FieldLabel>
                <Input
                  id="monitoring-tag"
                  value={form.data.tag}
                  placeholder={"App\\Models\\User:6352"}
                  aria-invalid={form.errors.tag ? true : undefined}
                  autoFocus
                  onChange={(event) => form.setData("tag", event.target.value)}
                />
                <FieldError>{form.errors.tag}</FieldError>
              </Field>
            </FieldGroup>
            <DialogFooter>
              <DialogClose render={<Button type="button" variant="ghost" />}>Cancel</DialogClose>
              <Button type="submit" disabled={form.processing}>
                {form.processing ? "Monitoring…" : "Monitor"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  );
}

const monitoringRefreshProps = ["tags"];

export default MonitoringIndex;

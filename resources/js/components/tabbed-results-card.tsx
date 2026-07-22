import { Link } from "@inertiajs/react";
import { SearchIcon, XIcon } from "lucide-react";

import { ListPageHeader } from "@/components/shell/list-page-header";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { Field, FieldLabel } from "@/components/ui/field";
import {
  InputGroup,
  InputGroupAddon,
  InputGroupButton,
  InputGroupInput,
} from "@/components/ui/input-group";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";

export type TabbedResultsTab<Tab extends string> = {
  count?: number | null;
  href: string;
  label: string;
  preserveState?: boolean;
  value: Tab;
};

const numberFormatter = new Intl.NumberFormat();

export function TabbedResultsCard<Tab extends string>({
  title,
  activeTab,
  tabs,
  tabListLabel,
  contentHeading,
  search,
  searchLabel,
  searchPlaceholder,
  onSearchChange,
  filters,
  actions,
  children,
}: {
  title: string;
  activeTab: Tab;
  tabs: readonly TabbedResultsTab<Tab>[];
  tabListLabel: string;
  contentHeading: string;
  search: string;
  searchLabel: string;
  searchPlaceholder: string;
  onSearchChange: (value: string) => void;
  filters?: React.ReactNode;
  actions?: React.ReactNode;
  children: React.ReactNode;
}) {
  return (
    <Card>
      <ListPageHeader title={title} actions={actions} separated={false} />
      <CardContent className="p-0">
        <Tabs value={activeTab} className="gap-0">
          <TabsList
            variant="line"
            aria-label={tabListLabel}
            className="w-full justify-start gap-2 overflow-x-auto rounded-none px-3 py-0"
          >
            {tabs.map((tab) => (
              <TabsTrigger
                key={tab.value}
                value={tab.value}
                nativeButton={false}
                className="h-auto flex-none rounded-none px-3 pt-0 pb-3 text-[13.5px]"
                render={
                  <Link
                    href={tab.href}
                    prefetch
                    preserveState={tab.preserveState}
                    aria-current={tab.value === activeTab ? "page" : undefined}
                  />
                }
              >
                <span>{tab.label}</span>
                {tab.count !== null && tab.count !== undefined ? (
                  <Badge className="h-4 min-w-4 px-1.5 text-[10.5px]" variant="secondary">
                    {numberFormatter.format(tab.count)}
                  </Badge>
                ) : null}
              </TabsTrigger>
            ))}
          </TabsList>

          <div className="flex min-h-10 items-center border-y border-separator px-6 py-1.5">
            <Field className="min-w-0 flex-1">
              <FieldLabel className="sr-only">{searchLabel}</FieldLabel>
              <InputGroup className="gap-1.5 border-0 bg-transparent! shadow-none has-[[data-slot=input-group-control]:focus-visible]:ring-0!">
                <InputGroupInput
                  className="px-0!"
                  type="text"
                  role="searchbox"
                  inputMode="search"
                  value={search}
                  aria-label={searchLabel}
                  placeholder={searchPlaceholder}
                  onChange={(event) => onSearchChange(event.target.value)}
                />
                <InputGroupAddon align="inline-start" className="pl-0">
                  <SearchIcon aria-hidden="true" />
                </InputGroupAddon>
                {search ? (
                  <InputGroupAddon align="inline-end" className="py-0 pr-0">
                    <InputGroupButton
                      size="icon-xs"
                      aria-label="Clear search"
                      onClick={() => onSearchChange("")}
                    >
                      <XIcon data-icon="inline-start" />
                    </InputGroupButton>
                  </InputGroupAddon>
                ) : null}
              </InputGroup>
            </Field>
            {filters ? (
              <div className="flex min-h-8 shrink-0 items-center justify-end">{filters}</div>
            ) : null}
          </div>

          <h2 className="sr-only">{contentHeading}</h2>
          <TabsContent value={activeTab}>{children}</TabsContent>
        </Tabs>
      </CardContent>
    </Card>
  );
}

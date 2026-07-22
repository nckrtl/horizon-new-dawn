import { buttonVariants } from "@/components/ui/button";
import { describe, expect, it } from "vitest";

describe("buttonVariants", () => {
  it.each([
    ["default", "px-[13px]", "pr-[11px]", "pl-[11px]"],
    ["xs", "px-[7px]", "pr-[5px]", "pl-[5px]"],
    ["sm", "px-[9px]", "pr-[5px]", "pl-[5px]"],
    ["lg", "px-[9px]", "pr-[7px]", "pl-[7px]"],
  ] as const)(
    "reduces the %s button side padding by one pixel",
    (size, horizontalPadding, inlineEndPadding, inlineStartPadding) => {
      const classes = buttonVariants({ size });

      expect(classes).toContain(horizontalPadding);
      expect(classes).toContain(`has-data-[icon=inline-end]:${inlineEndPadding}`);
      expect(classes).toContain(`has-data-[icon=inline-start]:${inlineStartPadding}`);
    },
  );
});

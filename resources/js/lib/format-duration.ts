const numberFormatter = new Intl.NumberFormat();
const preciseNumberFormatter = new Intl.NumberFormat(undefined, {
  maximumFractionDigits: 2,
});

export type DurationFormat = "approximate" | "precise";

const preciseUnits = [
  { singular: "year", plural: "years", seconds: 31_536_000 },
  { singular: "month", plural: "months", seconds: 2_592_000 },
  { singular: "day", plural: "days", seconds: 86_400 },
  { singular: "hour", plural: "hours", seconds: 3_600 },
  { singular: "minute", plural: "minutes", seconds: 60 },
  { singular: "second", plural: "seconds", seconds: 1 },
] as const;
const maximumPreciseParts = 3;

export function formatDuration(seconds: number, format: DurationFormat = "approximate"): string {
  const duration = Math.max(0, seconds);

  if (format === "precise") {
    return formatPreciseDuration(duration);
  }

  if (duration <= 5) {
    return "A few seconds";
  }

  if (duration < 60) {
    return `${numberFormatter.format(Math.round(duration))} seconds`;
  }

  if (duration < 90) {
    return "A minute";
  }

  if (duration < 2_700) {
    return `${numberFormatter.format(Math.round(duration / 60))} minutes`;
  }

  if (duration < 5_400) {
    return "An hour";
  }

  if (duration < 79_200) {
    return `${numberFormatter.format(Math.round(duration / 3_600))} hours`;
  }

  if (duration < 129_600) {
    return "A day";
  }

  if (duration < 2_592_000) {
    return `${numberFormatter.format(Math.round(duration / 86_400))} days`;
  }

  if (duration < 5_184_000) {
    return "A month";
  }

  if (duration < 31_536_000) {
    return `${numberFormatter.format(Math.round(duration / 2_592_000))} months`;
  }

  if (duration < 63_072_000) {
    return "A year";
  }

  return `${numberFormatter.format(Math.round(duration / 31_536_000))} years`;
}

function formatPreciseDuration(duration: number): string {
  let remainingSeconds = duration;
  const parts: string[] = [];

  for (const unit of preciseUnits) {
    if (parts.length === maximumPreciseParts) {
      break;
    }

    const value =
      unit.seconds === 1 ? remainingSeconds : Math.floor(remainingSeconds / unit.seconds);

    if (value <= 0) {
      continue;
    }

    parts.push(
      `${preciseNumberFormatter.format(value)} ${durationUnit(value, unit.singular, unit.plural)}`,
    );
    remainingSeconds -= value * unit.seconds;
  }

  return parts.length > 0 ? parts.join(", ") : "0 second";
}

function durationUnit(value: number, singular: string, plural: string): string {
  return value <= 1 ? singular : plural;
}

export function lowercaseFirst(value: string) {
  return `${value.charAt(0).toLowerCase()}${value.slice(1)}`;
}

export function formatRawDuration(seconds: number): string {
  const duration = Math.max(0, seconds);

  return `${preciseNumberFormatter.format(duration)} ${durationUnit(duration, "second", "seconds")}`;
}

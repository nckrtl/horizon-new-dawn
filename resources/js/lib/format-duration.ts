const numberFormatter = new Intl.NumberFormat();

export function formatDuration(seconds: number) {
  const duration = Math.max(0, seconds);

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

  return `${numberFormatter.format(Math.round(duration / 86_400))} days`;
}

export function lowercaseFirst(value: string) {
  return `${value.charAt(0).toLowerCase()}${value.slice(1)}`;
}

export function formatRuntime(seconds: number) {
  return `${Math.max(0, seconds).toFixed(2)}s`;
}

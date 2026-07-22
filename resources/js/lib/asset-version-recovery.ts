type InertiaLocationEvent = CustomEvent<{
  url: URL;
  versionChange: boolean;
}>;

export function recoverFromAssetVersionChange(
  event: InertiaLocationEvent,
  reload: () => void,
): false | undefined {
  if (!event.detail.versionChange) {
    return;
  }

  reload();

  return false;
}

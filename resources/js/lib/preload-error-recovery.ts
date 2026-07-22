export function recoverFromPreloadError(event: Event, reload: () => void): void {
  event.preventDefault();
  reload();
}

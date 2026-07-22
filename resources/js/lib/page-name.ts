export function pageModulePath(name: string) {
  const path = name
    .split("/")
    .map((segment) => segment.replace(/([a-z0-9])([A-Z])/g, "$1-$2").toLowerCase())
    .join("/");

  return `./pages/${path}.tsx`;
}

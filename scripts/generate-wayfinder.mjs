import { spawnSync } from "node:child_process";
import { readdirSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import path from "node:path";

const packagePath = process.cwd();
const result = spawnSync(
  "php",
  ["vendor/bin/testbench", "wayfinder:generate", ...process.argv.slice(2)],
  {
    env: {
      ...process.env,
      APP_BASE_PATH: packagePath,
      VIEW_COMPILED_PATH: path.resolve(packagePath, "bootstrap/cache"),
    },
    stdio: "inherit",
  },
);

if (result.error) {
  throw result.error;
}

if (result.status !== 0) {
  process.exitCode = result.status ?? 1;
} else {
  const generatedRoutesPath = path.resolve(packagePath, "resources/js/generated/routes");
  const retainedNamespaces = new Set(["horizon", "horizon-new-dawn"]);

  for (const entry of readdirSync(generatedRoutesPath, { withFileTypes: true })) {
    if (entry.isDirectory() && !retainedNamespaces.has(entry.name)) {
      rmSync(path.join(generatedRoutesPath, entry.name), { force: true, recursive: true });
    }
  }

  const checkoutPrefixes = [
    packagePath.replaceAll("\\", "/").replace(/\/+$/, ""),
    packagePath.replaceAll("\\", "/").replace(/^\/+|\/+$/g, ""),
  ];

  const normalizeSourcePaths = (directory) => {
    for (const entry of readdirSync(directory, { withFileTypes: true })) {
      const entryPath = path.join(directory, entry.name);

      if (entry.isDirectory()) {
        normalizeSourcePaths(entryPath);
        continue;
      }

      if (!entry.name.endsWith(".ts")) {
        continue;
      }

      const content = readFileSync(entryPath, "utf8");
      const normalized = checkoutPrefixes.reduce(
        (source, prefix) => source.replaceAll(`${prefix}/`, ""),
        content,
      );

      if (normalized !== content) {
        writeFileSync(entryPath, normalized);
      }
    }
  };

  normalizeSourcePaths(generatedRoutesPath);
}

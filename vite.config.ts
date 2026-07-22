import path from "node:path";
import { wayfinder } from "@laravel/vite-plugin-wayfinder";
import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import { defineConfig } from "vite";

export default defineConfig({
  base: "./",
  plugins: [
    wayfinder({
      actions: false,
      command: "node scripts/generate-wayfinder.mjs",
      path: "resources/js/generated",
      patterns: ["src/**/*.php", "vendor/laravel/horizon/routes/**/*.php"],
    }),
    react(),
    tailwindcss(),
  ],
  resolve: {
    alias: {
      "@": path.resolve(import.meta.dirname, "resources/js"),
    },
  },
  build: {
    license: {
      fileName: "THIRD_PARTY_LICENSES.md",
    },
    manifest: true,
    outDir: "dist/build",
    emptyOutDir: true,
    rollupOptions: {
      input: "resources/js/app.tsx",
    },
  },
});

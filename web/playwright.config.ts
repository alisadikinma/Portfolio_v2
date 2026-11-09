import { defineConfig } from "@playwright/test";

// Server under test: the built Astro server (see web/e2e/README in the ledger / CLAUDE.md).
export const BASE_URL = process.env.E2E_BASE_URL ?? "http://127.0.0.1:4321";
// Safety: e2e must never run against production (form POSTs, GA4 beacons). Point E2E_BASE_URL at a local server/stack.
if (/^(www\.)?alisadikinma\.com$/i.test(new URL(BASE_URL).hostname)) {
  throw new Error(`E2E refuses to run against production (${BASE_URL}). Use a local server.`);
}
// The ONE headed Chrome, started once by the operator with --remote-debugging-port=9222.
export const CDP_URL = process.env.E2E_CDP_URL ?? "http://127.0.0.1:9222";

export default defineConfig({
  testDir: "./e2e",
  testMatch: "**/*.spec.ts",
  workers: 1,
  fullyParallel: false,
  retries: 0,
  timeout: 60_000,
  expect: { timeout: 10_000 },
  reporter: [["list"]],
  use: { actionTimeout: 15_000, navigationTimeout: 45_000 },
});

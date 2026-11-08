import { describe, it, expect } from "vitest";
import { readFileSync } from "node:fs";

// PageSectionSeeder deletes every homepage row not in HOMEPAGE_SECTIONS. A section the Astro home
// reads but the seeder does not list would lose its admin toggle on the next seed — keep them equal.
const astro = readFileSync(new URL("../pages/[lang]/index.astro", import.meta.url), "utf8");
const seeder = readFileSync(new URL("../../../backend/database/seeders/PageSectionSeeder.php", import.meta.url), "utf8");

describe("homepage section keys: Astro home == PageSectionSeeder keep-list", () => {
  it("every on(\"…\") key in index.astro (plus always-on hero) is kept by the seeder, and nothing else", () => {
    const read = new Set([...astro.matchAll(/\bon\("([a-z-]+)"\)/g)].map((m) => m[1]));
    read.add("hero");
    const block = seeder.match(/HOMEPAGE_SECTIONS\s*=\s*\[([\s\S]*?)\];/)?.[1] ?? "";
    const kept = new Set([...block.matchAll(/'([a-z-]+)'/g)].map((m) => m[1]));
    expect([...kept].sort()).toEqual([...read].sort());
  });
});

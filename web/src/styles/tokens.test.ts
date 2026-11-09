import { readFileSync, readdirSync, statSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, it } from "vitest";

const dir = import.meta.dirname;
const css = (f: string) => readFileSync(join(dir, f), "utf8").replace(/\s+/g, "");

describe("tokens.css", () => {
  const t = css("tokens.css");
  it.each([
    "--bg-deep:#0a1220",
    "--bg-surface:#0e1828",
    "--bg-elevated:#16243a",
    "--text-primary:#e8eef8",
    "--text-secondary:#9fb0c7",
    "--accent-primary:#3fb8c5",
    "--accent-gold:#d4a24d",
    "--header-h:68px",
  ])("defines %s", (decl) => {
    expect(t).toContain(decl + ";");
  });
});

describe("styles", () => {
  it("base.css has teal focus ring and scroll-padding-top", () => {
    const b = css("base.css");
    expect(b).toContain("scroll-padding-top");
    expect(b).toContain(":focus-visible{outline:2pxsolidvar(--accent-primary);outline-offset:2px");
  });
  it("components.css has hero, journey, bento, lightbox, nav active", () => {
    const c = css("components.css");
    for (const s of [".hx-more", ".journey", ".bento", ".lb{", ".nava.is-active::after", ".faqdetails"]) {
      expect(c).toContain(s.replace(/ /g, ""));
    }
  });
  it("no googleapis font reference under web/src", () => {
    const walk = (d: string): string[] =>
      readdirSync(d).flatMap((n) => {
        const p = join(d, n);
        return statSync(p).isDirectory() ? walk(p) : [p];
      });
    const offenders = walk(join(dir, ".."))
      .filter((p) => !p.endsWith("tokens.test.ts"))
      .filter((p) => readFileSync(p, "utf8").includes("fonts.googleapis.com"));
    expect(offenders).toEqual([]);
  });
});

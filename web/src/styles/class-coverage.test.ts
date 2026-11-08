import { readFileSync, readdirSync, statSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, it } from "vitest";

// Guards against a section's CSS being deleted by accident (2026-11-08: the latest-writing,
// newsletter and training styles vanished together with an unrelated block and the page
// shipped as a raw list). Every static class in a component must be styled somewhere:
// the shared stylesheets or the component's own <style>.
const root = join(import.meta.dirname, "..");
const shared = ["styles/components.css", "styles/base.css"].map((f) => readFileSync(join(root, f), "utf8")).join("\n");
// Classes that only exist as JS/test hooks or semantic markers, never styled on purpose.
const HOOKS = new Set<string>(["nl-copy"]); // plain wrapper, layout comes from .nl-grid

function files(dir: string): string[] {
  return readdirSync(dir).flatMap((n) => {
    const p = join(dir, n);
    return statSync(p).isDirectory() ? files(p) : p.endsWith(".astro") ? [p] : [];
  });
}

const targets = ["components/home/LatestWriting.astro", "components/NewsletterForm.astro", "components/home/Journey.astro", "components/home/WhoIAm.astro", "pages/[lang]/[trainingSeg].astro"];

describe("every static class used by key sections has CSS", () => {
  it.each(targets)("%s", (rel) => {
    const src = readFileSync(join(root, rel), "utf8");
    const own = (src.match(/<style[\s\S]*?<\/style>/g) ?? []).join("\n");
    const css = shared + own;
    const classes = new Set([...src.matchAll(/class="([^"{}]+)"/g)].flatMap((m) => m[1].split(/\s+/)).filter(Boolean));
    const missing = [...classes].filter((c) => !HOOKS.has(c) && !new RegExp(`\\.${c.replace(/[-]/g, "\\-")}(?![\\w-])`).test(css));
    expect(missing).toEqual([]);
  });
  it("collects astro files", () => expect(files(root).length).toBeGreaterThan(10));
});

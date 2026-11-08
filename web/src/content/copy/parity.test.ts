import { describe, expect, it } from "vitest";
import { id } from "./id";
import { en } from "./en";

type Tree = { [k: string]: string | Tree };

function keyPaths(tree: Tree, prefix = ""): string[] {
  return Object.entries(tree).flatMap(([k, v]) =>
    typeof v === "string" ? [prefix + k] : keyPaths(v, `${prefix}${k}.`),
  );
}

function strings(tree: Tree): [string, string][] {
  return Object.entries(tree).flatMap(([k, v]) =>
    typeof v === "string" ? [[k, v] as [string, string]] : strings(v),
  );
}

describe("copy parity id/en", () => {
  it("has identical key trees", () => {
    expect(keyPaths(en as Tree).sort()).toEqual(keyPaths(id as Tree).sort());
  });

  it("has no empty strings", () => {
    for (const tree of [id, en]) {
      for (const [k, v] of strings(tree as Tree)) {
        expect(v.trim(), k).not.toBe("");
      }
    }
  });

  it("has a non-trivial key set", () => {
    expect(keyPaths(id as Tree).length).toBeGreaterThanOrEqual(20);
  });

  // Ali 2026-11-09 (SEO audit): "pakar AI Indonesia" belongs to the home page; FAQ names Ali + INDUSIA.ai.
  it("FAQ H1 names Ali Sadikin Ma and INDUSIA.ai, not the home keyword", () => {
    for (const h of [id.faq.h1, en.faq.h1]) {
      expect(h).toContain("Ali Sadikin Ma");
      expect(h).toContain("INDUSIA.ai");
    }
    expect(id.faq.h1.toLowerCase()).not.toContain("pakar ai indonesia");
  });

  it("uses the full name and never the bare name or banned claims", () => {
    for (const tree of [id, en]) {
      // about.disambig names the former governor on purpose (GEO disambiguation, Ali 2026-11-09).
      const all = strings(tree as Tree).filter(([k]) => !String(k).endsWith("disambig")).map(([, v]) => v).join("\n");
      expect(all).not.toMatch(/Ali Sadikin(?! Ma)/);
      expect(all).not.toMatch(/terbaik|nomor 1|\bbest\b|number one/i);
      expect(all).not.toMatch(/Rp\s?\d|\$\s?\d/);
    }
  });

  it("keeps the WhatsApp number out of copy", () => {
    for (const tree of [id, en]) {
      expect(strings(tree as Tree).map(([, v]) => v).join("\n")).not.toMatch(/\+?\d{9,}/);
    }
  });
});

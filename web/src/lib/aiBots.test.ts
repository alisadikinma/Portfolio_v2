import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";
import { AI_BOTS, matchAiBot } from "./aiBots";

function parsePhp(): Array<[string, string]> {
  const src = readFileSync(resolve(__dirname, "../../../backend/config/ai_bots.php"), "utf8");
  const stripped = src.replace(/\/\*[\s\S]*?\*\//g, "").replace(/(^|[^:'"])\/\/.*$/gm, "$1");
  const body = stripped.slice(stripped.indexOf("'map'"));
  const pairs = [...body.matchAll(/(['"])([^'"\n]+)\1\s*=>\s*(['"])([^'"\n]+)\3/g)].map(
    (m): [string, string] => [m[2], m[4]],
  );
  const arrowLines = body.split("\n").filter((l) => l.includes("=>") && !/=>\s*\[/.test(l)).length;
  expect(pairs.length).toBe(arrowLines);
  return pairs;
}

describe("AI_BOTS parity with backend/config/ai_bots.php", () => {
  it("has identical keys, values and order", () => {
    const php = parsePhp();
    expect(php.length).toBeGreaterThan(5);
    expect(Object.entries(AI_BOTS)).toEqual(php);
  });
  it("no key is a substring of a later key", () => {
    const keys = Object.keys(AI_BOTS).map((k) => k.toLowerCase());
    keys.forEach((k, i) => keys.slice(i + 1).forEach((o) => expect(o.includes(k)).toBe(false)));
  });
});

describe("matchAiBot", () => {
  it("returns canonical name case-insensitively", () => {
    expect(matchAiBot("Mozilla/5.0 (compatible; gptbot/1.2; +https://openai.com/gptbot)")).toBe("GPTBot");
    expect(matchAiBot("Mozilla/5.0 ClaudeBot/1.0")).toBe("ClaudeBot");
  });
  it("returns null for humans, empty and missing UA", () => {
    expect(matchAiBot("Mozilla/5.0 Chrome/120")).toBeNull();
    expect(matchAiBot("")).toBeNull();
    expect(matchAiBot(null)).toBeNull();
  });
});

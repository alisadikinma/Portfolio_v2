import { describe, it, expect } from "vitest";
import { clipWords, htmlToText, firstParagraphText, wordCount, postTitle, postDescription, pickLede } from "./seo-text";

describe("clipWords", () => {
  it("returns short text unchanged", () => expect(clipWords("short text", 20)).toBe("short text"));
  it("clips at a word boundary with a single ellipsis char, never exceeding max", () => {
    const out = clipWords("one two three four five six seven eight nine ten", 25);
    expect(out.endsWith("…")).toBe(true);
    expect(out).not.toContain("...");
    expect(out.length).toBeLessThanOrEqual(25);
    expect(out).toBe("one two three four five…");
  });
  it("strips trailing punctuation before the ellipsis", () => {
    expect(clipWords("alpha beta gamma, delta epsilon zeta", 17)).toBe("alpha beta gamma…");
    expect(clipWords("alpha beta - gamma delta epsilon", 13)).toBe("alpha beta…");
  });
  it("hard-cuts a single long word", () => {
    expect(clipWords("x".repeat(50), 10)).toBe("x".repeat(9) + "…");
  });
});

describe("htmlToText / firstParagraphText / wordCount", () => {
  it("strips tags and decodes entities", () => {
    expect(htmlToText("<p>Tom &amp; <b>Jerry</b> &#39;s &quot;x&quot;</p>")).toBe("Tom & Jerry 's \"x\"");
  });
  it("takes the first non-empty <p>", () => {
    expect(firstParagraphText("<h2>t</h2><p> </p><p>Hello <em>world</em>.</p><p>Second</p>")).toBe("Hello world.");
  });
  it("is empty without paragraphs", () => expect(firstParagraphText("<div>x</div>")).toBe(""));
  it("counts words", () => expect(wordCount("  a b   c ")).toBe(3));
});

describe("postTitle", () => {
  const N = "Ali Sadikin Ma";
  it("uses meta_title as-is when <= 60", () => {
    expect(postTitle("Anything", "Self-Hosting Chinese AI Models: The Proven Prediction")).toBe(
      "Self-Hosting Chinese AI Models: The Proven Prediction",
    );
  });
  it("clips an over-long meta_title at a word boundary with …", () => {
    const t = postTitle("x", "A very long meta title that definitely goes past the sixty character limit, really");
    expect(t.length).toBeLessThanOrEqual(60);
    expect(t.endsWith("…")).toBe(true);
    expect(t).not.toContain("...");
  });
  it("without meta_title appends the name when it fits", () => {
    expect(postTitle("Vibe coding", null)).toBe(`Vibe coding | ${N}`);
  });
  it("without meta_title clips the title alone when the suffix does not fit", () => {
    const t = postTitle("Judul artikel yang sangat panjang sekali sampai tidak muat lagi", "");
    expect(t.length).toBeLessThanOrEqual(60);
    expect(t).not.toContain(N);
  });
  it("never produces the name twice", () => {
    expect(postTitle(`Cerita ${N}`, null)).toBe(`Cerita ${N}`);
    expect((postTitle("X", `X — ${N}`).match(/Ali Sadikin Ma/g) ?? []).length).toBe(1);
  });
});

describe("postDescription", () => {
  const long = "a".repeat(130);
  const long2 = "b".repeat(140);
  it("prefers meta_description when >= 120", () => {
    expect(postDescription({ meta: long, excerpt: long2, content: "<p>c</p>" })).toBe(long);
  });
  it("falls through short sources to the next", () => {
    expect(postDescription({ meta: "short", excerpt: long2, content: "" })).toBe(long2);
    expect(postDescription({ meta: "", excerpt: "short", content: `<p>${long}</p>` })).toBe(long);
  });
  it("uses the first non-empty short one if nothing reaches 120, never empty", () => {
    expect(postDescription({ meta: "", excerpt: "short one", content: "" })).toBe("short one");
    expect(postDescription({ meta: "", excerpt: "", content: "", fallback: "Judul" })).toBe("Judul");
  });
  it("clips to 160 at a word boundary", () => {
    const words = Array.from({ length: 60 }, (_, i) => `kata${i}`).join(" ");
    const out = postDescription({ meta: words });
    expect(out.length).toBeLessThanOrEqual(160);
    expect(out.endsWith("…")).toBe(true);
  });
});

describe("postDescription: short hook paragraphs (PF-3)", () => {
  it("joins the opening paragraphs until 120 chars instead of using a 15-char hook line", () => {
    const content = "<p>Ini bukan typo.</p><p>AI yang pernah nyebut dirinya sendiri MechaHitler kini bisa akses jaringan militer <em>classified</em> Amerika Serikat.</p><p>Paragraf ketiga.</p>";
    const out = postDescription({ meta: "Ini bukan typo. AI yang pernah nyebut dirinya jaring...", excerpt: null, content, fallback: "Judul" });
    expect(out).toBe("Ini bukan typo. AI yang pernah nyebut dirinya sendiri MechaHitler kini bisa akses jaringan militer classified Amerika Serikat.");
    expect(out.length).toBeGreaterThanOrEqual(120);
  });
});

describe("pickLede", () => {
  const long = Array.from({ length: 26 }, (_, i) => `kata${i}`).join(" ");
  it("returns the first source with >= 25 words, as plain text", () => {
    expect(pickLede([null, "pendek sekali", `<b>${long}</b>`, "other " + long])).toBe(long);
  });
  it("returns empty string when no source is long enough", () => {
    expect(pickLede(["a b c", undefined, ""])).toBe("");
  });
});

describe("postDescription as project description", () => {
  it("a generic short meta_description yields to the project description", () => {
    const meta = "A comprehensive web-based platform designed to streamline business operations and enhance user experience.";
    const desc = "Protect your assets with intelligent monitoring and real-time threat detection. This advanced security system leverages AI to identify anomalies and alert.";
    expect(postDescription({ meta, excerpt: desc, content: "" })).toBe(clipWords(desc, 160));
  });
});

// SEO audit 2026-11-09: some DB meta fields were cut mid-word with "..." by the old pipeline.
describe("pipeline-truncated meta fields", () => {
  it("a meta_title ending in ... falls back to the full title", () => {
    const t = postTitle("Self-Hosting Chinese AI Models: Prediksi yang Sudah Terbukti", "Self-Hosting Chinese AI Models: Prediksi yang Sudah Terbu...");
    expect(t).not.toMatch(/Terbu\.\.\.|\.\.\.$/);
    expect(t.startsWith("Self-Hosting Chinese AI Models")).toBe(true);
  });
  it("same for a meta_title ending in …", () => {
    expect(postTitle("Judul Lengkap", "Judul Leng…")).toBe("Judul Lengkap | Ali Sadikin Ma");
  });
  it("a meta_description ending in ... yields to the excerpt", () => {
    const ex = "Model AI China seperti DeepSeek dan Qwen naik dari 1% ke 15% pangsa pasar global dalam 12 bulan; ini alasan self-hosting yang patuh GDPR.";
    expect(postDescription({ meta: "Model AI China tumbuh d...", excerpt: ex })).toBe(ex);
  });
});

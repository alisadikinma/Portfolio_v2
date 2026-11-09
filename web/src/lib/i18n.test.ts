import { describe, expect, it } from "vitest";
import { LANGS, alternates, localizedPath, pickLang } from "./i18n";

describe("alternates", () => {
  it("maps training page both ways", () => {
    const want = { id: "/id/pelatihan-ai", en: "/en/ai-training", "x-default": "/en/ai-training" };
    expect(alternates("/id/pelatihan-ai")).toEqual(want);
    expect(alternates("/en/ai-training")).toEqual(want);
  });
  it("maps speaker page both ways", () => {
    expect(alternates("/en/ai-speaker").id).toBe("/id/pembicara-ai");
    expect(alternates("/id/pembicara-ai").en).toBe("/en/ai-speaker");
  });
  it("maps award slugs both ways", () => {
    expect(alternates("/id/penghargaan/global-ai-demo-day").en).toBe("/en/awards/global-ai-demo-day");
    expect(alternates("/en/awards/x").id).toBe("/id/penghargaan/x");
  });
  it("keeps identical paths for shared routes", () => {
    expect(alternates("/id/about")).toEqual({ id: "/id/about", en: "/en/about", "x-default": "/en/about" });
    expect(alternates("/en/blog/some-post").id).toBe("/id/blog/some-post");
    expect(alternates("/id").en).toBe("/en");
    expect(alternates("/en")["x-default"]).toBe("/en");
  });
  it("swaps lang for unknown path", () => {
    expect(alternates("/id/whatever/deep").en).toBe("/en/whatever/deep");
  });
});

describe("localizedPath", () => {
  it("translates mapped paths", () => {
    expect(localizedPath("/id/pelatihan-ai", "en")).toBe("/en/ai-training");
    expect(localizedPath("/en/ai-speaker", "id")).toBe("/id/pembicara-ai");
  });
  it("swaps lang on unknown path", () => {
    expect(localizedPath("/en/faq", "id")).toBe("/id/faq");
    expect(localizedPath("/id", "en")).toBe("/en");
  });
  it("prefixes unprefixed paths", () => {
    expect(localizedPath("/faq", "id")).toBe("/id/faq");
    expect(localizedPath("/", "en")).toBe("/en");
  });
});

describe("pickLang", () => {
  it("defaults to en", () => {
    expect(pickLang("")).toBe("en");
    expect(pickLang("*")).toBe("en");
    expect(pickLang(undefined)).toBe("en");
    expect(pickLang("garbage;;;,,")).toBe("en");
    expect(pickLang("fr-FR,fr;q=0.9")).toBe("en");
  });
  it("picks id when first matching tag is id/in", () => {
    expect(pickLang("id-ID,id;q=0.9")).toBe("id");
    expect(pickLang("in")).toBe("id");
    expect(pickLang("id,en;q=0.8")).toBe("id");
  });
  it("picks en when en comes first", () => {
    expect(pickLang("en-US,en;q=0.9,id;q=0.8")).toBe("en");
  });
  it("honors q ordering", () => {
    expect(pickLang("en;q=0.5,id;q=0.9")).toBe("id");
  });
});

describe("LANGS", () => {
  it("is id, en", () => expect([...LANGS]).toEqual(["id", "en"]));
});

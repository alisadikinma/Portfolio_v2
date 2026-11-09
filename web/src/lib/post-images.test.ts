import { describe, it, expect } from "vitest";
import { imageOrigin } from "./post-images";

describe("imageOrigin", () => {
  it("uses the origin of an absolute featured image", () => {
    expect(imageOrigin("https://api.alisadikinma.com/storage/a.png")).toBe("https://api.alisadikinma.com");
  });
  it("falls back to the site origin when there is none or it is relative", () => {
    expect(imageOrigin(null)).toBe("https://alisadikinma.com");
    expect(imageOrigin(undefined)).toBe("https://alisadikinma.com");
    expect(imageOrigin("/storage/a.png")).toBe("https://alisadikinma.com");
  });
});

import { variantList, srcsetOf, largestUrl, urlAtWidth } from "./post-images";

describe("variant helpers", () => {
  const raw = { "1920w": "/storage/v/a-1920.webp", "320w": "/storage/v/a-320.webp", bogus: "/x", "640w": "" };
  const list = variantList(raw, "https://alisadikinma.com");
  it("parses NNNw keys, drops junk, sorts ascending, makes URLs absolute", () => {
    expect(list.map((v) => v.w)).toEqual([320, 1920]);
    expect(list[0].url).toBe("https://alisadikinma.com/storage/v/a-320.webp");
  });
  it("builds srcset, picks largest and exact widths", () => {
    expect(srcsetOf(list)).toBe("https://alisadikinma.com/storage/v/a-320.webp 320w, https://alisadikinma.com/storage/v/a-1920.webp 1920w");
    expect(largestUrl(list)).toBe("https://alisadikinma.com/storage/v/a-1920.webp");
    expect(urlAtWidth(list, 1920)).toBe("https://alisadikinma.com/storage/v/a-1920.webp");
    expect(urlAtWidth(list, 999)).toBeUndefined();
  });
  it("handles null / empty", () => {
    expect(variantList(null, "https://x")).toEqual([]);
    expect(srcsetOf([])).toBeUndefined();
    expect(largestUrl([])).toBeUndefined();
  });
});

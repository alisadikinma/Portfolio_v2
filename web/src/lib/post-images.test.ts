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

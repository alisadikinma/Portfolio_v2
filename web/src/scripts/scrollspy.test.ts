import { describe, it, expect } from "vitest";
import { pickActive, ROOT_MARGIN } from "./scrollspy";

describe("pickActive", () => {
  const ordered = ["tentang", "perjalanan", "layanan", "tulisan"];
  it("none visible -> null", () => {
    expect(pickActive(new Set(), ordered)).toBeNull();
    expect(pickActive([], ordered)).toBeNull();
  });
  it("one visible -> that id", () => {
    expect(pickActive(["layanan"], ordered)).toBe("layanan");
  });
  it("two visible -> later in document order, regardless of insertion order", () => {
    expect(pickActive(new Set(["layanan", "tentang"]), ordered)).toBe("layanan");
  });
  it("ignores ids not in ordered list", () => {
    expect(pickActive(["zzz"], ordered)).toBeNull();
  });
  it("exposes rootMargin", () => {
    expect(ROOT_MARGIN).toBe("-45% 0px -50% 0px");
  });
});

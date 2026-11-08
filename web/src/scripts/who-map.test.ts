// @vitest-environment happy-dom
import { describe, it, expect, vi, afterEach } from "vitest";
import { shouldAnimate, initWhoMap } from "./who-map";

describe("shouldAnimate", () => {
  it("only when motion is allowed and the map is still below the fold", () => {
    expect(shouldAnimate({ top: 1200 }, 800, false)).toBe(true);
    expect(shouldAnimate({ top: 300 }, 800, false)).toBe(false); // already visible: never hide it
    expect(shouldAnimate({ top: 1200 }, 800, true)).toBe(false); // reduced motion: final frame only
  });
});

describe("initWhoMap", () => {
  afterEach(() => { document.body.innerHTML = ""; vi.unstubAllGlobals(); });
  it("no map on the page -> no-op, no throw", () => {
    expect(() => initWhoMap(document)).not.toThrow();
  });
  it("reduced motion -> leaves the final frame untouched", () => {
    document.body.innerHTML = '<figure data-who-map><svg><path class="wm-line"></path></svg></figure>';
    vi.stubGlobal("matchMedia", (q: string) => ({ matches: q.includes("reduce"), addEventListener() {}, removeEventListener() {} }));
    initWhoMap(document);
    expect(document.querySelector<SVGPathElement>(".wm-line")!.getAttribute("style")).toBeNull();
  });
});

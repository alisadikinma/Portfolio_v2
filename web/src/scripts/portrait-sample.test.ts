import { describe, it, expect } from "vitest";
import { samplePortrait, pointBudget } from "./portrait-sample";

/** 8x8 RGBA: uniform border (background), 2x2 centre subject with distinct luminance. */
function synthetic(): { data: Uint8ClampedArray; width: number; height: number } {
  const n = 8;
  const data = new Uint8ClampedArray(n * n * 4);
  for (let p = 0; p < n * n; p++) {
    const x = p % n, y = (p / n) | 0;
    const centre = x >= 3 && x <= 4 && y >= 3 && y <= 4;
    const v = centre ? (x === 3 && y === 3 ? 100 : x === 4 && y === 4 ? 220 : 160) : 20;
    data.set([v, v, v, 255], p * 4);
  }
  return { data, width: n, height: n };
}

describe("samplePortrait", () => {
  it("returns only the 4 centre points (background flood-filled away)", () => {
    const s = samplePortrait(synthetic(), 8);
    expect(s.lum.length).toBe(4);
    expect(s.pos.length).toBe(8);
    expect(s.rnd.length).toBe(16);
    // all positions near the image centre (|x|,|y| < 0.2 in -0.5..0.5 space)
    for (let i = 0; i < s.pos.length; i++) expect(Math.abs(s.pos[i]!)).toBeLessThan(0.2);
  });

  it("contrast-stretches luminance into 0..1 using the full range", () => {
    const s = samplePortrait(synthetic(), 8);
    for (const l of s.lum) {
      expect(l).toBeGreaterThanOrEqual(0);
      expect(l).toBeLessThanOrEqual(1);
    }
    expect(Math.min(...s.lum)).toBe(0);
    expect(Math.max(...s.lum)).toBe(1);
  });

  it("returns empty arrays for an all-background image", () => {
    const n = 4;
    const data = new Uint8ClampedArray(n * n * 4).fill(30);
    const s = samplePortrait({ data, width: n, height: n }, n);
    expect(s.lum.length).toBe(0);
    expect(s.pos.length).toBe(0);
  });
});

describe("pointBudget", () => {
  it("mobile (<760) = 160", () => {
    expect(pointBudget({ cores: 8, memory: 8, width: 390 })).toBe(160);
  });
  it("desktop = 250", () => {
    expect(pointBudget({ cores: 8, memory: 8, width: 1440 })).toBe(250);
  });
  it("low-end device (cores<=4 or memory<=4) = 110, any width", () => {
    expect(pointBudget({ cores: 4, memory: 8, width: 1440 })).toBe(110);
    expect(pointBudget({ cores: 8, memory: 4, width: 390 })).toBe(110);
  });
  it("unknown cores/memory is treated as capable", () => {
    expect(pointBudget({ width: 1440 })).toBe(250);
    expect(pointBudget({ width: 390 })).toBe(160);
  });
  it("boundary width 760 is desktop", () => {
    expect(pointBudget({ cores: 8, memory: 8, width: 760 })).toBe(250);
  });
});

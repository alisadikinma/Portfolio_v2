import { describe, it, expect } from "vitest";
import { nearestIndex, nextStep, STEPS } from "./journey-cycle";

describe("journey cycle", () => {
  it("walks 1 -> 4 and loops back to the first chapter", () => {
    const seen = [1];
    for (let i = 0; i < STEPS; i++) seen.push(nextStep(seen[seen.length - 1]));
    expect(seen).toEqual([1, 2, 3, 4, 1]);
  });
});

// Ali 2026-11-09: on phones the card carousel follows the timeline, and a swipe moves the timeline.
describe("nearestIndex (which card a swiped carousel settled on)", () => {
  const lefts = [0, 300, 600, 900];
  it("picks the card whose start is closest to the scroll position", () => {
    expect(nearestIndex(lefts, 0)).toBe(0);
    expect(nearestIndex(lefts, 140)).toBe(0);
    expect(nearestIndex(lefts, 160)).toBe(1);
    expect(nearestIndex(lefts, 610)).toBe(2);
  });
  it("clamps past either end", () => {
    expect(nearestIndex(lefts, -50)).toBe(0);
    expect(nearestIndex(lefts, 5000)).toBe(3);
  });
  it("returns 0 for an empty list", () => {
    expect(nearestIndex([], 100)).toBe(0);
  });
});

import { describe, it, expect } from "vitest";
import { stepTarget, positionLabel } from "./carousel";

// Ali 2026-11-09: "ini bentuk slider tapi saya gak bisa geser ke card berikutnya" — mouse users
// need buttons; the buttons move exactly one card.
describe("stepTarget", () => {
  const lefts = [0, 300, 600];
  it("next goes to the following card start", () => {
    expect(stepTarget(lefts, 0, 1)).toBe(300);
    expect(stepTarget(lefts, 150, 1)).toBe(300);
    expect(stepTarget(lefts, 300, 1)).toBe(600);
  });
  it("prev goes to the previous card start", () => {
    expect(stepTarget(lefts, 600, -1)).toBe(300);
    expect(stepTarget(lefts, 450, -1)).toBe(300);
    expect(stepTarget(lefts, 300, -1)).toBe(0);
  });
  it("stays put at either end", () => {
    expect(stepTarget(lefts, 600, 1)).toBe(600);
    expect(stepTarget(lefts, 0, -1)).toBe(0);
  });
  it("tolerates sub-pixel scroll positions", () => {
    expect(stepTarget(lefts, 299.4, 1)).toBe(600);
    expect(stepTarget(lefts, 300.6, -1)).toBe(0);
  });
});

describe("positionLabel", () => {
  it("shows the card in view as 1-based of total", () => {
    expect(positionLabel([0, 300, 600], 0)).toBe("1 / 3");
    expect(positionLabel([0, 300, 600], 310)).toBe("2 / 3");
    expect(positionLabel([0, 300, 600], 600)).toBe("3 / 3");
  });
});

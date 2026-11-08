import { describe, it, expect } from "vitest";
import { nextStep, STEPS } from "./journey-cycle";

describe("journey cycle", () => {
  it("walks 1 -> 4 and loops back to the first chapter", () => {
    const seen = [1];
    for (let i = 0; i < STEPS; i++) seen.push(nextStep(seen[seen.length - 1]));
    expect(seen).toEqual([1, 2, 3, 4, 1]);
  });
});

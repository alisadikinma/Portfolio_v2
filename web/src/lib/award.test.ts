import { describe, expect, it } from "vitest";
import { awardLocation, awardMetaDescription, awardResultKind, awardShortName, awardStartDate, awardTitleTag, organizerName } from "./award";

describe("awardShortName", () => {
  it("drops a trailing parenthetical and turns dashes into a colon", () => {
    expect(awardShortName("1st Place Winner — Global AI Demo Day 2026 (Outskill AI Generalist Fellowship)")).toBe(
      "1st Place Winner: Global AI Demo Day 2026",
    );
    expect(awardShortName("Top 8 Finalist – IDBYTE 2017 Connected")).toBe("Top 8 Finalist: IDBYTE 2017 Connected");
    expect(awardShortName("Google Startup Grind Silicon Valley - San Fransisco")).toBe("Google Startup Grind Silicon Valley: San Fransisco");
  });
  it("leaves plain titles alone", () => {
    expect(awardShortName("1st Place Winner Nextdev Startup Competition")).toBe("1st Place Winner Nextdev Startup Competition");
  });
});

describe("awardTitleTag", () => {
  it("is '{short} — Ali Sadikin Ma' when it fits in 60", () => {
    expect(awardTitleTag("Top 8 Finalist – IDBYTE 2017 Connected")).toBe("Top 8 Finalist: IDBYTE 2017 Connected — Ali Sadikin Ma");
  });
  it("uses the short name of the Demo Day award and stays within 60", () => {
    const t = awardTitleTag("1st Place Winner — Global AI Demo Day 2026 (Outskill AI Generalist Fellowship)");
    expect(t).toBe("1st Place Winner: Global AI Demo Day 2026 — Ali Sadikin Ma");
    expect(t.length).toBeLessThanOrEqual(60);
  });
  it("clips only the title part and always keeps the name", () => {
    const t = awardTitleTag("1st Place Winner Wild Card Fenox Startup World Cup Competition");
    expect(t.length).toBeLessThanOrEqual(60);
    expect(t.endsWith(" — Ali Sadikin Ma")).toBe(true);
    expect(t).toContain("…");
    expect(t).not.toContain("...");
  });
});

describe("awardStartDate", () => {
  it.each([
    ["17 Jan 2026", "2026-01-17"],
    ["3 March 2019", "2019-03-03"],
    ["2019", "2019"],
    ["2026-01-17", "2026-01-17"],
    ["2026-01-17T00:00:00.000000Z", "2026-01-17"],
  ])("%s -> %s", (raw, want) => expect(awardStartDate(raw)).toBe(want));
  it.each([["Q1 2025"], ["January 2025"], [""], [null], [undefined], ["32 Jan 2026"], ["31 Feb 2026"]])("%j -> undefined (never guessed)", (raw) =>
    expect(awardStartDate(raw as string)).toBeUndefined(),
  );
});

describe("awardLocation", () => {
  it("reads only places named in the award's own text", () => {
    expect(awardLocation("Demo Day", "kompetisi di Bengaluru, India pada 17 Januari")).toBe("Bengaluru, India");
    expect(awardLocation("Alibaba efounders Fellowship, Hangzhou China", "")).toBe("Hangzhou, China");
    expect(awardLocation("Google Startup Grind Silicon Valley - San Fransisco", "")).toBe("Silicon Valley, United States");
  });
  it("is undefined when no place is named", () => {
    expect(awardLocation("1st Place Winner Nextdev Startup Competition", "Achieved first place in prestigious competition.")).toBeUndefined();
  });
});

describe("organizerName", () => {
  it.each([
    ["OUTSKILL - INDIA", "Outskill"],
    ["Google - USA", "Google"],
    ["TELKOMSEL - Indonesia", "Telkomsel"],
    ["Alibaba - UN-UNCTAD - CHINA", "Alibaba, UN-UNCTAD"],
    ["BUBU.com", "BUBU.com"],
    ["  ", ""],
  ])("%s -> %s", (raw, want) => expect(organizerName(raw)).toBe(want));
});

describe("awardResultKind (honest result line)", () => {
  it.each([
    ["1st Place Winner — Global AI Demo Day 2026 (Outskill AI Generalist Fellowship)", "first"],
    ["1st Place Winner Wild Card Fenox Startup World Cup Competition", "first"],
    ["Top 8 Finalist – IDBYTE 2017 Connected", "finalist"],
    ["Alibaba efounders Fellowship, Hangzhou China", "fellow"],
    ["Google Startup Grind Silicon Valley - San Fransisco", "attendee"],
    ["Some Other Honor", null],
  ])("%s -> %s", (title, want) => expect(awardResultKind(title)).toBe(want));
});

describe("awardMetaDescription", () => {
  const tail = "Penghargaan dan panggung internasional Ali Sadikin Ma, pakar AI Indonesia.";
  it("short text gets the short name in front and the tail behind, within 120-160", () => {
    const d = awardMetaDescription("1st Place Winner Nextdev Startup Competition", "Achieved first place in Nextdev Startup Competition.", tail);
    expect(d.length).toBeGreaterThanOrEqual(120);
    expect(d.length).toBeLessThanOrEqual(160);
    expect(d.startsWith("1st Place Winner Nextdev Startup Competition")).toBe(true);
    expect(d).toContain(tail.slice(0, 20));
  });
  it("long text is clipped to 160 with a single ellipsis and keeps the name in front", () => {
    const d = awardMetaDescription("Top 8 Finalist: IDBYTE 2017 Connected", "x ".repeat(200), tail);
    expect(d.length).toBeLessThanOrEqual(160);
    expect(d.startsWith("Top 8 Finalist: IDBYTE 2017 Connected")).toBe(true);
    expect(d).not.toContain("...");
  });
  it("does not repeat the name when the text already has it", () => {
    const d = awardMetaDescription("IDBYTE 2017", "IDBYTE 2017 was a national startup competition where only eight finalists reached the stage in front of investors and media.", tail);
    expect(d.match(/IDBYTE 2017/g)).toHaveLength(1);
  });
});

import { beforeEach, describe, expect, it, vi } from "vitest";

const apiGet = vi.fn();
vi.mock("./api", () => ({ apiGet: (...a: unknown[]) => apiGet(...a) }));
import { waLink } from "./whatsapp";

beforeEach(() => {
  apiGet.mockReset();
});

describe("waLink", () => {
  it("strips non-digits into a wa.me link", async () => {
    apiGet.mockResolvedValue({ contact_phone: "+62 812-3456 7890" });
    expect(await waLink("id")).toBe("https://wa.me/6281234567890");
    expect(apiGet).toHaveBeenCalledWith("/settings/site", { lang: "id" });
  });
  it.each([
    ["empty string", { contact_phone: "" }],
    ["no digits", { contact_phone: "n/a" }],
    ["missing", {}],
    ["non-string", { contact_phone: 62812 }],
    ["null payload", null],
  ])("returns empty for %s", async (_n, payload) => {
    apiGet.mockResolvedValue(payload);
    expect(await waLink("en")).toBe("");
  });
  it("returns empty on API error", async () => {
    apiGet.mockImplementation(async () => {
      throw new Error("boom");
    });
    expect(await waLink("id")).toBe("");
  });
});

// Ali 2026-11-09: WhatsApp buttons open with a page-specific message, so a lead shows where it came from.
describe("waLink prefilled text", () => {
  beforeEach(() => apiGet.mockReset());
  it("is URL-encoded after ?text=", async () => {
    apiGet.mockResolvedValue({ contact_phone: "+62 813-8016-3758" });
    expect(await waLink("id", "Halo, saya ingin pelatihan AI & workshop?")).toBe(
      "https://wa.me/6281380163758?text=Halo%2C%20saya%20ingin%20pelatihan%20AI%20%26%20workshop%3F",
    );
  });
  it("stays empty when the phone is missing, even with text", async () => {
    apiGet.mockResolvedValue({});
    expect(await waLink("id", "x")).toBe("");
  });
});

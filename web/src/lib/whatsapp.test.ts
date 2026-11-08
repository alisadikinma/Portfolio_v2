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

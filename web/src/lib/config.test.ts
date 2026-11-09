import { describe, expect, it } from "vitest";
import { getConfig } from "./config";

describe("getConfig", () => {
  it("returns defaults outside production", () => {
    const c = getConfig({});
    expect(c.apiInternalUrl).toBe("http://127.0.0.1:8080/api");
    expect(c.siteUrl).toBe("https://alisadikinma.com");
    expect(c.ga4Id).toBe("");
    expect(c.internalToken).toBe("");
  });

  it("reads overrides from env", () => {
    const c = getConfig({
      API_INTERNAL_URL: "http://x/api",
      SITE_URL: "https://s.test",
      GA4_MEASUREMENT_ID: "G-1",
      INTERNAL_API_TOKEN: "tok",
    });
    expect(c).toEqual({
      apiInternalUrl: "http://x/api",
      siteUrl: "https://s.test",
      ga4Id: "G-1",
      internalToken: "tok",
    });
  });

  it("reads token in production", () => {
    expect(getConfig({ NODE_ENV: "production", INTERNAL_API_TOKEN: "t" }).internalToken).toBe("t");
  });

  it("treats empty strings as unset", () => {
    expect(getConfig({ API_INTERNAL_URL: "" }).apiInternalUrl).toBe("http://127.0.0.1:8080/api");
  });
});

import { requireInternalToken } from "./config";
describe("token split", () => {
  it("getConfig never throws for a missing token, even in production", () => {
    expect(() => getConfig({ NODE_ENV: "production" })).not.toThrow();
  });
  it("requireInternalToken throws in production when missing", () => {
    expect(() => requireInternalToken({ NODE_ENV: "production" })).toThrow(/MissingInternalTokenError/);
  });
  it("requireInternalToken returns token", () => {
    expect(requireInternalToken({ NODE_ENV: "production", INTERNAL_API_TOKEN: "t" })).toBe("t");
    expect(requireInternalToken({})).toBe("");
  });
});

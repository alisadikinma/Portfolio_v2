export interface AppConfig {
  apiInternalUrl: string;
  siteUrl: string;
  ga4Id: string;
  internalToken: string;
}

type Env = Record<string, string | undefined>;

export class MissingInternalTokenError extends Error {
  constructor() {
    super("MissingInternalTokenError: INTERNAL_API_TOKEN is required when NODE_ENV=production");
    this.name = "MissingInternalTokenError";
  }
}

const pick = (v: string | undefined, fallback: string): string => (v && v.length > 0 ? v : fallback);

/** Read paths use getConfig() and never need the token. Only the crawler-hit sender calls this. */
export function requireInternalToken(env: Env = process.env): string {
  const token = pick(env.INTERNAL_API_TOKEN, "");
  if (env.NODE_ENV === "production" && token === "") throw new MissingInternalTokenError();
  return token;
}

export function getConfig(env: Env = process.env): AppConfig {
  const internalToken = pick(env.INTERNAL_API_TOKEN, "");
  return {
    apiInternalUrl: pick(env.API_INTERNAL_URL, "http://127.0.0.1:8080/api"),
    siteUrl: pick(env.SITE_URL, "https://alisadikinma.com"),
    ga4Id: pick(env.GA4_MEASUREMENT_ID, ""),
    internalToken,
  };
}

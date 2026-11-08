/** Mirror of backend/config/ai_bots.php 'map'. Parity enforced by aiBots.test.ts. Keep order. */
export const AI_BOTS: Readonly<Record<string, string>> = {
  GPTBot: "GPTBot",
  "OAI-SearchBot": "OAI-SearchBot",
  "ChatGPT-User": "ChatGPT-User",
  ClaudeBot: "ClaudeBot",
  "Claude-Web": "Claude-Web",
  "Claude-User": "Claude-User",
  "Claude-SearchBot": "Claude-SearchBot",
  PerplexityBot: "PerplexityBot",
  "Perplexity-User": "Perplexity-User",
  "Meta-ExternalAgent": "Meta-ExternalAgent",
  Amazonbot: "Amazonbot",
  Bytespider: "Bytespider",
  CCBot: "CCBot",
  DuckAssistBot: "DuckAssistBot",
  "MistralAI-User": "MistralAI-User",
  GoogleOther: "GoogleOther",
};

/** Canonical bot name for a User-Agent (case-insensitive substring), or null. */
export function matchAiBot(userAgent: string | null | undefined): string | null {
  if (!userAgent) return null;
  const ua = userAgent.toLowerCase();
  for (const [key, name] of Object.entries(AI_BOTS)) {
    if (ua.includes(key.toLowerCase())) return name;
  }
  return null;
}

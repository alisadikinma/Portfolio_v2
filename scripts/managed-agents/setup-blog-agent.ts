/**
 * One-time setup: Create Environment + Blog Article Generator Agent.
 *
 * This creates persistent resources on Anthropic's infrastructure.
 * Run ONCE, then use the saved IDs for all future sessions.
 *
 * Usage:
 *   ANTHROPIC_API_KEY=sk-ant-... npx tsx setup-blog-agent.ts
 *
 * Prerequisites:
 *   1. Run upload-skills.ts first (creates skills/skill-ids.json)
 *   2. ANTHROPIC_API_KEY env var set
 */

import Anthropic from "@anthropic-ai/sdk";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SKILLS_IDS_FILE = path.resolve(__dirname, "../../skills/skill-ids.json");
const CONFIG_FILE = path.resolve(__dirname, "../../.claude/agent-config.json");

// Ensure .claude directory exists
const claudeDir = path.dirname(CONFIG_FILE);
if (!fs.existsSync(claudeDir)) {
  fs.mkdirSync(claudeDir, { recursive: true });
}

// Check if already configured
if (fs.existsSync(CONFIG_FILE)) {
  const existing = JSON.parse(fs.readFileSync(CONFIG_FILE, "utf-8"));
  console.log("Agent config already exists:");
  console.log(`  Agent ID:       ${existing.agent_id}`);
  console.log(`  Environment ID: ${existing.environment_id}`);
  console.log(`  Agent Version:  ${existing.agent_version}`);
  console.log("\nTo recreate, delete .claude/agent-config.json and run again.");
  process.exit(0);
}

// Load skill IDs
if (!fs.existsSync(SKILLS_IDS_FILE)) {
  console.error("Error: skills/skill-ids.json not found. Run upload-skills.ts first.");
  process.exit(1);
}
const skillIds: Record<string, string> = JSON.parse(
  fs.readFileSync(SKILLS_IDS_FILE, "utf-8")
);
console.log(`Loaded ${Object.keys(skillIds).length} skill IDs\n`);

const client = new Anthropic();

// ── Step 1: Create Environment ─────────────────────────────────────────────
console.log("Creating environment...");
const environment = await client.beta.environments.create({
  name: "blog-generator-env",
  config: {
    type: "cloud",
    networking: { type: "unrestricted" },
  },
});
console.log(`  Environment: ${environment.id}\n`);

// ── Step 2: Create Agent ───────────────────────────────────────────────────
console.log("Creating Blog Article Generator agent...");
const agent = await client.beta.agents.create({
  name: "Blog Article Generator",
  description: "Automated content pipeline for alisadikinma.com. Finds trending tech topics, writes bilingual articles (EN + ID) using HOOK-FORESHADOW-BODY-PEAK-CTA framework, generates hero images via GeminiGen API, and saves as draft via blog API.",
  model: "claude-opus-4-6",
  system: `You are an automated blog content pipeline for alisadikinma.com.

Your job: find a trending tech topic, write a bilingual article (EN + ID),
generate a hero image via GeminiGen API, and save as draft via the blog API.

WORKFLOW:
1. Use your Skills knowledge to understand the writing framework, SEO/GEO rules,
   image generation techniques, and Indonesian writing style.
2. WebFetch Google Trends RSS (geo=ID and geo=US) to find trending tech topics.
3. POST to the blog API check-duplicate endpoint to avoid repeats.
4. Generate a 1,800-2,400 word article in English using HOOK-FORESHADOW-BODY-PEAK-CTA.
5. Transcreate (NOT translate) to Indonesian with code-mixing, kita/lo/gue pronouns,
   particles (sih, dong, banget), and warm opening style.
6. Generate a hero image prompt using the 8-element structure, then WebFetch to
   GeminiGen API (imagen-pro, 16:9, Photorealistic).
7. POST the complete article to the blog admin API with EN + ID translations.

RULES:
- ALWAYS save as draft (published: false). NEVER auto-publish.
- Quality > quantity: skip if no good unique topic found.
- Each article must be genuinely useful, not SEO-stuffed filler.
- Indonesian content: keep ALL tech terms in English, use code-mixing naturally.
- Target: Flesch-Kincaid grade 7-9, statistic every 150-200 words (GEO).

API ENDPOINTS:
- Blog API: POST https://alisadikinma.com/api/admin/posts
  Auth: Bearer {BLOG_API_TOKEN} (in environment variables)
  Body: { category_id, title, slug, content, featured_image, tags, published: false, translations: [{language, title, slug, content, meta_title, meta_description, ...}] }
- Dedup: POST https://alisadikinma.com/api/posts/check-duplicate
  Body: { title: "...", similarity_threshold: 70 }
- Image: POST https://api.geminigen.ai/uapi/v1/generate_image
  Headers: x-api-key: {GEMINIGEN_API_KEY}
  Body (form-data): prompt, model=imagen-pro, aspect_ratio=16:9, style=Photorealistic
  If status=1, poll: GET https://api.geminigen.ai/uapi/v1/history/{uuid}
- Trends: https://trends.google.com/trending/rss?geo=ID and ?geo=US`,
  tools: [
    { type: "agent_toolset_20260401", default_config: { enabled: true } },
  ],
  skills: Object.values(skillIds).map((id) => ({
    type: "custom" as const,
    skill_id: id as string,
    version: "latest",
  })),
});
console.log(`  Agent: ${agent.id} (version: ${agent.version})\n`);

// ── Step 3: Save Config ────────────────────────────────────────────────────
const config = {
  agent_id: agent.id,
  agent_version: agent.version,
  environment_id: environment.id,
  skill_ids: skillIds,
  created_at: new Date().toISOString(),
};

fs.writeFileSync(CONFIG_FILE, JSON.stringify(config, null, 2));
console.log(`Config saved to ${CONFIG_FILE}\n`);

console.log("========================================");
console.log("  SETUP COMPLETE");
console.log("========================================");
console.log(`Agent ID:       ${agent.id}`);
console.log(`Environment ID: ${environment.id}`);
console.log(`Skills:         ${Object.keys(skillIds).length} attached`);
console.log("");
console.log("NEXT STEPS:");
console.log("1. Create a Sanctum token in your admin panel (Automation Tokens)");
console.log("2. Go to https://claude.ai/code/scheduled");
console.log("3. Create a new scheduled task:");
console.log("   - Name: Blog Article Generator");
console.log("   - Schedule: Hourly");
console.log("   - Repository: Portfolio_v2");
console.log("   - Environment variables: BLOG_API_TOKEN, GEMINIGEN_API_KEY");
console.log("   - Network: Full access");
console.log("4. Or run: /schedule 'Blog Article Generator' hourly");

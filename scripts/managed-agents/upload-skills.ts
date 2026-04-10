/**
 * Upload Custom Skills to Claude Managed Agents Skills API.
 *
 * Reads each skills/{name}/SKILL.md, creates or updates the skill,
 * and saves the mapping to skills/skill-ids.json.
 *
 * Usage:
 *   ANTHROPIC_API_KEY=sk-ant-... npx tsx upload-skills.ts
 *
 * Prerequisites:
 *   npm install (in this directory)
 *   ANTHROPIC_API_KEY env var set
 */

import Anthropic from "@anthropic-ai/sdk";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SKILLS_DIR = path.resolve(__dirname, "../../skills");
const IDS_FILE = path.join(SKILLS_DIR, "skill-ids.json");

const client = new Anthropic();

// Load existing IDs or start fresh
let skillIds: Record<string, string> = {};
if (fs.existsSync(IDS_FILE)) {
  skillIds = JSON.parse(fs.readFileSync(IDS_FILE, "utf-8"));
  console.log(`Loaded ${Object.keys(skillIds).length} existing skill IDs from skill-ids.json`);
}

// Find all skill directories
const skillDirs = fs
  .readdirSync(SKILLS_DIR)
  .filter((d) => {
    const fullPath = path.join(SKILLS_DIR, d);
    return fs.statSync(fullPath).isDirectory() && fs.existsSync(path.join(fullPath, "SKILL.md"));
  });

console.log(`\nFound ${skillDirs.length} skills to upload:\n`);

for (const dir of skillDirs) {
  const skillPath = path.join(SKILLS_DIR, dir, "SKILL.md");
  const content = fs.readFileSync(skillPath, "utf-8");
  const wordCount = content.split(/\s+/).length;

  try {
    if (!skillIds[dir]) {
      // Create new skill
      const skill = await client.beta.skills.create(
        { name: dir },
        { headers: { "anthropic-beta": "skills-2025-10-02" } }
      );
      skillIds[dir] = skill.id;
      console.log(`  [CREATE] ${dir} -> ${skill.id}`);
    } else {
      console.log(`  [EXISTS] ${dir} -> ${skillIds[dir]}`);
    }

    // Upload new version
    await client.beta.skills.versions.create(
      skillIds[dir],
      { content },
      { headers: { "anthropic-beta": "skills-2025-10-02" } }
    );
    console.log(`           Uploaded version (${wordCount} words)`);
  } catch (error: any) {
    console.error(`  [ERROR]  ${dir}: ${error.message || error}`);
  }
}

// Save IDs
fs.writeFileSync(IDS_FILE, JSON.stringify(skillIds, null, 2));
console.log(`\nSkill IDs saved to ${IDS_FILE}`);
console.log("\nDone! Skills are ready to be referenced by agents.");

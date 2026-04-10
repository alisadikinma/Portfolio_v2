/**
 * Test: Start a session with the Blog Agent and send a test message.
 *
 * Usage:
 *   ANTHROPIC_API_KEY=sk-ant-... npx tsx test-session.ts
 *
 * Prerequisites:
 *   1. Run setup-blog-agent.ts first (creates .claude/agent-config.json)
 *   2. ANTHROPIC_API_KEY env var set
 */

import Anthropic from "@anthropic-ai/sdk";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const CONFIG_FILE = path.resolve(__dirname, "../../.claude/agent-config.json");

if (!fs.existsSync(CONFIG_FILE)) {
  console.error("Error: .claude/agent-config.json not found. Run setup-blog-agent.ts first.");
  process.exit(1);
}

const config = JSON.parse(fs.readFileSync(CONFIG_FILE, "utf-8"));
console.log(`Agent: ${config.agent_id}`);
console.log(`Environment: ${config.environment_id}\n`);

const client = new Anthropic();

// Create session
console.log("Creating test session...");
const session = await client.beta.sessions.create({
  agent: config.agent_id,
  environment_id: config.environment_id,
  title: "Test: Blog Agent Skills Check",
});
console.log(`Session: ${session.id} (status: ${session.status})\n`);

// Open stream first, then send message
const stream = await client.beta.sessions.stream(session.id);

await client.beta.sessions.events.send(session.id, {
  events: [
    {
      type: "user.message",
      content: [
        {
          type: "text",
          text: "List all your available skills and briefly describe what each one covers. Don't generate an article — just confirm you can access the skills knowledge.",
        },
      ],
    },
  ],
});

console.log("Streaming agent response:\n");
console.log("─".repeat(60));

for await (const event of stream) {
  if (event.type === "agent.message") {
    for (const block of event.content) {
      if (block.type === "text") {
        process.stdout.write(block.text);
      }
    }
  } else if (event.type === "session.status_idle") {
    if (event.stop_reason?.type !== "requires_action") {
      console.log("\n" + "─".repeat(60));
      console.log("\nAgent finished.");
      break;
    }
  } else if (event.type === "session.status_terminated") {
    console.log("\n" + "─".repeat(60));
    console.log("\nSession terminated.");
    break;
  }
}

// Cleanup
console.log("\nArchiving session...");
// Small delay to avoid post-idle status race
await new Promise((r) => setTimeout(r, 1000));
try {
  const s = await client.beta.sessions.retrieve(session.id);
  if (s.status !== "running") {
    await client.beta.sessions.archive(session.id);
    console.log("Session archived.");
  }
} catch {
  console.log("Session cleanup skipped.");
}

console.log("\nTest complete!");

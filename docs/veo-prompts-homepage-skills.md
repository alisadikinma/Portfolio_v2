# Portfolio Homepage Skill Videos — NB2 Image + VEO I2V Pipeline

**Pipeline:** NB2 Start Frame (4K image) → VEO 3.1 I2V (8s video from image)
**Specs:** 4 videos, 8s each, 720p output, 16:9, abstract (no people), seamless loop feel
**Models:** NB2 (Gemini 3.1 Flash Image) for keyframes → VEO 3.1 I2V for video

---

## REFERENCE IMAGES — Master List

Download semua logo sebelum generate. Upload sebagai reference images di NB2 bersama prompt.
Setiap Image section di bawah punya tabel "Required Reference Images" sendiri.

> **Tips:** Download versi logo dengan background transparan (PNG) atau background gelap. Logo putih akan clash dengan dark void aesthetic.

| # | Filename | Used In | Source |
|---|----------|---------|--------|
| 1 | `logo-claude.png` | Image 1, 3 | anthropic.com/brand — amber starburst, transparent bg |
| 2 | `logo-claude-code.png` | Image 1 | claude.ai/code atau screenshot CLI — dark bg |
| 3 | `logo-antigravity.png` | Image 1 | Google Antigravity branding / app icon |
| 4 | `logo-n8n.png` | Image 2 | n8n.io/press — orange wordmark, transparent bg |
| 5 | `logo-openclaw.png` | Image 3 | OpenClaw website/GitHub repo |
| 6 | `logo-veo.png` | Image 4 | Google DeepMind VEO branding |
| 7 | `logo-kling.png` | Image 4 | klingai.com brand page |
| 8 | `logo-seedance.png` | Image 4 | Seedance / ByteDance branding |

---

## PHASE A: NB2 START FRAME IMAGES

Generate in Google AI Studio using NB2 (Gemini Flash Image).
Settings: **4K resolution, 16:9 aspect ratio, High Thinking mode, CFG 5-7**
**Upload reference logos** listed above bersama setiap prompt.

---

### Image 1: Vibe Coding
**Output:** `skill-vibe-coding-start.png`
**Brands:** Claude Code, Claude (Anthropic), Google Antigravity

**Required Reference Images — upload bersama prompt:**

| # | Asset | Filename | Source |
|---|-------|----------|--------|
| 1 | Claude AI logo (amber starburst) | `logo-claude.png` | anthropic.com/brand, transparent/dark bg |
| 2 | Claude Code icon/wordmark | `logo-claude-code.png` | Screenshot Claude Code CLI atau claude.ai/code |
| 3 | Google Antigravity logo | `logo-antigravity.png` | Google Antigravity branding / app icon |

```
16:9 aspect ratio.

SUBJECT: A cinematic dark workspace showing a large curved ultrawide monitor 
displaying a Claude Code terminal session. The terminal has a dark background 
(#0A0A0D) with a conversation-style interface: amber (#D4A843) AI response 
blocks alternating with cyan (#06B6D4) code output blocks. Visible code 
includes Vue.js components and TypeScript functions. A blinking amber cursor 
at the bottom with "> " prompt symbol.

To the LEFT of the monitor: a vertical floating holographic panel showing a 
file explorer tree with project files: "App.vue", "useAuth.js", "api.ts", 
"tailwind.config.js". At the top of this panel, the Claude AI logo — a 
glowing amber starburst symbol (simple 6-pointed radial burst) — with the 
text "CLAUDE CODE" directly below in small uppercase tracked sans-serif.

To the RIGHT of the monitor: a secondary floating panel displaying a code 
diff view with green added lines and red removed lines, suggesting AI-assisted 
code review. Below the diff, a small status bar reading "vibe-coding session 
active" with a pulsing green dot.

FLOATING ELEMENTS around the workspace:
- A translucent pill badge near top-left reading "AI-POWERED DEV" in amber
- A pill badge near bottom-right reading "PROMPT TO PRODUCTION" in cyan  
- Small text "gaspol-dev" in monospace near the left panel
- The Anthropic starburst logo watermark subtly glowing at 15% opacity 
  floating in the background void, large scale (spanning 30% of background)
- A small floating badge near the right panel showing the Google Antigravity 
  logo — the stylized "AG" mark with Google's four-color palette — with text 
  "ANTIGRAVITY" in small uppercase beneath it

LIGHTING: Monitor and panels self-illuminate the scene. Warm amber dominant 
from AI response blocks. Cyan accent from code syntax. Dark matte desk 
surface barely visible below monitor reflecting screen light. Cinematic 4:1 
contrast. 3200K tungsten warmth from amber elements.

CAMERA: Shot on 35mm f/2.8. Slight low angle (10-degree), looking up at 
the monitor setup. Medium wide shot. Shallow depth of field — monitor sharp, 
floating side panels at 80% sharpness, background elements soft.

ENVIRONMENT: Deep charcoal void (#0A0A0D) surrounding the workspace. No 
visible walls or ceiling. Subtle volumetric haze at 8% behind the monitor. 
Floating micro-particles (code symbols: brackets, arrows) drift at various 
depths. Matte dark desk surface fades into void at edges.

COMPOSITION: Monitor centered, slightly left of center. Claude logo on left 
panel clearly readable. All text elements sharp and legible. Critical content 
in central 60%. Generous negative space at frame edges dissolving into void.

REFERENCE IMAGE INJECTION:
- Maintain exact logo appearance from reference image: logo-claude.png 
  — render as the glowing starburst on left panel header and as the large 
  15% opacity background watermark.
- Maintain exact logo appearance from reference image: logo-claude-code.png 
  — render as the icon/label in the central terminal title bar next to 
  "CLAUDE CODE" text.
- Maintain exact logo appearance from reference image: logo-antigravity.png 
  — render as the floating badge near the right panel with "ANTIGRAVITY" 
  text beneath.

TECHNICAL: Kodak Vision3 500T tungsten. All text rendered in bold clean 
sans-serif (labels) and monospace (code). Crystal-clear focus on monitor 
content. Film grain 4%. No watermarks. No cartoon effects.
```

---

### Image 2: AI Automation
**Output:** `skill-ai-automation-start.png`
**Brands:** n8n

**Required Reference Images — upload bersama prompt:**

| # | Asset | Filename | Source |
|---|-------|----------|--------|
| 4 | n8n logo (wordmark) | `logo-n8n.png` | n8n.io/press, orange/white, transparent bg |

```
16:9 aspect ratio.

SUBJECT: A cinematic overhead-angled view of a large dark holographic workspace 
displaying a visual workflow automation canvas. The canvas shows a node-based 
pipeline: 8-10 connected nodes arranged in a left-to-right flow pattern on a 
dark grid surface. 

Node types visible:
- TRIGGER node (leftmost): hexagonal, glowing green, with a lightning bolt icon
- PROCESS nodes (middle): rounded rectangles in amber (#D4A843), containing 
  miniature gear icons and labels like "Transform", "Filter", "HTTP Request"
- OUTPUT nodes (rightmost): rounded rectangles in cyan (#06B6D4), with labels 
  like "Send Email", "Update DB", "Webhook"
- Connection lines between nodes glow with animated data flow (bright pulses 
  traveling left to right along the lines)

BRAND ELEMENTS:
- Top-left corner: the n8n logo — a clean geometric "n8n" wordmark in white 
  on a dark rounded rectangle badge. Next to it, the text "WORKFLOW AUTOMATION" 
  in small uppercase tracked sans-serif, amber color.
- Top-right corner: a small status indicator showing "CONNECTED" with a 
  green dot, and "12 ACTIVE WORKFLOWS" in dim white monospace text.
- A floating pill badge center-bottom reading "56+ AUTOMATIONS DEPLOYED" in 
  cyan monospace text.

SECONDARY ELEMENTS:
- A small floating panel in the lower-right showing a real-time execution log 
  with timestamps and green "SUCCESS" status badges
- Floating data packet visualizations (small glowing cubes) traveling along 
  the connection lines between nodes

LIGHTING: Self-illuminated nodes and connection lines as primary light. Amber 
glow from process nodes, cyan glow from output nodes, green accent from 
trigger node. Dark matte grid surface reflects node light softly. Cinematic 
4:1 contrast. CineStill 800T halation on brightest nodes.

CAMERA: Shot on 35mm f/4. High angle (30-degree overhead tilt) looking down 
at the workflow canvas. Medium wide shot capturing full pipeline. Deep depth 
of field — all nodes equally sharp.

ENVIRONMENT: Deep matte black surface (#050505) with subtle grid lines at 3% 
opacity forming a technical blueprint feel. No visible walls. Volumetric 
ground fog at 8% hugging the surface, backlit by node glow. Floating 
micro-particles above the canvas.

COMPOSITION: Workflow pipeline spans center 70% of frame horizontally. Trigger 
node at left-third, output nodes at right-third. Brand logos in top corners, 
not competing with main content. Execution log panel in lower-right quadrant. 
Central 60% rule applied.

REFERENCE IMAGE INJECTION:
- Maintain exact logo appearance from reference image: logo-n8n.png 
  — render as the logo badge in the top-left corner on a dark rounded 
  rectangle, matching exact n8n brand colors and typography.

TECHNICAL: CineStill 800T color science. All text and logos sharp and legible. 
Node labels in clean sans-serif. Log text in monospace. Film grain 5%. 
Crystal-clear across entire frame. No watermarks. No cartoon effects.
```

---

### Image 3: AI Agents
**Output:** `skill-ai-agents-start.png`
**Brands:** OpenClaw, Claude

**Required Reference Images — upload bersama prompt:**

| # | Asset | Filename | Source |
|---|-------|----------|--------|
| 5 | OpenClaw logo | `logo-openclaw.png` | OpenClaw website / GitHub repo |
| 6 | Claude AI logo (reuse #1) | `logo-claude.png` | Same as Image 1 |

```
16:9 aspect ratio.

SUBJECT: A cinematic dark command center visualization showing a multi-agent 
orchestration system. Three distinct AI agent interfaces arranged in a 
triangular formation, each represented as a floating holographic terminal:

AGENT 1 (left, largest — 35% frame): A terminal labeled "ORCHESTRATOR" at 
the top in amber (#D4A843) uppercase. Below the label, the OpenClaw logo — 
a stylized claw/paw mark icon in white. The terminal displays a task queue 
with 5-6 task items, each with status indicators (green dots for complete, 
amber dots for in-progress, gray for pending). Connecting lines radiate 
outward from this terminal to the other two agents.

AGENT 2 (right-top — 25% frame): A terminal labeled "RESEARCHER" in cyan 
(#06B6D4). Shows a miniature web search results interface with highlighted 
text excerpts. The Claude starburst logo (amber) sits in the terminal's 
title bar, indicating this agent runs on Claude.

AGENT 3 (right-bottom — 25% frame): A terminal labeled "EXECUTOR" in indigo 
(#5E6AD2). Shows a code execution environment with a terminal output 
scrolling results. A small gear icon in the title bar.

CONNECTING ELEMENTS:
- Luminous data flow lines connecting all three terminals, with visible 
  directional arrows showing message passing between agents
- Micro-particle swarms traveling along the connection lines (representing 
  data/context being shared)
- At the intersection point of all three connections, a small glowing hub 
  node pulsing rhythmically

BRAND ELEMENTS:
- Floating pill badge top-center: "MULTI-AGENT SYSTEM" in amber uppercase
- Small badge near Agent 1: "OpenClaw" in monospace
- Small badge near Agent 2: "Claude API" in monospace  
- Floating badge bottom-center: "AUTONOMOUS TASK EXECUTION" in cyan

LIGHTING: Each terminal self-illuminates with its accent color (amber, cyan, 
indigo). Connection lines provide secondary illumination. Where amber and 
cyan light zones overlap, subtle green-tinted edge visible. Deep charcoal 
void background. Dramatic 4:1 contrast.

CAMERA: Shot on 50mm f/2.0. Eye-level, straight. Medium wide shot capturing 
all three terminals and connections. Shallow depth of field — orchestrator 
terminal sharp, other two at 75% sharpness.

ENVIRONMENT: Pure deep charcoal void (#0A0A0D). Volumetric micro-fog within 
50cm of each terminal creating localized atmosphere halos. No ground plane. 
Floating data symbols (JSON brackets, arrows, dots) drift slowly in 
background.

COMPOSITION: Orchestrator terminal at left-third. Two smaller terminals 
stacked at right-third. Connection hub at center. Brand badges positioned 
outside critical content zone. Central 60% rule. Generous negative space.

REFERENCE IMAGE INJECTION:
- Maintain exact logo appearance from reference image: logo-openclaw.png 
  — render as the icon on the Orchestrator terminal, directly below the 
  "ORCHESTRATOR" label.
- Maintain exact logo appearance from reference image: logo-claude.png 
  — render as the amber starburst icon in the Researcher terminal title 
  bar, indicating Claude-powered agent.

TECHNICAL: Kodak Vision3 500T. Terminal text in monospace, labels in 
sans-serif. All text legible at 4K. Crystal-clear on orchestrator terminal. 
Film grain 4%. No watermarks. No cartoon effects.
```

---

### Image 4: AI Video Generation
**Output:** `skill-ai-video-start.png`
**Brands:** VEO 3.1, Kling AI, Seedance 2.0

**Required Reference Images — upload bersama prompt:**

| # | Asset | Filename | Source |
|---|-------|----------|--------|
| 7 | VEO 3.1 logo/wordmark | `logo-veo.png` | Google DeepMind VEO branding |
| 8 | Kling AI logo | `logo-kling.png` | klingai.com brand page |
| 9 | Seedance 2.0 logo | `logo-seedance.png` | Seedance / ByteDance branding |

```
16:9 aspect ratio.

SUBJECT: A cinematic dark creative studio visualization showing an AI video 
production command center. Three floating video generation platform interfaces 
arranged in a staggered formation, each showing active video generation:

CENTRAL DISPLAY (45% frame width, foreground): The largest panel — a dark 
video generation interface. At the top, bold text "VEO 3.1" in warm amber 
(#D4A843) uppercase with a small sparkle icon. Below, a prompt input bar 
with amber text "Cinematic aerial establishing shot of..." being typed with 
blinking cursor. Below the prompt, a generation preview showing a partially 
rendered video frame — left 60% fully rendered (warm amber cinematic 
landscape scene), right 40% showing pixel-painting in progress with cyan 
(#06B6D4) scan-line render boundary. Timeline scrubber at bottom with 
keyframe markers. Duration badge: "8s". Resolution badge: "720p".

LEFT DISPLAY (25% frame, behind-left, slightly overlapping): A secondary 
panel labeled "KLING AI" at the top in cyan uppercase. Shows a completed 
video preview with a play button overlay. Below the preview, settings: 
"Professional Mode" badge, "1080p" badge. A vertical stack of 3 small 
generation history thumbnails with green checkmarks.

RIGHT DISPLAY (25% frame, behind-right, slightly overlapping): A third 
panel labeled "SEEDANCE 2.0" at the top in indigo (#5E6AD2) uppercase. 
Shows a generation-in-progress state with a circular progress indicator 
at 65%. Below, text "Motion Score: 94" in green. Settings show "2K native" 
and "15s" duration badges.

FLOATING ELEMENTS:
- Film strip fragments (3-4 short segments) floating in the void at various 
  angles, each containing miniature rendered frames glowing softly
- Floating pill badge top-center: "AI VIDEO PRODUCTION" in amber uppercase
- Floating pill badge bottom-center: "VEO 3.1 | KLING AI | SEEDANCE 2.0" 
  in cyan monospace
- Small floating badge near bottom-left: "MULTI-PLATFORM PIPELINE" in 
  dim white

LIGHTING: Three displays as primary illumination — VEO panel in amber, 
Kling panel in cyan, Seedance panel in indigo. Film strips glow with their 
content. Where amber and cyan light zones meet, subtle warm green edge. 
Teal and orange Hollywood grade. 2:1 lighting ratio. Deep charcoal void 
background.

CAMERA: Shot on 50mm f/2.0. Eye-level, straight. Medium wide shot. Shallow 
depth of field — central VEO display razor sharp, Kling and Seedance panels 
at 75% sharpness. Warm-gold bokeh circles (8-10 circles at 5% opacity) 
float in foreground and background.

ENVIRONMENT: Deep charcoal void (#0A0A0D). No physical studio. Film strip 
fragments float at various depths creating layered parallax. Volumetric 
haze at 6% behind displays. Floating dust particles in display light spill.

COMPOSITION: VEO display slightly left of center (dominant). Kling display 
upper-left behind. Seedance display upper-right behind. Staggered depth 
creates Z-axis layering. Brand names all clearly readable. Film strips in 
upper-right and lower-left quadrants for balance. Central 60% rule.

REFERENCE IMAGE INJECTION:
- Maintain exact logo appearance from reference image: logo-veo.png 
  — render as the "VEO 3.1" branding at the top of the central display, 
  matching exact Google/DeepMind styling.
- Maintain exact logo appearance from reference image: logo-kling.png 
  — render as the "KLING AI" label/icon at the top of the left display.
- Maintain exact logo appearance from reference image: logo-seedance.png 
  — render as the "SEEDANCE 2.0" label/icon at the top of the right display.

TECHNICAL: Teal and orange grade. Kodak Vision3 500T. All UI text sharp and 
legible — brand names in their distinctive styling, settings in monospace, 
labels in clean sans-serif. Film grain 5%. Bokeh circles from f/2.0. 
Crystal-clear on VEO central display. No watermarks. No cartoon effects.
```

---

## PHASE B: VEO 3.1 I2V PROMPTS

After generating all 4 NB2 images, use VEO 3.1 **Image-to-Video mode**.
Upload start frame → paste I2V prompt.
Settings: **720p, 16:9, 8 seconds, Highest Quality Audio**

---

### VEO I2V 1: Vibe Coding
**Upload:** `skill-vibe-coding-start.png`
**Output:** `frontend/public/videos/vibe-coding.mp4`

```
~8s, 720p, 16:9.

Camera: Smooth dolly push-in toward monitor over 8 seconds, slight 3-degree 
drift right. Slow, contemplative pace.

Subject: Code streams in the Claude Code terminal — new AI response blocks 
appear with typing animation, followed by code output that auto-writes itself 
line by line. Cursor moves actively between lines. Auto-complete ghost text 
materializes on side panels then solidifies. File tree on left panel 
highlights different files as the session progresses. Diff view on right 
panel updates with new green lines appearing.

Ambient motion: Floating code particles drift slowly between panels. Claude 
starburst in background pulses subtly with 3-second rhythm. Volumetric haze 
shifts with screen brightness changes. Desk surface reflects monitor flicker.

Maintain exact lighting, environment, composition, all logos and branding 
from reference frame throughout.

Audio: Ambient: low digital processing hum, soft keyboard clicks rhythmically 
spaced. SFX: subtle data chirp on each AI response block appearing, gentle 
electric tone when code auto-completes. No music, no voice, no subtitles, 
no audience sounds, no text overlays.

16:9 output.
```

---

### VEO I2V 2: AI Automation
**Upload:** `skill-ai-automation-start.png`
**Output:** `frontend/public/videos/ai-automation.mp4`

```
~8s, 720p, 16:9.

Camera: Static high-angle shot, very slow clockwise rotation at 2-degrees 
per second. No push-in. Locked distance.

Subject: Data flow pulses activate in sequence — trigger node fires first 
(green flash), then pulses travel along connection lines left to right 
through each process node (amber flash on arrival), reaching output nodes 
(cyan flash). Each node brightens for 0.5 seconds on activation then dims. 
Execution log panel in lower-right updates with new "SUCCESS" entries 
appearing. Floating data cubes travel along connection lines at varied speeds. 
One dormant connection activates mid-clip, drawing a new glowing line 
between two previously unconnected nodes.

Ambient motion: Ground fog churns slowly, backlit by node activations. 
Grid lines on surface pulse faintly with data flow rhythm. Micro-particles 
above canvas drift with subtle turbulence from node energy.

Maintain exact lighting, surface, all node positions, logos, and branding 
from reference frame throughout.

Audio: Ambient: deep resonant bass drone, electrical current hum. SFX: soft 
chime on each node activation, gentle data whoosh on pulse travel, subtle 
click when new connection line draws. No music, no voice, no subtitles, 
no audience sounds, no text overlays.

16:9 output.
```

---

### VEO I2V 3: AI Agents
**Upload:** `skill-ai-agents-start.png`
**Output:** `frontend/public/videos/ai-agents.mp4`

```
~8s, 720p, 16:9.

Camera: Very slow orbit shot, 15-degree arc around the terminal formation 
over 8 seconds. Gentle, weighted movement.

Subject: The orchestrator terminal (left) dispatches tasks — a task item 
changes from gray pending to amber in-progress with a brief flash. Data 
pulses travel along connection lines from orchestrator to researcher and 
executor terminals. Researcher terminal (right-top) displays new search 
results appearing with scroll animation. Executor terminal (right-bottom) 
shows code output streaming line by line. Central hub node pulses brighter 
each time data passes through it. Task statuses update: one item flips from 
amber to green (complete) with a subtle checkmark animation.

Ambient motion: Particle swarms along connection lines accelerate during 
data transfers, slow between. Volumetric fog halos around each terminal 
pulse gently with terminal activity. Floating JSON symbols in background 
drift slowly.

Maintain exact lighting, terminal positions, all logos and branding from 
reference frame throughout.

Audio: Ambient: vast spatial reverb, low electronic resonance. SFX: soft 
ping on task dispatch, crystalline tone on data arriving at terminal, brief 
metallic click on task completion. No music, no voice, no subtitles, 
no audience sounds, no text overlays.

16:9 output.
```

---

### VEO I2V 4: AI Video Generation
**Upload:** `skill-ai-video-start.png`
**Output:** `frontend/public/videos/ai-video.mp4`

```
~8s, 720p, 16:9.

Camera: Smooth tracking shot slightly left to right, following the render 
progression in the preview area. Gentle dolly push-in from current framing 
to slightly closer over 8 seconds.

Subject: In the central display, the pixel-painting render boundary advances 
steadily from left to right, completing more of the preview frame. The 
prompt text in the input field continues typing itself character by character. 
When the preview fully renders at ~5 seconds, a brief amber completion 
flash, and a new thumbnail appears in the generation history panel on the 
left with a green checkmark. Settings panel values remain static. The 
"Generate" button pulses once after completion.

Ambient motion: Floating film strip fragments rotate slowly on their axes. 
Foreground bokeh circles drift gently with parallax from camera movement. 
Dust particles in display light spill drift lazily. Timeline scrubber at 
bottom advances with playhead moving right.

Maintain exact lighting, UI layout, all logos and branding from reference 
frame throughout.

Audio: Ambient: soft cinematic room tone, gentle mechanical processing hum. 
SFX: rapid pixel rendering clicks at 30% volume during active generation, 
soft completion chime when preview fully renders, subtle UI click when 
thumbnail appears in history. No music, no voice, no subtitles, no audience 
sounds, no text overlays.

16:9 output.
```

---

## Negative Prompt (Apply to ALL NB2 and VEO)

```
No subtitles, no text overlays, no watermarks, no blurry faces, 
no cartoon effects, no audience sounds, no laugh track, no people, 
no human faces, no human hands, no human bodies.
```

---

## Production Checklist

```
PHASE A — NB2 Images (Google AI Studio, Gemini Flash Image)
  [ ] Image 1: Vibe Coding (Claude Code + Claude logos)
  [ ] Image 2: AI Automation (n8n + Zapier logos)
  [ ] Image 3: AI Agents (OpenClaw + Claude logos)
  [ ] Image 4: AI Video Gen (Google AI Studio + VEO 3.1 logos)
  Settings: 4K, 16:9, High Thinking, CFG 5-7

PHASE B — VEO I2V Videos (Google AI Studio, VEO 3.1)
  [ ] Video 1: Upload vibe-coding-start.png → generate → vibe-coding.mp4
  [ ] Video 2: Upload ai-automation-start.png → generate → ai-automation.mp4
  [ ] Video 3: Upload ai-agents-start.png → generate → ai-agents.mp4
  [ ] Video 4: Upload ai-video-start.png → generate → ai-video.mp4
  Settings: 720p, 16:9, 8s, Highest Quality Audio, I2V mode

PHASE C — Deploy
  [ ] Copy all .mp4 to frontend/public/videos/
  [ ] Refresh browser at localhost:5173
  [ ] Verify auto-play on scroll for all 4 sections
```

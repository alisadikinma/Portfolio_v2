> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

## Goal

Upgrade the article-content-writer plugin's image prompt quality from generic ~50-word prompts to cinematic ~300-500 word prompts matching the carousel plugin standard. Port the 8-element WOW framework, 5-paragraph structure, cinematography lookup tables (LUT), and example prompts from `D:\Projects\claude-plugin\ai-image-carousel-prompt-gen\` into `D:\Projects\claude-plugin\article-content-writer\`. This is a pure plugin enhancement — backend and frontend untouched.

## Architecture Context

**From Portfolio_v2 CLAUDE.md:**
- Article generation runs via Claude Code CLI on VPS (SSH driver)
- Split pipeline: article-prep (Sonnet) → article-write (Opus) → article-score (Sonnet)
- Reference files injected via `--append-system-prompt-file refs-{prep,write,score}.md`
- Compiled refs built from individual files via `scripts/compile-references.sh`
- `refs-write.md` (~49KB) is where image prompt generation guidance lives — this bundle grows with changes

**From article-content-writer plugin structure:**
- 11 reference files in `references/`
- 4 skills: article-prep, article-write, article-gen, article-writer agent
- Image prompts generated during Step 4 (Write) in article-write/article-gen
- Plugin currently installed on VPS at:
  - `/home/claudesn/.claude/plugins/cache/alisadikinma-ai-content-suite/article-content-writer/2.3.0/`
  - `/home/claudesn/.claude/plugins/cache/alisadikinma-ai-content-suite/article-content-writer/2.0.0/`

**From carousel plugin source (port targets):**
- `prompt-formulas.md` (1,140 lines / ~50KB) — 8-element WOW framework, 5-paragraph structure, formatting rules, template structures
- `cinematography-lut.md` (196 lines / ~12KB) — lookup tables (emotion, lighting, color temp, cinematographers, film stocks, shot types, atmosphere)
- `hook-visual-library.md` (798 lines / ~35KB) — expression libraries, lighting presets, camera angle bank

## Tech Stack

- Markdown reference files (no code)
- Bash script for compilation (existing `scripts/compile-references.sh`)
- No new dependencies

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---------|-----------|----------|---------|--------|
| 8-element WOW framework | `prompt-formulas.md` lines 74-86, 259-272 | File in carousel plugin | Yes | Adapt + port to `image-prompt-guide.md` |
| 5-paragraph structure rule | `prompt-formulas.md` lines 39-49 | File in carousel plugin | Yes | Adapt + port to `image-prompt-guide.md` |
| Cinematography LUT (lighting/color/cinematographers) | `cinematography-lut.md` (full) | File in carousel plugin | Yes | Copy to new file `cinematography-lut.md` in article plugin |
| Expression libraries (non-hook specific) | `hook-visual-library.md` Section 1-2 | File in carousel plugin | Yes | Extract generic parts → merge into new `cinematography-lut.md` |
| Example cinematic prompts | N/A | Need to author | No | Write 3 example prompts in `image-prompt-guide.md` matching blog context |
| Compiled refs bundle | `scripts/compile-references.sh` | Existing script | Yes | Modify to include new LUT file in `refs-write.md` |
| Plugin deployment to VPS | File copy via curl | GitHub raw URL | Yes | Existing pattern from prior plugin deploys |

## Phases

---

### Phase 1: Port cinematography-lut.md as NEW reference

**Estimated time:** 10 minutes

**Files:**
- Read: `D:\Projects\claude-plugin\ai-image-carousel-prompt-gen\references\cinematography-lut.md` (source)
- Create: `D:\Projects\claude-plugin\article-content-writer\references\cinematography-lut.md`

**Steps:**
1. Read source file (196 lines)
2. Copy content verbatim as a starting baseline
3. Remove carousel-specific sections (hook-slide-specific expression mappings, if any reference creator face/multi-slide)
4. Add header "## Scope" note clarifying this LUT is used during blog image prompt generation
5. Keep these sections (all applicable to blog images):
   - Emotion → Expression → Setup Mapping
   - Expression Keyword Phrases
   - Lighting Patterns (Rembrandt, Butterfly, Loop, Split, Rim)
   - Lighting Ratios (2:1, 4:1, 8:1, 16:1)
   - Color Temperature Specs (1900K → 12000K)
   - Cinematographer Signatures (Deakins, Fraser, Lubezki, Van Hoytema, Young)
   - Camera & Lens Specifications (ECU → EWS + angles)
   - Film Stock Guide (Portra 400, Vision3, Ektar, Tri-X, CineStill, Velvia)
   - Color Grading Styles (Teal & Orange, Bleach Bypass, Golden Hour, etc.)
   - Atmosphere Elements (haze, fog, smoke, dust, bokeh, god rays)
   - Quick Combos (content type → complete setup)
6. Save

**Verification:**
- [ ] File exists at `article-content-writer/references/cinematography-lut.md`
- [ ] No references to "carousel", "slide", "hook slide", "swipe" in content
- [ ] Contains all 8 section headers from source (Emotion/Lighting/Color/Cinematographer/Camera/FilmStock/Grading/Atmosphere)
- [ ] Line count between 180-220 (allow some trim)

---

### Phase 2: Enhance image-prompt-guide.md with cinematic standard

**Estimated time:** 15 minutes

**Files:**
- Read: `D:\Projects\claude-plugin\ai-image-carousel-prompt-gen\references\prompt-formulas.md` (source sections)
- Modify: `D:\Projects\claude-plugin\article-content-writer\references\image-prompt-guide.md`

**Steps:**

**2A. Add "Cinematic Prompt Standard" section** (after existing "Image Prompt Output Format" section)

Content to add:
```markdown
## Cinematic Prompt Standard (MANDATORY)

Every inline image prompt MUST follow this standard. Generic 50-word prompts
are REJECTED. Target length: 300-500 words per prompt.

### The 8-Element WOW Framework

Every prompt MUST include all 8 elements. Count determines quality tier:
- 8/8 = EXCELLENT (cinematic editorial quality)
- 6-7/8 = PASS (acceptable)
- <6/8 = FAIL (rewrite prompt)

| # | Element | Requirement | Example |
|---|---------|-------------|---------|
| 1 | Lighting Drama | Pattern name + ratio + Kelvin | "Rembrandt key at 4:1 ratio from camera-left at 3200K" |
| 2 | Depth Layers | foreground + midground + background (labeled) | "foreground: dust particles...; midground: subject...; background: bokeh lights" |
| 3 | Atmosphere | Volumetric + particles + haze/fog/bokeh | "heavy volumetric haze catching side light, dust floating" |
| 4 | Color Contrast | Warm-cool tension or accent colors | "warm 3200K key vs cool 5600K rim creating tension" |
| 5 | Emotional Peak | Specific expression or scene emotion | "eyes narrowed in concentration, jaw set with determination" |
| 6 | Camera Intention | Shot type + lens + aperture + angle + DOF | "medium shot, 50mm f/2.0, low angle 15°, shallow DOF" |
| 7 | Texture Realism | Material-specific (skin/fabric/metal/wood) | "skin with visible pores, fabric weave, metal patina" |
| 8 | Cinematic Reference | Film stock + color grade + DP signature | "Kodak Portra 400, warm golden amber grade, inspired by Roger Deakins" |

### 5-Paragraph Structure (REQUIRED)

Every prompt body must be organized into exactly 5 paragraphs:

**Paragraph 1 — Subject + Expression + Wardrobe**
Shot type, who/what is in the image, facial expression (if person), body pose,
clothing/wardrobe details.

**Paragraph 2 — Depth Layers**
Explicitly label foreground, midground, background. Each layer 1-2 sentences
describing what's in it.

**Paragraph 3 — Lens + Lighting Setup**
Camera specs (lens, aperture, angle, DOF). Lighting setup (pattern name, ratio,
Kelvin temperatures for key/fill/rim).

**Paragraph 4 — Film Stock + Color Grade + Atmosphere + Texture + Cinematographer**
Film stock (e.g., Kodak Portra 400). Color grade (e.g., warm golden amber).
Atmosphere elements (volumetric, particles, haze). Material textures (skin,
fabric, metal). Cinematography inspired by [DP name].

**Paragraph 5 — Aspect Ratio + Negative Constraints**
Aspect ratio. Negative constraints: "no text visible", "no logos",
"no competitor branding".

### Formatting Rules

- NO ALL CAPS instructions in the prompt body
- NO raw percentages (use "thirty percent" not "30%")
- NO filenames in body (reference images go in separate `insert_after_heading` or ref fields)
- NO "Shot on [camera]" prefix — use "lens:" format instead
- NO category/emotion tags in body — those are metadata
- Use lowercase for most specifications (helps image model focus on structure)

### Cross-Reference to LUT

For specific values (lighting patterns, Kelvin temps, cinematographer names,
film stocks, shot types), consult `cinematography-lut.md` which is also in
the system prompt.
```

**2B. Add "Cinematic Prompt Example Library" section** (after the standard section)

Content to add:
```markdown
## Cinematic Prompt Example Library

### Example 1: Cover Image (Hero Shot)
```
A photorealistic cinematic wide shot of an Indonesian-appearing
professional developer in his mid-30s working intently at a minimalist
desk, face illuminated by the warm glow of a laptop screen, eyes focused
with quiet determination, slight smile suggesting discovery, wearing a
charcoal crew-neck sweater with subtle texture visible, hands poised
above a mechanical keyboard mid-thought.

foreground: out-of-focus edge of a notebook with handwritten notes
catching warm amber light, coffee mug with steam rising in soft bokeh.
midground: the developer centered in frame, laptop screen reflecting
subtle cool light on his face from below while warm desk lamp provides
key light from upper-right. background: large floor-to-ceiling window
showing Jakarta city skyline at golden hour, warm amber buildings and
sky bokeh creating depth, subtle silhouette of potted plants framing
the scene.

lens: 50mm f/1.8, eye-level slight high angle, shallow depth of field
with developer's face in sharp focus. Rembrandt key light at 3:1 ratio
from upper-right desk lamp at 3200K warm tungsten, cool 5600K fill from
the laptop screen creating subtle warm-cool tension, soft rim light from
window catching hair edges.

Kodak Portra 400, warm golden amber grade. subtle atmospheric haze from
warm evening air, dust particles visible in the desk lamp beam, natural
bokeh from window lights. natural skin texture with visible pores and
faint beard stubble, sweater fiber weave with soft warmth, mechanical
keyboard key caps showing subtle wear and finger oils. cinematography
inspired by Bradford Young.

16:9 aspect ratio. no text visible. no logos on laptop or screen.
```
**WOW Score: 8/8** — all elements present.

### Example 2: Inline Scene (People Interaction)
```
A photorealistic cinematic medium shot of two Indonesian business
professionals in modern office attire standing side-by-side before a
large holographic display, the woman on the left gesturing toward a
sharp upward trajectory curve with her index finger, eyes focused with
analytical intensity, slight confident smile. the man on the right with
arms crossed thoughtfully, eyebrows raised in measured surprise,
nodding slightly. both wearing tailored charcoal suits with subtle
sheen catching screen glow.

foreground: blurred edge of a glass conference table with a coffee cup
and tablet catching warm reflections. midground: both professionals
centered in frame, the holographic display between them projecting
sharp upward chart line in warm golden amber with subtle data points,
their figures illuminated by the screen glow. background: clean glass
and steel office interior with diffused daylight, city skyline visible
through floor-to-ceiling windows, out-of-focus colleagues at distant
workstations.

lens: 35mm f/2.8, eye-level, medium depth of field keeping both
subjects in focus. soft butterfly lighting pattern from overhead
architectural fixtures at 3:1 ratio at 4000K neutral daylight, warm
3200K accent from the holographic display glow on their faces, cool
5600K window light from camera-left providing rim separation.

Kodak Portra 400, balanced warm-neutral grade. minimal haze, clean air
atmosphere with subtle light rays from the windows, gentle bokeh of
city lights outside. natural skin texture with visible pores, suit
fabric showing fine weave pattern and subtle sheen, glass table edge
catching light with crystalline clarity, holographic display edge
showing subtle transparency. cinematography inspired by Emmanuel
Lubezki.

16:9 aspect ratio. no text on holographic screen. no visible corporate
logos.
```
**WOW Score: 8/8**.

### Example 3: Data Visualization (Abstract Concept)
```
A photorealistic cinematic close-up of an abstract digital data
visualization emerging from a dark surface, intricate network of
glowing connection nodes pulsing with soft warm amber light, data
streams flowing between nodes like rivers of liquid light, geometric
precision meets organic flow, sense of intelligence awakening.

foreground: scattered floating holographic data fragments in sharp
focus, numeric micro-text too small to read but suggesting complexity,
subtle bokeh of distant data points. midground: the main network
hub with three concentric rings of nodes rotating slightly, central
core pulsing with brighter warm light, data streams radiating outward.
background: infinite dark void with distant pinpoints of light
suggesting depth, subtle volumetric glow at the horizon creating a
sense of vast computational space.

lens: 100mm macro f/2.8, slight low angle 10° up toward the network
hub, extremely shallow depth of field with the central core in sharp
focus. key rim light from behind the network at 4:1 ratio at 3200K
warm amber creating silhouette glow, soft fill from below at 3500K
warm tungsten, cool 6000K accent lights scattered throughout creating
warm-cool tension.

Kodak Portra 400, warm golden amber grade with deep rich blacks.
heavy volumetric atmosphere with glowing data particles floating
throughout, subtle haze catching the warm rim light, streams of light
creating god rays through the network. glossy reflective surfaces on
the data nodes showing subtle imperfections and micro-scratches, dark
void surface with subtle texture variations catching incidental light.
cinematography inspired by Greig Fraser.

4:3 aspect ratio. no text visible. no logos. abstract conceptual
imagery.
```
**WOW Score: 8/8**.
```

**Verification:**
- [ ] `image-prompt-guide.md` contains new "Cinematic Prompt Standard" section
- [ ] Contains 8-element table with all 8 rows
- [ ] Contains "5-Paragraph Structure" with paragraph 1-5 labeled
- [ ] Contains "Formatting Rules" with at least 5 bullet points
- [ ] Contains "Cinematic Prompt Example Library" with 3 full example prompts
- [ ] Each example labeled with **WOW Score: 8/8**
- [ ] Each example demonstrates 5-paragraph structure clearly

---

### Phase 3: Update article-write/SKILL.md to enforce cinematic standard

**Estimated time:** 5 minutes

**Files:**
- Modify: `D:\Projects\claude-plugin\article-content-writer\skills\article-write\SKILL.md`

**Steps:**
1. Locate the "Completion — Save Article Data" section (around line 175-180)
2. Find the "Image distribution rule" block added earlier
3. Add right after it, a new "Cinematic prompt quality rule" block:

```markdown
**Cinematic prompt quality rule:** Every `image_prompts[].prompt` field
MUST follow the 8-element WOW framework and 5-paragraph structure defined
in `image-prompt-guide.md` and `cinematography-lut.md` (both in your
system prompt). Target length: 300-500 words per prompt. Generic prompts
under 200 words are REJECTED. Include:
- Camera specs (lens, aperture, angle, DOF)
- Lighting pattern name + ratio + Kelvin temperatures
- 3 depth layers (foreground / midground / background)
- Film stock (default: Kodak Portra 400) + color grade
- Atmosphere (volumetric, particles, haze)
- Material textures (specific, not generic)
- Cinematographer inspiration (from LUT)
```

4. Update the JSON payload example's `prompt` field comment from `"20-80 word image prompt"` to `"300-500 word cinematic prompt (see image-prompt-guide.md + cinematography-lut.md)"`

**Verification:**
- [ ] `article-write/SKILL.md` contains "Cinematic prompt quality rule" block
- [ ] JSON payload comment updated to "300-500 word cinematic prompt"
- [ ] Cross-reference to `cinematography-lut.md` present

---

### Phase 4: Update article-gen/SKILL.md + article-writer.md agent

**Estimated time:** 5 minutes

**Files:**
- Modify: `D:\Projects\claude-plugin\article-content-writer\skills\article-gen\SKILL.md`
- Modify: `D:\Projects\claude-plugin\article-content-writer\agents\article-writer.md`

**Steps:**

**4A. article-gen/SKILL.md:**
1. Locate the "Step 4 — WRITE + POLISH" section, "Section-bound image analysis" block
2. Add a bullet before the existing "Goal" line:
   ```
   - Prompt length: 300-500 words following 8-element WOW framework and 5-paragraph structure (see image-prompt-guide.md + cinematography-lut.md)
   ```
3. Find the JSON output format (around line 190-200)
4. Update `"prompt": "{full_prompt}"` comment to: `"prompt": "{300-500 word cinematic prompt}"`

**4B. article-writer.md:**
1. Locate Step 4B "Generate Section-Bound Image Prompts"
2. Add at the top of the decision criteria list:
   ```
   **Prompt quality:** Every image prompt MUST be 300-500 words following
   the 8-element WOW framework and 5-paragraph structure from
   image-prompt-guide.md + cinematography-lut.md.
   ```

**Verification:**
- [ ] `article-gen/SKILL.md` mentions "300-500 words" and cross-references new LUT file
- [ ] `article-writer.md` Step 4B mentions cinematic standard requirement
- [ ] Both files reference `cinematography-lut.md` explicitly

---

### Phase 5: Update compile-references.sh to include new LUT in refs-write.md

**Estimated time:** 3 minutes

**Files:**
- Modify: `D:\Projects\claude-plugin\article-content-writer\scripts\compile-references.sh`

**Steps:**
1. Open script
2. Locate the `refs-write.md` section (around line 91-121)
3. Add after the existing `append_ref "$WRITE" "$REFS_DIR/retention-engine.md"` line:
   ```bash
   append_ref "$WRITE" "$REFS_DIR/cinematography-lut.md"
   ```
4. DO NOT add to `refs-prep.md` (not needed during research/outline)
5. DO NOT add to `refs-score.md` (not needed during scoring)

**Verification:**
- [ ] Script has `append_ref "$WRITE" ".../cinematography-lut.md"` line
- [ ] Other compiled refs (prep, score) unchanged

---

### Phase 6: Recompile reference bundles + verify sizes

**Estimated time:** 2 minutes

**Files:**
- Run: `bash D:\Projects\claude-plugin\article-content-writer\scripts\compile-references.sh`
- Verify: `D:\Projects\claude-plugin\article-content-writer\references\compiled\refs-write.md`

**Steps:**
1. Run compile script
2. Note output sizes (expect refs-write.md to grow ~10-15KB)
3. Grep the compiled `refs-write.md` to verify LUT content present:
   ```bash
   grep -c "Cinematographer Signatures\|Lighting Patterns\|Kodak Portra" refs-write.md
   ```
   Expect at least 3 matches.

**Verification:**
- [ ] Script exits 0
- [ ] refs-write.md size between 60KB-75KB (grew from 49KB)
- [ ] refs-write.md contains "Cinematography", "Rembrandt", "Kodak Portra" strings
- [ ] refs-prep.md and refs-score.md sizes unchanged

---

### Phase 7: Commit + push plugin changes

**Estimated time:** 2 minutes

**Files:**
- Commit: all modified files in `D:\Projects\claude-plugin\article-content-writer\`

**Steps:**
1. `cd D:\Projects\claude-plugin\article-content-writer`
2. `git add references/cinematography-lut.md references/image-prompt-guide.md skills/article-write/SKILL.md skills/article-gen/SKILL.md agents/article-writer.md scripts/compile-references.sh references/compiled/`
3. Commit with message:
   ```
   feat: cinematic image prompt standard (8-element WOW framework)
   
   Port cinematic quality standards from carousel plugin:
   - NEW references/cinematography-lut.md (lighting, film stock,
     cinematographers, shot types, atmosphere)
   - ENHANCED image-prompt-guide.md with 8-element WOW framework,
     5-paragraph structure, 3 example prompts
   - ENFORCED 300-500 word prompts in article-write, article-gen,
     article-writer agent
   - Compile script includes new LUT in refs-write.md bundle
   ```
4. `git push origin main`

**Verification:**
- [ ] Commit created with all 6 files (new LUT + 4 edited + compile script + compiled bundles)
- [ ] Push successful
- [ ] GitHub raw URL serves new cinematography-lut.md

---

### Phase 8: Deploy plugin files to VPS

**Estimated time:** 3 minutes

**Files:**
- Deploy to: `/home/claudesn/.claude/plugins/cache/alisadikinma-ai-content-suite/article-content-writer/{2.3.0,2.0.0}/`

**Steps:**
1. Use SSH MCP to run deployment script on VPS:
   ```bash
   for v in 2.3.0 2.0.0; do
     BASE="/home/claudesn/.claude/plugins/cache/alisadikinma-ai-content-suite/article-content-writer/$v"
     [ -d "$BASE" ] || continue
     for f in "references/cinematography-lut.md" "references/image-prompt-guide.md" "skills/article-write/SKILL.md" "skills/article-gen/SKILL.md" "agents/article-writer.md" "scripts/compile-references.sh" "references/compiled/refs-prep.md" "references/compiled/refs-write.md" "references/compiled/refs-score.md"; do
       curl -sL "https://raw.githubusercontent.com/alisadikinma/article-content-writer/main/$f" -o "$BASE/$f"
     done
   done
   ```
2. Verify on VPS: check that `references/cinematography-lut.md` exists in both versions
3. Verify compiled bundle updated: check file size of `references/compiled/refs-write.md`

**Verification:**
- [ ] Both plugin versions (2.3.0 + 2.0.0) have new cinematography-lut.md
- [ ] refs-write.md on VPS matches local compiled size (~60-75KB)
- [ ] grep "Kodak Portra" on VPS refs-write.md returns matches

---

### Phase 9: Regression test with existing article

**Estimated time:** 5 minutes (manual)

**Steps:**
1. On Content Engine admin, regenerate an existing article (e.g., idea #4 or new one)
2. Wait for generation to complete (~6-8 min)
3. Open article preview → check `image_prompts[]`
4. Verify each prompt:
   - Length ~300-500 words (not ~50)
   - Contains "lens:" format
   - Contains lighting pattern name (Rembrandt/Butterfly/etc.)
   - Contains Kelvin temp (3200K/5600K/etc.)
   - Contains 3 depth layers (foreground/midground/background)
   - Contains "Kodak Portra 400" or similar film stock
   - Contains cinematographer name (Deakins/Fraser/etc.)
   - Mentions `insert_after_heading` with actual H2 text
5. Generate one image via GeminiGen → compare visual quality vs prior generation

**Verification:**
- [ ] All generated prompts meet length requirement
- [ ] All 8 WOW elements present in sample of 3 prompts
- [ ] Visual quality noticeably improved vs baseline

---

## File Change Summary

| Phase | File | Action | Location |
|-------|------|--------|----------|
| 1 | `cinematography-lut.md` | CREATE | `article-content-writer/references/` |
| 2 | `image-prompt-guide.md` | MODIFY (add sections) | `article-content-writer/references/` |
| 3 | `article-write/SKILL.md` | MODIFY | `article-content-writer/skills/article-write/` |
| 4 | `article-gen/SKILL.md` | MODIFY | `article-content-writer/skills/article-gen/` |
| 4 | `article-writer.md` | MODIFY | `article-content-writer/agents/` |
| 5 | `compile-references.sh` | MODIFY | `article-content-writer/scripts/` |
| 6 | `refs-write.md` (compiled) | RECOMPILE | `article-content-writer/references/compiled/` |
| 7 | All above | COMMIT + PUSH | git remote |
| 8 | All above | DEPLOY | VPS plugin cache dirs |
| 9 | N/A | REGRESSION TEST | Admin panel |

## Dependencies

- Requires access to carousel plugin at `D:\Projects\claude-plugin\ai-image-carousel-prompt-gen\`
- Requires SSH access to VPS (already established)
- Requires GeminiGen API to be operational for Phase 9 test

## Estimated Total Time

- Implementation (Phases 1-7): ~45 minutes
- Deployment (Phase 8): ~3 minutes
- Regression test (Phase 9): ~5-10 minutes
- **Total: ~55-60 minutes**

# Hero Video — 3 Keyframe Prompts (Optimized for Seedance 2.0)

**Project:** alisadikinma.com homepage hero video — Genesis Triptych
**Phase:** 4 (simplified — 3 anchor frames, no asset library)
**Total keyframes:** 3
**Output folder:** `D:\Projects\Portfolio_v2\docs\plans\hero-video\keyframes\`
**Model:** Nano Banana 2 (Gemini 3.1 Flash Image) via [GeminiGen.AI](https://geminigen.ai)
**Aspect ratio:** 16:9 (2048×1152 final, generate at 4096×2304)
**Target:** Seedance 2.0 image-to-video Method 2 (Full Reference Mode) for 15s seamless loop

---

## Why 3 Keyframes

Seedance 2.0 can render 15s in a single generation. 3 anchor frames give Seedance enough guidance to:
1. Lock the **triptych structure** (the "what does this person do" visual answer)
2. Lock the **3D parallax orbit** (the "wow" cinematographic moment)
3. Lock the **text legibility** at end (the "AI GENERALIST" wordmark + Outskill kicker — text-critical)

Seedance interpolates between these anchors:
- Frame 0-4s: dark void → particles converge → triptych emerges (interpolated from KF-01)
- Frame 4-8s: KF-01 → KF-02 (orbit motion 0° → 30°)
- Frame 8-12s: KF-02 → continued orbit + columns begin collapsing
- Frame 12-14s: column collapse → wordmark resolves → kicker text appears (interpolated to KF-03)
- Frame 14-15s: KF-03 → fade to black (loop reset)

---

## Keyframe Map

| # | Filename | Beat | Time | Role |
|---|---|---|---|---|
| 01 | `keyframe-01-triptych-split.png` | Beats 1-2 | t≈4s | Anchor for "the reveal" — 3 columns formed, front view |
| 02 | `keyframe-02-orbit-mid.png` | Beats 4-6 | t≈8s | Anchor for "the wow" — 30° offset showing 3D depth |
| 03 | `keyframe-03-founder-reveal.png` | Beats 7a+7b | t≈14s | **Anchor for "the founder reveal"** — Ali portrait + wordmark above + kicker below. Personal branding payoff. |

Sequential dependency: KF-02 uses KF-01 as continuity ref. KF-03 uses KF-02 as continuity ref + `alisadikin_profile_photo.png` as face identity lock.

---

## KEYFRAME 1 — Triptych Split (Front View)

**Output →** `keyframes/keyframe-01-triptych-split.png`

**Upload:** None (this is the start anchor — no upstream keyframe).

> **REWRITE NOTE 2026-05-04:** Original prompt produced literal rectangular container boxes with readable code text + visible video player thumbnails + constellation graphs — defeats abstract intent. New prompt strips ALL literal content. Pure light volumes only. Reference: aurora borealis sheets, Hubble nebula pillars, 2001 monolith reveal.

```
[16:9 ASPECT RATIO — 2048×1152 — landscape orientation]

A premium cinematic dark cinema scene rendered like a single still frame from a $200M sci-fi film. Three vertical pillars of pure flowing luminous energy suspended in an infinite matte black void (#050506). The pillars are NOT physical objects, NOT containers, NOT boxes — they are pure light phenomena with soft glowing volumetric edges that fade gradually into the surrounding darkness. No hard borders, no frames, no outlines, no enclosing geometry. Reference quality: aurora borealis curtains lit from within, nebula columns from a Hubble Space Telescope deep-field photograph, the iconic monolith reveal from 2001: A Space Odyssey but luminous and triple, the sandworm-summoning pillar shots from Dune Part One.

LEFT PILLAR positioned at horizontal frame center 25%, vertical centerline 50%, height approximately 70-75% of frame, width approximately 10-12% of frame: a soft glowing vertical column of electric cinematic cyan light (#06B6D4). Internal motion is gentle abstract energy flow — like cyan smoke rising through a beam of blacklight, or aurora curtains drifting slowly. No discernible internal content, no recognizable shapes, no patterns, no symbols, no UI, no characters, no readable elements of any kind. Pure flowing dynamic light only. Soft outer glow extends 5% of column width into surrounding void.

CENTER PILLAR positioned at horizontal frame center 50% — CRITICAL COMPOSITIONAL REQUIREMENT: this pillar MUST be 115% scale of the side pillars (visibly taller and wider) AND positioned 10% closer to camera in 3D space (subtle parallax — center pillar is slightly forward, creating obvious asymmetric depth). Brighter than the side pillars at approximately 130% luminance — dominant in the composition. Glowing cinematic warm gold light (#D4A843). Internal motion is gentle abstract energy flow with a subtle slow breath-like luminance variance (not strobing, not pulsing rapidly — a calm rhythmic glow). No discernible internal content of any kind. Pure flowing dynamic light. Soft outer glow extends 7% of column width.

RIGHT PILLAR positioned at horizontal frame center 75%, mirror character of LEFT pillar: a soft glowing vertical column of electric cinematic cyan light (#06B6D4), same luminance as LEFT, same internal abstract flow character. No discernible content of any kind. Pure flowing dynamic light.

Background: infinite cinematic dark void — deep matte black #050506 with imperceptible volumetric atmospheric haze at 3% opacity. No horizon line, no floor, no grid, no ground reflection, no architectural elements, no stage, no surfaces of any kind. Faint cinematic warm gold particle haze in the central mid-frame area between pillars at 8% opacity — extremely subtle, suggests the trace of an event that just happened.

Camera: wide shot, anamorphic 35mm equivalent lens, eye level, FRONT-ON view (0° offset). Three pillars within central 60% horizontal frame width in aggregate. Top 20% and bottom 20% of frame is pure void (pillars do NOT touch top or bottom edges). Slight depth of field — pillars sharp at their luminous cores, frame edges dreamily soft.

Style: premium cinematic Dark Cinema reference quality — Apple keynote intro, Linear product launch reveal, 2001: A Space Odyssey monolith reveal, Dune Part One sandworm tease, IMAX title card cinematography. Kodak Vision3 500T film grain at 12% intensity. Color temperature 3200K warm tungsten base. Custom Teal & Orange variant grade: deep teal void shadows, dual cinematic highlights (gold dominant + cyan accent — strict color separation, NO bleed between cyan and gold zones). Lighting ratio 4:1 — pillars are SELF-ILLUMINATING (they ARE the light source). Mood: anticipatory revelation, restrained agency-tier mastery, quiet authority before the reveal.

CRITICAL NEGATIVES — these MUST be avoided as the previous render failed on these points:

DO NOT make pillars look like rectangular boxes, glass containers, aquariums, display cases, or any kind of enclosing geometry. Pillars are PURE LIGHT VOLUMES — no hard edges, no borders, no frames, no outlines, no walls, no surfaces, no edges of any kind. Soft glowing fading edges only.

DO NOT add any internal visible content within the pillars: NO code text, NO readable lines or characters, NO numbers, NO words, NO icons, NO UI elements, NO video frames, NO video player thumbnails, NO charts, NO graphs, NO network diagrams, NO constellation patterns, NO connecting nodes, NO data visualizations, NO documents, NO logos. The pillars contain ONLY abstract flowing light energy — like luminous fog or aurora light, with no recognizable internal structure.

DO NOT add labels, captions, titles, or any typography in this frame.

DO NOT make the three pillars equal in size — the center MUST be visibly LARGER (115%) and visibly CLOSER to camera (10% forward parallax). The asymmetry must be obvious to the viewer.

DO NOT add purple, magenta, pink, lavender, or any non-gold-non-cyan hue anywhere.

DO NOT add lens flare, starburst, sparkle effects, fairy dust, Christmas lights, disco ball reflections, or any decorative light artifacts.

DO NOT make pillars look like 3D Cinema 4D rendered solid objects with chrome, metallic, glass, or refractive shaders. Pillars are LUMINOUS GAS, not solid material.

DO NOT add a horizon, floor, grid, stage, ground, surface, or any environmental element.

DO NOT let cyan and gold light colors bleed into each other — strict color zone separation maintained.

DO NOT show pillar tops or bottoms touching the frame edges.

TECHNICAL: 16:9 aspect ratio (2048×1152 final, generate at 4096×2304). NB2 CFG 5.5 (lowered from 6.5 — softer, more interpretive, less prescriptive output), denoise 0.40, thinking mode High, JPEG quality 92. Color space sRGB. Output PNG lossless.

[16:9 ASPECT RATIO LOCKED — soft glowing pillars of pure light only — no containers, no internal content, no hard edges]
```

---

## KEYFRAME 2 — Orbit at 30° Offset (Mid-Rotation Anchor)

**Output →** `keyframes/keyframe-02-orbit-mid.png`

**Upload:**
| Filename | Purpose |
|---|---|
| `keyframe-01-triptych-split.png` | Continuity anchor — palette, pillar character, void atmosphere, composition (size + parallax) |

> **REWRITE NOTE 2026-05-04:** Same fix as KF-01 — strip all literal content (NO code, nodes, video frames). Pure light pillars at 30° camera angle.

```
[16:9 ASPECT RATIO — 2048×1152 — landscape orientation]

Continuation from keyframe-01-triptych-split.png — the SAME three vertical pillars of pure flowing luminous energy from the previous frame are now viewed from a camera position rotated 30° clockwise around the central pillar (camera moved to roughly 4 o'clock if pillars sit at 12 o'clock looking south). Parallax is now clearly visible — the gold center pillar appears most foreground due to the angle, the right cyan pillar appears more distant, the left cyan pillar appears slightly forward. 3D depth between pillars is now apparent via the angular shift.

The same THREE LUMINOUS ENERGY PHENOMENA from keyframe-01 — vertical pillars of pure light, NO physical containers, NO hard edges, NO borders, NO frames, NO outlines, NO enclosing geometry. Soft glowing volumetric forms that fade gradually into the surrounding darkness. Reference quality: aurora borealis curtains, Hubble nebula columns, the 2001 monolith but luminous and triple.

CENTER GOLD PILLAR is at a slight luminance peak at this orbital pivot moment — its internal flowing energy intensifies briefly (luminance momentarily rises 15% above keyframe-01 baseline). The internal flow has a subtle slow breath-like rhythmic glow (not strobing). NO discernible internal content of any kind — no code, no nodes, no symbols, no UI, no readable elements. Pure flowing gold light only. Camera focus is on this center pillar — sharpness highest, side pillars rendered slightly dreamier via depth-of-field.

LEFT CYAN PILLAR — in foreground due to 30° camera angle but dimmed to approximately 70% relative luminance (de-emphasized to keep visual focus on the center). Pure flowing cyan light, same character as keyframe-01. NO discernible internal content of any kind.

RIGHT CYAN PILLAR — in slight background due to 30° camera angle, dimmed to approximately 70% luminance. Pure flowing cyan light, same character as keyframe-01. NO discernible internal content.

Background: infinite cinematic dark void from previous frame — deep matte black #050506, 3% atmospheric haze. Subtle 1-2px light streak ghosting on the furthest pillar edges suggests the dolly motion that brought us to this 30° pivot (NOT motion blur on the pillars themselves — just a faint trace of the orbital path). Faint cinematic warm gold particle haze in central mid-frame between pillars at 8% opacity, slightly redistributed due to angle change.

Camera: wide shot, anamorphic 35mm equivalent, eye level, 30° offset right from front view. Slight wide-angle perspective. Center pillar dominates central 50% of frame width. Left pillar at approximately 15% horizontal frame, right pillar at approximately 80% horizontal frame (compositional asymmetry due to camera angle).

Style: continuation of keyframe-01's Dark Cinema language — Kodak Vision3 500T film grain 12% intensity, 3200K base color temperature, custom Teal & Orange variant grade, color separation maintained (NO cyan/gold bleed). Lighting ratio 4:1, self-illuminating pillars. Mood: dynamic revelation, mid-orbit cinematic agency feel — the orbit camera move around the helmet in The Mandalorian title sequence applied to abstract light pillars.

CRITICAL NEGATIVES (preserved from keyframe-01 + camera-specific):

DO NOT make pillars look like rectangular boxes, glass containers, aquariums, display cases, or any enclosing geometry. Pure light volumes only — no hard edges, no borders, no frames, no outlines.

DO NOT add internal visible content: NO code text, NO readable lines, NO numbers, NO words, NO icons, NO UI elements, NO video frames, NO charts, NO network diagrams, NO connecting nodes, NO logos.

DO NOT add labels, captions, or any typography.

DO NOT relocate pillars relative to each other — preserve composition from keyframe-01, only rotate the camera viewpoint. Same 115% center pillar scale + 10% forward parallax MUST be maintained.

DO NOT make camera angle so extreme that one pillar is hidden behind another (30° must show all 3 pillars with clear parallax).

DO NOT make all 3 pillars equal in size — preserve the center 115% asymmetry from keyframe-01.

DO NOT let center pillar outshine to the point side pillars become invisible (70% dimming on side pillars, NOT total blackout).

DO NOT add lens flare or starburst.

DO NOT add visible motion blur on the stationary pillars themselves (subtle 1-2px edge ghosting only suggests camera motion).

DO NOT change pillar colors (gold center, cyan sides — strict color separation maintained).

DO NOT add purple, magenta, pink, or any non-gold-non-cyan hue.

DO NOT make pillars look like 3D Cinema 4D rendered solid objects with chrome, metallic, glass, or refractive shaders.

DO NOT add a horizon, floor, grid, stage, or surface.

TECHNICAL: 16:9 aspect ratio (2048×1152 final, generate at 4096×2304). NB2 CFG 5.5 (softer interpretation matching keyframe-01), denoise 0.40, thinking mode High, JPEG quality 92. Color space sRGB. Output PNG lossless.

[16:9 ASPECT RATIO LOCKED — soft glowing pillars of pure light only — preserve asymmetry from keyframe-01]
```

---

## KEYFRAME 3 — Founder Reveal (Ali + Wordmark + Kicker)

**Output →** `keyframes/keyframe-03-founder-reveal.png`

**Upload:**
| Filename | Purpose |
|---|---|
| `keyframe-02-orbit-mid.png` | Continuity anchor — palette, void atmosphere, gold particle character |
| `alisadikinma_face.png` | **Face reference** — preserve exact facial identity. Source URL: https://alisadikinma.com/uploads/about/1761935897_alisadikin_profile_photo.png — operator downloads + uploads to NB2 |

```
[16:9 ASPECT RATIO — 2048×1152 — landscape orientation]

Continuation from keyframe-02-orbit-mid.png — the camera has completed its full circular orbit (returned to front-center 0° view), AND the 3 columns from the previous frames have collapsed inward, revealing the founder behind the AI Generalist work. This is the documentary-grade founder reveal — the moment audience meets the human who delivered the abstract mastery shown in previous keyframes.

COMPOSITION (3-zone vertical stack, all centered horizontally):

ZONE 1 — Top (vertical position 12-22% of frame, occupying ~22% of frame height): the wordmark "AI GENERALIST" rendered in Space Grotesk Bold (or close visual equivalent — clean modern geometric sans-serif with humanist warmth, NOT Inter, NOT Roboto, NOT Helvetica, NOT Arial). Two words on a single line. Letterforms have ~95% white core (#EDEDEF) with a 4-6px outer rim glow in cinematic warm gold (#D4A843). Slight 3D extrusion 8-12px depth toward camera giving subtle dimensional weight without becoming chrome metallic. No drop shadow. Wordmark occupies central 40% horizontal frame width — sharp, broadcast-legible. Functions as Ali's "name plate" / nameplate above the founder portrait.

ZONE 2 — Center (vertical position 25-78% of frame, occupying ~53% of frame height): a cinematic chest-up portrait of Ali Sadikin Ma (Maintain exact facial identity from reference image: alisadikin_profile_photo.png) — bald, glasses with rounded rectangular frames, Indonesian (Sundanese) ethnicity, age 40-45, looking directly forward into camera with neutral confident calm expression (slight authoritative warmth, NOT smiling, NOT stern — restrained agency-presenter neutrality). Wearing a dark navy professional suit jacket over a crisp white shirt (no tie, top button open). Body slightly turned 5° toward camera right (suggests engagement, not perfectly square). Portrait occupies central 30% horizontal frame width, vertically centered in this zone. Subject is sharp and well-lit; surrounding void is soft. Cinematic dark-cinema lighting on Ali: key light from camera-left at 4:1 ratio (warm gold 3200K rim from frame-right side suggests residual particle illumination — like he just walked through the dispersing triptych), fill light minimal, hair light absent (bald). Subtle warm gold rim glow on right shoulder/face edge from the dispersing gold particles. Skin tones natural, not over-saturated. Eyes catch light, slight gold glint.

ZONE 3 — Bottom (vertical position 80-90% of frame, occupying ~10% of frame height): the kicker text "1ST · OUTSKILL DEMO DAY 2026 · 26 STARTUPS · 16 COUNTRIES" rendered in JetBrains Mono Bold ALL CAPS (monospaced typewriter-style, NOT sans-serif). Solid cinematic warm gold (#D4A843) fill at 100% opacity. Generous letter tracking (+25, broadcast credit roll standard). Bullet separators (·) between segments give scannable rhythm. Single line horizontal, occupying ~55% horizontal frame width centered. Text height roughly 3% of frame height — small and supportive, NOT shouting. Letters sharp and broadcast-legible character-by-character.

BACKGROUND/ATMOSPHERE: dark cinematic void from previous frames — deep matte black #050506. Faint cinematic warm gold particles (#D4A843) in DISPERSAL motion — streaming OUTWARD from behind Ali toward camera and frame edges. Approximately 100-150 particles visible (subdued density, NOT obscuring the founder). Foreground particles (rushing toward camera in front of Ali) are tiny (3-5px) so they don't visually compete with the portrait. Mid-distance particles behind Ali are slightly larger (6-9px), creating depth. Particles are heaviest behind Ali's right shoulder (suggests the gold center column collapsed BEHIND him → onto/around him → outward toward viewer). Background void is moderately darkened (40% darker than keyframe-01) — read as fade-out moment approaching but NOT pure black yet.

Camera: wide shot, anamorphic 35mm equivalent, eye level, FRONT-ON view (0° — camera returned to original position after the full orbital rotation). Wordmark + Ali portrait + kicker text vertically stacked in central ~50% width of frame. Slight depth of field — Ali sharp, particle haze around him soft.

Style: premium cinematic founder reveal aesthetic. Reference quality: Christopher Nolan film closing credits with director portrait, Apple keynote "And one more thing" Tim Cook reveal, documentary-grade hero portrait (Errol Morris portraiture style). Kodak Vision3 500T film grain 12% intensity (typography crisp + skin natural). Color temperature 3200K warm tungsten base. Custom grade: deep teal void shadows, gold rim light highlights, natural skin tones (not orange-pushed, not teal-pushed). Lighting ratio 4:1 (cinematic high contrast — Ali pops against void). Mood: founder reveal payoff, quiet confident authority, "and this is who built it" — restrained victory whisper.

Do NOT show Ali smiling broadly (neutral confident expression only). Do NOT show Ali at extreme camera angle (front-on with 5° subtle turn only). Do NOT have Ali hold any object (no trophy, no laptop, no phone). Do NOT add other people or hands. Do NOT change Ali's appearance — preserve exact facial identity from alisadikin_profile_photo.png (bald, glasses, suit). Do NOT use Ali's reference photo as-is (apply cinematic dark cinema lighting + gold rim — the photo from upload is daylight studio, this frame is studio-cinematic). Do NOT make skin look orange/teal pushed (natural tones with subtle warm gold rim only). Do NOT add a background environment, building, or studio setting (pure void). Do NOT bring void to PURE black yet (40% darkening only — pure black reserved for frame 15.0 endpoint). Do NOT add cyan particles (only gold dispersing — cyan columns merged into gold final state). Do NOT crop "1ST · OUTSKILL DEMO DAY 2026 · 26 STARTUPS · 16 COUNTRIES" mid-word — full text MUST be readable. Do NOT use italic typography. Do NOT make wordmark chrome metallic. Do NOT use Inter, Roboto, Helvetica, or Arial. Do NOT add a logo (no Outskill logo image — credential text-only). Do NOT add captions like "Ali Sadikin" below his portrait (the wordmark "AI GENERALIST" above IS his nameplate — no redundant name caption).

TECHNICAL: 16:9 aspect ratio (2048×1152 final, generate at 4096×2304). NB2 CFG 6.5, denoise 0.35 (lower for crisp text + sharp face), thinking mode High, JPEG quality 92. Color space sRGB. Output PNG lossless.

[16:9 ASPECT RATIO LOCKED]
```

---

## ⚠️ Phase 5 Pipeline Note — Face Triggers Hybrid Render Path

Including Ali's face in KF-03 means Phase 5 (video render) will likely require **hybrid pipeline** because Seedance 2.0 has a hard ban on real-person face inputs (per `seedance_edge_cases §5`). KF-03 contains Ali's face, so uploading it as `@Image3` to Seedance may trigger the safety filter (0% success rate).

**3 paths Phase 5 can take — decision deferred until KF-03 is rendered and tested:**

### Path A — Pure Seedance (test first)
Upload KF-03 as @Image3 to Seedance anyway. **Test only.** If Seedance accepts (face is stylized enough via NB2's cinematic dark-cinema treatment), entire 15s renders in single Seedance generation. Best case scenario.

### Path B — Hybrid Seedance + VEO 3.1 (most likely)
- **Seedance** renders frames 0-12s using @Image1 (KF-01) + @Image2 (KF-02) — pure abstract beats
- **VEO 3.1** renders frames 12-15s using KF-03 as start frame — face beat allowed
- Compose in DaVinci Resolve (free) for final 15s
- Cost: $0.33 Seedance + ~$2-5 VEO + ~30 min compositing

### Path C — Pivot to Kling AI 2.5 (single platform alternative)
Kling 2.5 allows real face inputs. Single 15s render using all 3 keyframes. Trade-off: less specialized for abstract motion than Seedance, but very capable. Single-platform simplicity.

**Decision criteria** (evaluated at Phase 5):
1. If Seedance accepts KF-03 in test → Path A (best)
2. If rejected and motion quality difference matters → Path B (hybrid, premium)
3. If simplicity > absolute motion quality → Path C (Kling)

This decision happens at Phase 5 (`/video-gen`) — not now. For Phase 4, just generate all 3 keyframes via NB2 (which DOES accept Ali's face ref).

---

## Operator Workflow

```
1. Open https://geminigen.ai (or NB2 direct API)
2. Set defaults: thinking mode = High, JPEG quality = 92
3. Generate KEYFRAME 1 (no upload — fresh start):
   a. Paste KF-01 prompt
   b. Set CFG 6.5, denoise 0.40
   c. Generate
   d. Save to D:\Projects\Portfolio_v2\docs\plans\hero-video\keyframes\keyframe-01-triptych-split.png
   e. Visual review — composition, palette, no AI-slop
   f. If fail: regenerate (try CFG 5.5 if too "AI-look", 7.0 if washed out)
4. Generate KEYFRAME 2 (upload KF-01 as reference):
   a. Upload keyframe-01-triptych-split.png
   b. Paste KF-02 prompt
   c. Set CFG 6.5, denoise 0.40
   d. Generate, save as keyframe-02-orbit-mid.png
   e. Visual review — same triptych, now at 30° angle, parallax visible
5. Generate KEYFRAME 3 (upload KF-02 + Ali face ref — CRITICAL: 2 uploads):
   a. Download Ali's profile photo from https://alisadikinma.com/uploads/about/1761935897_alisadikin_profile_photo.png — save locally as `alisadikin_profile_photo.png`
   b. Upload BOTH `keyframe-02-orbit-mid.png` (continuity ref) AND `alisadikin_profile_photo.png` (face identity lock)
   c. Paste KF-03 prompt
   d. Set CFG 6.5, denoise 0.35 (lower for crisp text + sharp face)
   e. Generate, save as keyframe-03-founder-reveal.png
   f. **CRITICAL review (3 checks):**
      - Ali's face matches reference (bald, glasses, ethnic features preserved — NOT a generic AI-generated man)
      - Kicker text 100% legible: "1ST · OUTSKILL DEMO DAY 2026 · 26 STARTUPS · 16 COUNTRIES"
      - Wordmark "AI GENERALIST" sharp + positioned above Ali's portrait
   g. If face wrong: regenerate with CFG 5.5 + denoise 0.40 (softer, more faithful to face ref)
   h. If kicker text garbled: regenerate with CFG 7.0 + denoise 0.30 (sharper, less text morphing)
6. Approve all 3 keyframes
7. Trigger /video-gen Phase 5 → produces Seedance 2.0 prompt that animates between @Image1 (KF-01), @Image2 (KF-02), @Image3 (KF-03)
```

---

## Total Effort

- 3 keyframes × ~2 attempts × ~60s (NB2 thinking-High) = ~6 minutes wall-clock
- Operator review: ~3-5 min per keyframe = ~15 min total
- Cost: ~free / very low via GeminiGen
- Phase 5 Seedance render: ~$0.33 single 15s generation × ~5 iterations = ~$1.65

**Compare to abandoned 5-keyframe plan:** 22 renders → 3 renders. **~85% reduction.**

---

## Critical Quality Checks (per keyframe)

For each rendered keyframe, verify:

- [ ] Aspect ratio 16:9 maintained (no square or portrait crop)
- [ ] Palette strict: gold #D4A843 + cyan #06B6D4 + white #EDEDEF + void #050506 ONLY
- [ ] No purple, magenta, pink, neon
- [ ] No Inter, Roboto, Helvetica, or Arial typography (Space Grotesk + JetBrains Mono only — visible in KF-03)
- [ ] Particles look like cinematic light, NOT fairy dust or Christmas lights
- [ ] Mood reads as Apple keynote tier (restrained, premium)
- [ ] No AI-slop clichés: brain network, holographic globes, glowing CPU chips, robot hands, chrome metallic, neon sign
- [ ] Sequential narrative readable: 1=triptych formed, 2=orbiting at 30°, 3=wordmark+kicker visible
- [ ] **KF-03 ONLY: kicker text "1ST · OUTSKILL DEMO DAY 2026 · 26 STARTUPS · 16 COUNTRIES" 100% legible character-by-character** — this is the most failure-prone and most-important text in the entire video

---

## Future Variant Note (creator face)

None of these 3 keyframes use Ali's face — concept is fully abstract. For future hero variants needing face render, embed in prompt body:

```
Maintain exact facial identity from reference image: alisadikin_profile_photo.png
```

Upload `https://alisadikinma.com/uploads/about/1761935897_alisadikin_profile_photo.png` as ref. (NOT applicable to current 3 keyframes.)

---

## Next: Phase 5 — Seedance 2.0 Animation Prompt

After all 3 keyframes operator-approved, run `/video-gen` to produce:

- Single Seedance 2.0 prompt referencing @Image1 (KF-01), @Image2 (KF-02), @Image3 (KF-03)
- 50-word ceiling per Seedance prompt length constraint (per `seedance_edge_cases §5`)
- MASTER CINEMATIC DIRECTION TEMPLATE structure (per `seedance_motion_audio.md §6`)
- 15s seamless loop animation timing — single Seedance render (no chaining needed, within 15s ceiling)
- Output: `seedance-prompt.md`

Anchor mapping for Seedance:
| @-tag | Keyframe | Time anchor in 15s timeline |
|---|---|---|
| @Image1 | keyframe-01-triptych-split.png | t≈4s — first major visible state (Seedance interpolates 0-4s convergence) |
| @Image2 | keyframe-02-orbit-mid.png | t≈8s — orbital pivot (3D parallax established) |
| @Image3 | keyframe-03-wordmark-kicker.png | t≈14s — final readable state (Seedance interpolates 14-15s fade to black) |

# Strategic Brief — Hero Video (Genesis Triptych)

**Project:** alisadikinma.com homepage hero video
**Phase:** Phase 9 of [docs/plans/2026-05-04-homepage-redesign-plan.md](../2026-05-04-homepage-redesign-plan.md)
**Pipeline:** AI Video Promo Engine v2.1.0 (Phase 1 brainstorm output)
**Created:** 2026-05-04
**Status:** Phase 1 complete — ready for Phase 2 (`/video-script`)

---

## 1. Brand & Positioning

| Field | Value |
|---|---|
| `creator_name` | Ali Sadikin Ma |
| `creator_tagline` | AI Generalist Expert |
| `positioning` | "Tell me your business problem. I'll architect the AI that solves it." |
| `creator_bio` | 17 years digital transformation + Vibe Coding · AI Agents · Generative Video — full-stack AI solutioning for operators who need shipped, not pitched. |
| `recent_proof` | 1st Place — Outskill AI Generalist Fellowship Demo Day 2026 (beat 26 startups from 16 countries) |

## 2. Language Configuration

| Setting | Value |
|---|---|
| `narration_language` | English (no narration in concept B, but on-screen text in EN) |
| `prompt_language` | English (mandatory for Seedance/NB2) |
| `ui_text_language` | English (wordmark + kicker micro-text) |
| `audio` | NONE (autoplay muted hero loop) |

## 3. Cast

Single character — but **no human likeness needed** in this concept (abstract metaphorical, not narrative drama).

See [cast-profile.md](cast-profile.md) for minimal cast record (placeholder for future variants that may use human face).

| Slot | Role | Status |
|---|---|---|
| Pemeran Utama 1 | Ali Sadikin Ma | NOT VISIBLE in this video — abstract concept only |

**Why no face:**
- Seedance 2.0 has hard ban on real-person faces (`seedance_real_face: BANNED` per global-promo-config Section 2)
- Concept B is metaphorical (light/particles/columns), not human-narrative
- Eliminates uncanny-valley risk
- Future hero variant could include face via VEO 3.1 (which allows generated faces)

## 4. Product / Service

| Field | Value |
|---|---|
| `product_category` | AI consulting / solutioning service (B2B) |
| `core_value_prop` | One operator, three AI disciplines, one mission: turn "we should use AI" into running production systems |
| `target_audience` | SEA + US founders/operators considering hiring for AI work |
| `shame_layer` | "We can't figure out where to start with AI" / "Our AI experiments never ship" |
| `pride_layer` | Working with a proven AI Generalist who delivers, not just talks |
| `awareness_level` | Solution-Aware (B): tahu masalahnya, mempertimbangkan solusi AI, sedang membandingkan opsi |

## 5. Location & Setting Context

| Field | Value |
|---|---|
| `video_location` | N/A — abstract/metaphorical |
| `video_country` | Global (not location-specific) |
| `video_setting_type` | Abstract dark void / studio environment |
| `video_indoor_outdoor` | Indoor (3D abstract space) |

**Domain research opt-out justification:** This concept is intentionally placeless and ageless. Standard 6-query domain research applies to real-world settings (factory, hospital, port, etc.) where local accuracy matters. For abstract metaphorical content, equivalent rigor comes from **Seedance 2.0 RAG knowledge** already embedded in the plugin (`docs/seedance_2_new_rag/`):
- `seedance_core_specs.md` (architecture, references, API)
- `seedance_motion_audio.md` (motion vocabulary, CRAFT framework, MASTER CINEMATIC DIRECTION TEMPLATE)
- `seedance_edge_cases.md` (constraints, hard gates, 15s ceiling)
- `seedance_visual_library.md` (visual primitives — read in Phase 4)

## 6. Target Market

| Segment | Description |
|---|---|
| **Primary** | SEA AI-curious founders (Indonesia, Singapore, Malaysia) — operators considering hiring AI generalists, looking for proven track record + cultural fit |
| **Secondary** | US early-stage SaaS founders looking for AI-native operators with cross-border SEA reach |
| **Tertiary** | Recruiters' AI assistants scraping AI Generalist talent (compounding GEO benefit) |

## 7. Awareness Level

**Solution-Aware (B) → Product-Aware (D)** transition is the goal:
- Audience knows AI exists, knows they need it, comparing operators → after 15s should know Ali specifically and want to talk to him

## 7.5 Generation Mode Lock — IMAGE-TO-VIDEO (operator preference)

**LOCKED:** Image-to-video via Seedance @Image references — **NOT pure text-to-video**.

**Why:**
- User explicit preference for animasi-from-image vs prompt-from-scratch
- Seedance Method 2 (Full Omni-Reference) gives 90%+ deterministic output vs Method 1 (text/First-Last-Frame) which is more probabilistic
- Per [seedance_edge_cases §4](../../skills/video-image): "Mandatory for complex assets or recurring brand characters. This method utilizes the 'Omni-Ref' system to fuse features from multiple sources simultaneously, effectively separating the 'Identity Vector' from the 'Motion Vector.'"
- For our triptych concept, 5 style frames as @Image1-5 + 1 motion ref @Video1 = locked aesthetic + locked camera trajectory. Seedance only fills in-between motion.

**Pipeline path adjustment:**

| Original pipeline | Image-to-video pipeline (this project) |
|---|---|
| Phase 2: A/V Script (dialogue + voiceover) | **COMPRESSED** — no dialogue/audio in muted hero. Beat structure already in §13 of this brief. |
| Phase 3: Scene breakdown | Compressed inline — 2 Seedance shots × 9s = entire structure |
| Phase 3.5: Reference collection gate | **CRITICAL** — this is where all the work happens for image-to-video |
| Phase 4: NB2 asset library + scene keyframes | **CRITICAL** — generate 5 style keyframes via Nano Banana 2 |
| Phase 5: Seedance video prompts (image review gate) | **CRITICAL** — Seedance prompt that references @Image1-5 + @Video1, animates between keyframes |

**Effect on next-step:** skip `/video-script` (Phase 2) since brief already encodes all script needs for muted abstract video. Jump to `/video-image` (Phase 4) directly, with this brief as input.

## 8. Platform Selection

| Platform | Status | Notes |
|---|---|---|
| **Seedance 2.0** | ✅ PRIMARY | User explicitly requested for natural fluid motion. Plugin RAG fully supports (`docs/seedance_2_new_rag/`). |
| Kling AI 2.5 | Optional A/B | Cross-render same prompt for comparison. Decision deferred to Phase 5. |
| VEO 3.1 | NOT NEEDED | No human face acting in this concept. Reserve for future hero variants with face. |
| HeyGen / Runway | NO | Out of plugin scope. |

**Seedance 2.0 production parameters:**

| Parameter | Value | Source |
|---|---|---|
| `seedance_resolution` | 2K native (2048×1080 landscape, 1080×2048 portrait) | seedance_core_specs §4 |
| `seedance_aspect_ratio` | 16:9 desktop primary + 9:16 mobile portrait variant | seedance_core_specs §4 |
| `seedance_fps` | 24fps (cinematic) | global-promo-config Section 2 |
| `seedance_clip_duration` | 15s single render | seedance_edge_cases §5 (exactly at ceiling — no chaining needed) |
| `seedance_total_duration` | 15s seamless loop | locked spec |
| `seedance_max_refs` | 12 (9 images + 3 videos + 3 audio) | seedance_edge_cases §5 |
| `seedance_real_face` | BANNED | seedance_edge_cases §5 (no face needed for our concept) |
| `seedance_audio` | OFF (no audio for hero loop) | autoplay muted requirement |
| `seedance_negative_prompt` | Not supported — embed "do not alter" inline | seedance_edge_cases §5 |

## 9. Tone / Mood

| Field | Value |
|---|---|
| `video_tone` | C (Professional / Corporate) — but specifically "**premium agency-tier cinematic**" |
| Mood reference | Apple keynote intro · Linear product reveal · Tesla Cybertruck unveil — restrained powerful |
| Emotional core | Premium confidence + technical mastery + subtle victory |
| Audience feels | "This person clearly knows AI deeply, makes me want to talk to them about my problem" |
| Audience does NOT feel | Salesy, hyped, vlog-y, "look at me" arrogant, generic AI-tool demo |

**Tone Impact Matrix consequences (per global-promo-config Section 13):**

| Phase | Adjustment |
|---|---|
| Script (Phase 2) | Minimal/no dialogue. On-screen text only. Restrained, declarative, confident. |
| Cinematography (Phase 4) | High contrast (4:1 lighting ratio default). Dark Cinema palette enforced. No bright/saturated/cartoony elements. |
| NB2 keyframes (Phase 4) | Asset library focuses on abstract style refs (3-angle rule applied to triptych concept, NOT human face) |
| Seedance prompt (Phase 5) | 50-word ceiling, MASTER CINEMATIC DIRECTION TEMPLATE used. Camera vocabulary: orbit, dolly push, slow rotate. |

## 10. Emotional Arc (State Transformation)

| Beat | Audience State |
|---|---|
| Frame 0 | Skeptical / generic AI fatigue ("another AI consultant?") |
| Frame 3 | Curious — what's this gold particle thing? |
| Frame 6-12 | Recognition — "oh, three pillars: code, agents, video — that's actually different" |
| Frame 15 | Realization — "AI Generalist is a real thing, this person does it" |
| Frame 17-18 | Conviction — "1st place global · 16 countries · he's the real deal" → trigger for CTA click |

## 11. Key Messages

1. **Three disciplines, one mind** — AI Generalist is a unified capability, not 3 separate hires
2. **Visually proven mastery** — the video itself (made via AI) is proof of skill, no need to claim
3. **Globally validated** — Outskill 1st place against 26 startups from 16 countries seals credibility silently

## 12. Call To Action (CTA)

**Primary:** WhatsApp click ("Diagnose My AI Problem" button above the video)
**Secondary:** Calendly book ("Book 30-min slot" — Section 6 of homepage)
**Tertiary:** Scroll to Section 2 ("See Live Work →" button) — exploration mode

**Note:** CTA buttons are **DOM elements above the video**, not video overlay. Video stays clean. Buttons are part of HomeHero.vue component (Phase 2 of homepage plan, line 224 of [main plan](../2026-05-04-homepage-redesign-plan.md)).

## 13. Storyline — Genesis Triptych (7-Beat Arc Mapping)

| Beat # | Skill F-Vault Beat | Time | Action | Visual |
|---|---|---|---|---|
| 1 | Pattern Interrupt | 0-2s | **HOOK** | Single dark frame; gold particles converge to center, forming a glowing glyph (subtle pulse) |
| 2 | Hook | 2-4s | **TENSION/PROMISE** | Glyph splits into 3 floating vertical columns: left=cyan code stream falling, mid=GOLD node graph pulsing (subliminal "1st place" cue), right=cyan video frames cascading |
| 3 | Foreshadow | (none) | — | (Genesis Triptych concept skips Foreshadow — abstract concept self-foreshadows via the 3-pillar split itself) |
| 4 | Agitate | 4-6.5s | **REVELATION 1 — Vibe Coding** | Camera begins slow circular dolly (clockwise from front-center). Left column performs: cyan code lines auto-type, syntax-highlight blooms |
| 5 | Guide+Plan | 6.5-9s | **REVELATION 2 — AI Agents** | Camera continues dolly past mid column. Gold node graph "fires" — connections light up sequentially (left node → mid node → right node), data packets travel along edges |
| 6 | Peak | 9-11.5s | **REVELATION 3 — Video Gen** | Camera completes dolly to right column. Right column performs: video frames morph through cinematic shots, light streaks |
| 7a | CTA | 11.5-13.5s | **RESOLUTION** | Camera returns to center as columns collapse inward, merge into single hologram displaying "AI GENERALIST" wordmark (Space Grotesk, gold tint, slight 3D depth) |
| 7b | Won Day | 13.5-15s | **KICKER + LOOP RESET** | Below wordmark, micro-text appears letter-by-letter (JetBrains Mono caps): `1ST · OUTSKILL DEMO DAY 2026 · 26 STARTUPS · 16 COUNTRIES` (gold tint). Particles disperse outward toward camera → fade to pure black at frame 15.0 (= frame 0 for seamless loop) |

**Beat 3 (Foreshadow) skip justification:** Genesis Triptych is a metaphorical-revealing structure. The Foreshadow beat is implicit in the visual (3 pillars hint at variety BEFORE we see what each does). Adding explicit Foreshadow scene would inflate runtime past 15s. This is a deliberate compression accepted for hero-video format.

## 14. Loop Mechanics

| Frame | State | Notes |
|---|---|---|
| 0.0 | Pure black | Loop start |
| 0.1-2.0 | Gold particles converge | Beat 1 |
| 2.0-4.0 | Triptych split | Beat 2 |
| 4.0-11.5 | 3-column orbit reveal | Beats 4-6 |
| 11.5-13.5 | Wordmark resolution | Beat 7a |
| 13.5-15.0 | Kicker text + dispersion | Beat 7b |
| 15.0 | Pure black | Loop end (= frame 0) |

**Seamless loop checks:**
- Both endpoints (frame 0 + frame 18) MUST be identical pixel values (pure black `#000000`)
- Particle dispersion direction (outward, toward camera) must visually consistent with start state (no particles)
- Gold/cyan accents fully extinguished by 17.95s
- No motion blur ramping at endpoints (Seedance ROPE encoding handles this if prompted correctly)

## 15. Production Specs

| Spec | Landscape (16:9) | Portrait (9:16) |
|---|---|---|
| Resolution | 2048×1152 (Seedance native 2K) | 1152×2048 |
| Frame rate | 24fps | 24fps |
| Duration | 15s seamless loop | 15s seamless loop |
| Audio | NONE (muted autoplay) | NONE |
| Format | MP4 (H.264) + WebM (AV1) | MP4 (H.264) + WebM (AV1) |
| Target file size | ≤ 7MB MP4, ≤ 4.5MB WebM | ≤ 5MB MP4, ≤ 3.5MB WebM |
| Color space | sRGB (web delivery) | sRGB |
| Bitrate target | ~3-4 Mbps | ~2-3 Mbps |

## 16. Reference Strategy (Seedance @-tags — Phase 4 deliverable)

For Phase 4 (`/video-image`), prepare 3 keyframe anchors only:

| Slot | Keyframe File | Time Anchor | Purpose |
|---|---|---|---|
| @Image1 | `keyframe-01-triptych-split.png` | t≈4s | Anchor for "the reveal" — triptych formed, front view. Seedance interpolates 0-4s particle convergence |
| @Image2 | `keyframe-02-orbit-mid.png` | t≈8s | Anchor for "the wow" — 30° offset showing 3D parallax depth |
| @Image3 | `keyframe-03-founder-reveal.png` | t≈14s | **Founder reveal — Ali portrait + wordmark above + kicker below.** Personal branding payoff. Seedance interpolates 14-15s fade to black |
| @Video | NOT USED | — | No motion ref needed — ROPE encoding handles inter-frame motion |
| @Audio | NOT USED | — | Muted hero |

**⚠️ Phase 5 hybrid render note:** KF-03 contains Ali's real face. Seedance 2.0 bans real-face inputs (`seedance_real_face: BANNED` per `seedance_edge_cases §5`). Phase 5 will test Path A (pure Seedance — face passes filter), and fall back to Path B (Seedance abstract 0-12s + VEO 3.1 face beat 12-15s, composited in DaVinci Resolve) or Path C (pivot to Kling AI 2.5 single-platform render, allows faces). Decision deferred to Phase 5.

**Rationale for 3 keyframes (not 5):** Seedance 2.0 single-clip ceiling is 15s — entire video renders in one generation. 3 anchor frames = enough to lock (1) triptych structure, (2) 3D parallax orbit pivot, (3) text-critical end state. Seedance interpolates particle convergence (0-4s) and final fade (14-15s) from text prompt + ROPE encoding. See [`image-prompts.md`](image-prompts.md) for prompt details.

**Saving vs 5-keyframe plan:** 22 NB2 renders → 3 renders (~85% reduction). Operator effort ~6 min wall-clock + ~15 min review.

## 17. CRAFT Framework Application (per seedance_motion_audio §3)

| Field | Value |
|---|---|
| **C**ontext | Abstract metaphorical 3D dark void; 15s seamless loop; AI Generalist hero video for portfolio website |
| **R**eference | @Image1-5 (style) + @Video1 (camera motion) |
| **A**ction | Particle convergence → triptych split → 360° orbit → column collapse → wordmark + kicker |
| **F**raming | Wide → medium dolly → wide hologram resolution |
| **T**one | Premium cinematic, restrained authority, agency-tier |

## 18. Risks & Mitigations (Phase 1 specific)

| # | Risk | Mitigation |
|---|---|---|
| B1 | Abstract concept misunderstood (audience sees pretty motion but doesn't connect to "AI Generalist") | Beat 7a wordmark is the resolution — explicit text "AI GENERALIST" lands message hard |
| B2 | Seedance fails to render readable text overlay (kicker micro-text) | Plan B: render base 0-17s in Seedance, composite kicker text in post via After Effects / DaVinci. CSS/SVG overlay also acceptable for web-only delivery |
| B3 | ~~18s exceeds Seedance 15s single-clip ceiling~~ | **RESOLVED** — duration locked at 15s = single Seedance render, no chaining needed. Maximum coherence. |
| B4 | Seamless loop fails at frame 15=0 boundary | Deliberate fade-to-black at both ends mitigates. Test on web video element with `loop` attribute. If fails, add 0.1s blackout buffer at both ends |
| B5 | Mobile portrait variant (9:16) reframes incorrectly (columns don't fit) | Re-render portrait separately via Seedance 9:16 native (NOT crop from landscape — Seedance 9:16 produces native composition) |
| B6 | Generation cost — multiple iterations needed | Seedance $0.022/sec × 15s = $0.33 per render. Budget 10 renders = $3.30. Trivial. |

## 19. Cultural Context

**Not applicable** — abstract concept transcends cultural specificity. Wordmark is English (global default), kicker text is English (global default).

This is a deliberate choice: hero video must work for both ID and EN homepage variants without re-rendering. Cultural specificity belongs to other sections (testimonials, blog content, etc.) which already use locale-aware delivery.

## 20. Success Criteria (validation in Phase 5 + post-launch)

- [ ] 15s seamless loop renders successfully via Seedance 2.0
- [ ] Both 16:9 and 9:16 variants produced
- [ ] File sizes within budget (≤8MB MP4 / ≤5MB WebM landscape)
- [ ] Wordmark "AI GENERALIST" legible at 1920×1080 desktop AND 414×896 mobile
- [ ] Kicker micro-text legible (or compositied if Seedance fails text rendering)
- [ ] Aesthetic matches Apple keynote tier (subjective, validated via user review at Phase 5 image review gate)
- [ ] Loop has no visible cut at 15s↔0s boundary
- [ ] Lighthouse mobile LCP ≤2.5s when video is hero LCP element
- [ ] Anti-AI-slop pass: no purple-pink gradients, no Inter as display, no generic crystal-ball/brain-network clichés

---

## Phase 1 Status

✅ **Complete.** Strategic brief locked. Ready for Phase 2 (`/video-script`).

**Next command (skipping Phase 2 — script is null for muted abstract hero):**

```
/video-image
```

Phase 4 will produce:
- NB2 (Nano Banana 2) prompts for 5 style keyframes:
  1. `keyframe-01-particles-converging.png` (beat 1 hook)
  2. `keyframe-02-triptych-split.png` (beat 2 tension)
  3. `keyframe-03-orbit-mid-column.png` (beats 3-5 dolly mid-point)
  4. `keyframe-04-wordmark-resolution.png` (beat 7a peak)
  5. `keyframe-05-kicker-text-disperse.png` (beat 7b kicker)
- Optional motion reference clip plan (@Video1 — orbit camera trajectory)
- Asset library with 3-angle rule applied (front, 30°, 60° offsets of triptych)
- Output: `nb2-prompts.md` + `keyframes/` folder with rendered PNGs (after operator runs prompts on Nano Banana 2)
- After keyframes approved → `/video-gen` (Phase 5) generates Seedance image-to-video prompts referencing @Image1-5


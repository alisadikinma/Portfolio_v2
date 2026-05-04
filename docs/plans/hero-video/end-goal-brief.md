# Hero Video — End-Goal Brief (Tool-Agnostic)

**Owner:** Ali Sadikin Ma · alisadikinma.com
**Purpose:** This document describes the END GOAL only. It is platform/tool-agnostic — feed it to any AI image or video model (Midjourney, Imagen 4, FLUX 1.1 Pro, DALL-E 3, Stable Diffusion, Veo 3.1, Kling 2.5, Runway Gen-4, Sora, Seedance 2.0) and let that tool's prompt grammar handle the rest.

---

## 1. What this video must achieve

A 15-second silent looping hero video that sits autoplay-muted on the homepage of alisadikinma.com. When a founder or operator (Series A-C, AI-curious, scrolling on mobile or desktop) lands on the page:

1. They must **stop scrolling within 2 seconds** because the visual feels expensive, deliberate, and not like AI stock.
2. They must **understand within 8 seconds** that the person behind this site operates at the intersection of three AI creative disciplines (vibe coding · AI agents · generative video).
3. They must **see Ali's face once**, paired with the wordmark "AI GENERALIST" and a credential kicker, so the visual identity is anchored to a real human.
4. The clip must **loop seamlessly** — frame 15 dissolves cleanly into frame 0, no visible cut.

If a viewer screenshots this video at any frame, that frame must look like a still from a Denis Villeneuve film — not a Canva template, not an AI demo reel.

---

## 2. The narrative the video tells in 15 seconds

This is not a story with characters and dialogue. It is a **3-act visual metaphor** for AI mastery:

| Act | Time | What the viewer sees emotionally | What it represents |
|---|---|---|---|
| Genesis | 0 – 5s | Three independent forces emerge from a black void, like distant stars resolving into form | Three AI creative disciplines being mastered separately |
| Convergence | 5 – 11s | The three forces orbit, align, and slowly merge as the camera pushes in | Mastery synthesizing into a unified practice |
| Reveal | 11 – 15s | The convergence resolves into a recognizable human face above a wordmark + credential line, then dissolves back to black | The synthesizer revealed — that human is Ali |

The viewer's takeaway feeling, in one sentence: *"This person operates at a level above the noise."*

---

## 3. Visual identity (locked, do not negotiate)

### Aesthetic anchor: Dark Cinema

| Token | Hex | Usage |
|---|---|---|
| Base / void | `#050506` | 95 % of every frame. Pure black, slight cool tint. |
| Primary accent | `#D4A843` (gold) | The center force. Warm, ceremonial, magisterial. |
| Secondary accent | `#06B6D4` (cyan) | The two flanking forces. Cool, technical, electric. |
| Tertiary | `#5E6AD2` (indigo) | Atmosphere, fog, very subtle highlights only. Never dominant. |
| Foreground type | `#EDEDEF` | Wordmark + kicker text. Off-white, not pure white. |

### Typography

- **Wordmark "AI GENERALIST"**: Space Grotesk 700, letter-spacing 0.18 em, uppercase.
- **Kicker line**: JetBrains Mono 500, uppercase, letter-spacing 0.12 em, ~24 % the height of the wordmark.
- **No serif fonts. No script. No Inter, no Roboto, no Helvetica.**

### Camera + lens

- 35 mm anamorphic feel — slight horizontal flare, gentle barrel distortion.
- Atmospheric haze present in every frame (volumetric, not foggy).
- Shallow depth of field on Act 3 (Ali's face); pin-sharp on Act 1.
- Camera motion: orbital push-in across the 15 seconds, ~10° lateral arc total. Never static, never frantic.

---

## 4. Three keyframes the video is built from

The pipeline is **image-to-video**: generate three still images first, then animate between them with a video model. These are the still images that need to exist before any motion render.

### Keyframe 1 — Genesis (timestamp 0 s)

**What it shows:** A pure black void. Three vertical volumes of light have just *begun* to exist within it — they are not solid pillars, not boxes, not architectural columns. They are **standing waves of luminous energy**, the way an aurora borealis curtain hangs in the sky, or the way the monolith in *2001: A Space Odyssey* commands a frame without explaining itself.

- The center volume is warm gold light (`#D4A843`), tall and slender, slightly larger and slightly closer to camera than the other two.
- Two flanking volumes are cool cyan light (`#06B6D4`), positioned symmetrically left and right, set back in space.
- All three are vertical, top to bottom of frame, with soft falloff into atmospheric haze at top and bottom edges.
- The volumes have **internal turbulence** — gentle convection, like flame seen through frosted glass — but they contain no readable content. No code, no text, no UI elements, no icons, no charts, no faces, no objects.
- Negative space dominates: the three volumes occupy roughly 18 % of frame width combined. The other 82 % is dark void with subtle volumetric atmosphere.

**Reference images to study before generating:** aurora borealis curtain photography · the Hubble pillars-of-creation nebula · the monolith in *2001: A Space Odyssey* · the sandworm tease shot in *Dune: Part One* (just before it surfaces) · Blade Runner 2049 hologram volumes.

**Anti-references — DO NOT produce:** Christmas lights, fairy dust, neon signs, glass aquariums, light boxes, theater spotlights, chrome metallic surfaces, any 2D-flat appearance, any compositing-effect look (Photoshop layer styles).

### Keyframe 2 — Convergence (timestamp ~7.5 s, mid-loop)

**What it shows:** The same three light volumes from KF-1, but now seen from a **30° lower-and-right camera position**, mid-orbit. The volumes have moved slightly closer together and slightly forward in the frame. Atmospheric haze is denser. A faint suggestion of motion blur on the camera-side edges of the cyan volumes hints at orbital movement.

- Same color palette, same anti-content rules, same proportions.
- The center gold volume is now subtly more dominant (~20 % larger than KF-1 due to forward parallax).
- Lens flare bloom around the gold volume's vertical axis — anamorphic horizontal streak, gold-tinted, fading at frame edges.
- A few glints of warm-cyan particles (very few, ≤ 12 across the frame) drift through the haze. They are dust motes catching light, not "AI sparkle."

**This frame must be visually consistent with KF-1** so a video model can interpolate smoothly between them.

### Keyframe 3 — Reveal (timestamp 14.5 s, just before loop closure)

**What it shows:** The three light volumes have merged into a single luminous vertical axis behind a human portrait. The composition is a 3-zone vertical stack:

```
┌─────────────────────────────────────┐
│                                     │
│      AI GENERALIST                  │  ← top zone: wordmark, ~12% of frame height
│                                     │
├─────────────────────────────────────┤
│                                     │
│           [ portrait ]              │  ← center zone: Ali's face, ~55% of frame height
│                                     │
│                                     │
├─────────────────────────────────────┤
│  1ST · OUTSKILL DEMO DAY 2026 ·     │  ← bottom zone: kicker, ~10% of frame height
│  26 STARTUPS · 16 COUNTRIES         │
└─────────────────────────────────────┘
```

- **Portrait**: Ali Sadikin Ma — Indonesian male, 40-45, bald, rounded rectangular eyeglasses, navy/dark suit jacket over white shirt. Photorealistic, recognizable. Reference photo at `https://alisadikinma.com/uploads/about/1761935897_alisadikin_profile_photo.png` — the rendered face must match this person's identity (bald + glasses + ethnicity), not a generic AI-generated man.
- **Lighting on the face**: rim-lit warm gold from above, cyan key fill from camera left, deep shadow on camera right. ~3:1 lighting ratio. Cinematic, not flat.
- **Wordmark and kicker**: 100 % legible character-by-character. No artistic distortion of letters. Crisp anti-aliased edges.
- **Background**: the merged luminous axis is now a soft warm-gold glow directly behind the head, fading to black at frame edges. Some residual atmospheric haze.
- **Negative space**: the portrait sits in roughly the central 40 % of frame width. Wordmark + kicker each occupy ~70 % of frame width, centered.

The frame must feel like a movie poster, not a press headshot.

---

## 5. Motion specification (between the keyframes)

A video model generating this clip should produce:

- **0 – 5 s**: Slow orbital push-in. Camera arcs ~5° to the right while moving 8 % closer to the gold volume. The three volumes' internal turbulence convects gently — not stormy, not still. Atmospheric haze drifts subtly.
- **5 – 11 s**: Continued orbit, now ~15° total lateral arc. The flanking cyan volumes begin to drift inward toward the center axis, like planets falling into orbit. Lens flare on the gold volume builds slowly. No abrupt motion.
- **11 – 13 s**: The three volumes converge into a single vertical axis. The portrait of Ali fades in over the converging axis — additive blend, ~1.5 s crossfade. Wordmark and kicker text fade in 0.3 s after the face.
- **13 – 15 s**: Hold on the reveal frame for ~1.5 s. Then the entire scene slowly desaturates and dims to black over the final 1.0 s, returning to the void state. **The last frame must visually equal the first frame** so the loop is invisible.

Frame rate: 24 fps cinematic.

---

## 6. Output specifications

| Spec | Value |
|---|---|
| Aspect ratio (primary) | 16 : 9 landscape — 2048 × 1152 |
| Aspect ratio (mobile) | 9 : 16 portrait — 1152 × 2048 (re-render or center-crop with extended atmosphere on sides) |
| Duration | 15.00 s exact |
| Frame rate | 24 fps |
| Loop | Seamless — frame 15 = frame 0 |
| Codec | H.264 Main Profile MP4 + AV1 WebM |
| Audio | None — video plays autoplay-muted |
| File size budget | ≤ 7 MB MP4 / ≤ 4.5 MB WebM (landscape) |
| Quality bar | Indistinguishable from a 35 mm film clip at first glance |

---

## 7. Hard "do not" list

These are repeat failure modes from earlier renders. Any output that contains any of these is automatically rejected:

1. **No rectangular boxes, glass containers, light boxes, or aquarium-like volumes.** The three light forms are *fields*, not contained objects.
2. **No readable content inside the light.** No code lines, no text, no icons, no UI elements, no charts, no network graphs, no video thumbnails, no logos.
3. **No purple, magenta, pink, hot pink, or fuchsia anywhere.** Cyan and gold only on accents.
4. **No chrome, no metallic surfaces, no reflective spheres.** Light only.
5. **No fairy dust, no Christmas lights, no neon signage, no laser beams.** Volumetric light only.
6. **No flat 2D appearance, no Photoshop-layer-style look.** Real cinematic depth required.
7. **No literal AI imagery — no robots, no humanoid AI, no brain holograms, no binary stream backgrounds, no glowing matrix code.**
8. **No stock corporate "tech" tropes.** No circuit boards, no abstract gear icons, no globes wrapped in data lines.
9. **No generic AI-generated face for KF-3.** It must be the actual person referenced at the URL above. Bald + glasses + Indonesian ethnicity + the specific facial structure visible in that photo.
10. **No serif fonts, no script fonts, no Inter, no Roboto, no Helvetica.** Space Grotesk + JetBrains Mono only.

---

## 8. Quality acceptance checklist

A render is accepted only when all of these are true:

- [ ] At any pause-frame between 0 – 11 s, the image looks like a still from a feature film.
- [ ] The three light volumes are clearly *energy fields*, not architectural columns or boxes.
- [ ] Negative space (black void) dominates ≥ 75 % of every frame.
- [ ] Color palette is restricted to gold + cyan + black + off-white, with subtle indigo atmosphere only.
- [ ] At KF-3, the person's face is recognizably Ali Sadikin Ma — bald, glasses, Indonesian features. A stranger who has met Ali once can identify him.
- [ ] At KF-3, the wordmark "AI GENERALIST" is 100 % legible, character-by-character, crisp.
- [ ] At KF-3, the kicker line is 100 % legible, character-by-character, crisp.
- [ ] The 15 s loop closes invisibly (frame 15 ≈ frame 0).
- [ ] No visible AI-generation artifacts (no warped letters, no extra fingers if hands appear, no impossible architecture).
- [ ] The video could be cut into a Denis Villeneuve film and not look out of place.

---

## 9. Reference photo for KF-3

Operator must download and supply this image to whichever tool generates KF-3:

**URL:** `https://alisadikinma.com/uploads/about/1761935897_alisadikin_profile_photo.png`
**Save as:** `alisadikin_profile_photo.png`

This is the identity-lock reference. The face in KF-3 must match this photo's bald head, rounded rectangular eyeglasses, age, and Indonesian ethnicity.

---

## 10. Tool-specific hints (optional)

If the operator hands this brief to a specific tool, these adjustments help:

- **Midjourney v7 / FLUX 1.1 Pro / Imagen 4**: Strong on KF-1 and KF-2 abstracts. Weak on KF-3 face identity — feed the reference photo via image prompt or character reference. Use `--ar 16:9 --style raw` on Midjourney; `aspect_ratio="16:9"` on FLUX.
- **DALL-E 3 / GPT-Image-1**: Good at instruction-following but weak at photorealistic face fidelity. Use only for KF-1 and KF-2.
- **Veo 3.1 (Google)**: Allows real-face inputs. Best end-to-end choice for KF-3 → 15 s clip if Seedance rejects.
- **Kling AI 2.5**: Allows faces. Single-platform option for full 15 s render with all 3 keyframes.
- **Sora 2 / Runway Gen-4**: Strong motion quality. Feed all 3 keyframes as conditioning.
- **Seedance 2.0 (ByteDance)**: Best abstract motion (KF-1 → KF-2 segment), but bans real-face inputs. Use for the 0 – 11 s abstract segment, then composite a separate face render for 11 – 15 s in DaVinci Resolve.

---

## 11. Production decision the operator still owns

After all three keyframes are rendered, choose one of:

| Path | Pipeline | Cost (rough) | Pros | Cons |
|---|---|---|---|---|
| A | Single tool (Veo 3.1 or Kling 2.5) renders all 15 s with face | $1 – 5 | One render, simplest | Less specialized motion |
| B | Hybrid — Seedance 0 – 11 s abstract + Veo 11 – 15 s face, composite in DaVinci | $3 – 5 | Best motion + face fidelity | 2 renders + ~30 min compositing |
| C | Three-tool stack (Midjourney KF still + FLUX KF still + Kling animation) | $5 – 8 | Maximum quality control | Highest complexity |

The brief above does not depend on this choice — it is the END GOAL regardless of which production path delivers it.

---

**Document version:** 1.0
**Last updated:** 2026-05-04
**Supersedes:** docs/plans/hero-video/image-prompts.md (which was tool-specific to Nano Banana 2 and produced two unacceptable renders)

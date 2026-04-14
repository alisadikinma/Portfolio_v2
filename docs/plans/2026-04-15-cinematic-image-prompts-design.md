# Cinematic Image Prompts — Design Doc

## Goal

Upgrade the article-content-writer plugin's image prompt quality from generic ~50-word prompts to cinematic ~300-500 word prompts matching the quality standard of the carousel plugin (`D:\Projects\claude-plugin\ai-image-carousel-prompt-gen\`).

## Problem

Current article plugin generates prompts like:
> "A professional speaker on a modern conference stage illuminated by a warm spotlight, facing a massive engaged audience filling an auditorium, dramatic wide shot..."

This produces generic images without cinematic quality. The carousel plugin produces prompts 10x better because it enforces an 8-element WOW framework, specific lens/lighting/film stock, 3 depth layers, and cinematographer references.

## Solution Overview

Port the carousel plugin's cinematic standards into article-content-writer. Skip the text-overlay/carousel-specific parts (not applicable for blog images).

## Port Sources (from carousel plugin)

| Source File | Purpose | Port As |
|-------------|---------|---------|
| `prompt-formulas.md` | 8-element WOW framework, 5-paragraph structure | Merge into `image-prompt-guide.md` |
| `cinematography-lut.md` | LUT tables (emotion, lighting, color temp, cinematographers, film stocks, shot types, atmosphere) | **NEW** `references/cinematography-lut.md` |
| `hook-visual-library.md` (partial) | Expression libraries (generic parts only) | Merge into `cinematography-lut.md` |

## Skip (not applicable for blog)

- Text overlay / headline / watermark rendering
- Multi-slide carousel structure
- Social media platform formatting (4:5/9:16)
- Hook-specific expressions (blog doesn't have hooks)
- Swipe text, page numbers, brand badges

## Changes to article-content-writer Plugin

### 1. `references/image-prompt-guide.md` (ENHANCE)

Add new sections:

#### Cinematic Prompt Standard (8 Mandatory Elements)
1. **Lighting Drama** — pattern name (Rembrandt/Butterfly/Loop/Split) + ratio (2:1/4:1/8:1/16:1) + Kelvin temp (3200K/5600K/etc.)
2. **Depth Layers** — foreground + midground + background (explicitly labeled)
3. **Atmosphere** — volumetric light, particles, bokeh, haze, smoke, rain
4. **Color Contrast** — warm-cool tension, accent colors, color palette
5. **Emotional Peak** — expression specifics (eyes, mouth, body language) or scene emotion
6. **Camera Intention** — shot type + lens + aperture + angle + DOF + purpose
7. **Texture Realism** — material-specific (skin pores, fabric weave, metal, wood grain)
8. **Cinematic Reference** — film stock (Kodak Portra 400) + color grade + DP signature (Roger Deakins, Denis Villeneuve, etc.)

#### 5-Paragraph Structure (mandatory)

```
[Paragraph 1] Shot type + subject + expression + wardrobe
[Paragraph 2] Foreground / Midground / Background depth layers
[Paragraph 3] Lens specs + lighting setup + ratios + Kelvin
[Paragraph 4] Film stock + color grade + atmosphere + texture + cinematographer
[Paragraph 5] Aspect ratio + negative constraints ("no text", "no logos")
```

#### WOW Quality Gate
- All 8 elements MUST be present
- Minimum 6/8 = PASS, 8/8 = EXCELLENT
- Fail = rewrite prompt

#### 3 Example Prompts
- Cover image (hero, wide shot, 16:9)
- Inline scene (medium shot with person, 16:9)
- Data visualization (abstract, 4:3)

### 2. `references/cinematography-lut.md` (NEW)

Port directly from carousel plugin (strip non-applicable):

| Table | Content |
|-------|---------|
| Emotion → Setup | Shock, Curiosity, Confidence, Awe, Contemplation, etc. → expression + lighting + lens |
| Lighting Patterns | Rembrandt (45° side), Butterfly (above), Loop (30-45°), Split (90°), Rim |
| Lighting Ratios | 2:1 (subtle), 4:1 (moderate), 8:1 (high), 16:1 (extreme) |
| Color Temperatures | 1900K Candlelit → 12000K Overcast (8 common temps) |
| Cinematographers | Deakins, Fraser, Lubezki, Van Hoytema, Young, Villeneuve — with signature techniques |
| Camera Specs | Shot types (ECU→EWS) with lens ranges + angles with psychology |
| Film Stocks | Portra 400 (default warm), Vision3 500T, Ektar 100, Tri-X, CineStill 800T, Velvia |
| Color Grading | Teal & Orange, Bleach Bypass, Golden Hour, Blue Hour, Muted, Cross-Process |
| Atmosphere Elements | Haze, fog, smoke, rain, dust, bokeh, volumetric, god rays |
| Quick Combos | Content type → complete setup preset |

### 3. `skills/article-write/SKILL.md` (MODIFY)

Add "Cinematic Prompt Requirement" section:
- Every image_prompt.prompt field MUST follow 5-paragraph structure
- Every prompt MUST include all 8 elements (min 6/8)
- Prompt length: 300-500 words (was ~50)
- Reference `cinematography-lut.md` for specific values

### 4. `skills/article-gen/SKILL.md` — same
### 5. `agents/article-writer.md` — same
### 6. `scripts/compile-references.sh` (MODIFY)

Include `cinematography-lut.md` in:
- `refs-write.md` (main use — during writing)
- NOT in `refs-prep.md` (not needed during research/outline)
- NOT in `refs-score.md` (not needed during scoring)

## Before vs After Example

### Before (current, ~50 words)
```
A professional speaker on a modern conference stage illuminated by a warm spotlight,
facing a massive engaged audience filling an auditorium, dramatic wide shot from behind
audience showing scale, warm stage lighting with cool ambient fill, photorealistic.
```

### After (cinematic, ~300 words)
```
A photorealistic cinematic wide shot of a professional speaker on a modern conference
stage illuminated by warm spotlight, facing thousands of engaged audience members
filling a massive auditorium, arms raised mid-gesture conveying confident authority,
eyes scanning the crowd with genuine connection, dark business attire with subtle
microphone headset catching warm rim light.

foreground: silhouette of audience heads and shoulders in bokeh, warm amber light
catching hair edges. midground: the speaker center-stage with dramatic spotlight pool
creating strong figure-to-ground separation, hand gestures frozen mid-motion.
background: curved LED screen showing abstract blue data visualization patterns,
warm ambient stage lights framing the scene, deep perspective lines pulling eye
toward center.

lens: 24mm f/2.8, low angle from audience seat, shallow depth of field with speaker
in sharp focus. dramatic Rembrandt key light at 4:1 ratio from upper-left at 3200K
warm tungsten, cool 5600K ambient fill from back-of-house creating warm-cool tension
across the stage, strong rim light separating speaker from LED screen.

Kodak Portra 400, warm golden amber grade. heavy volumetric atmosphere from stage
haze catching light beams, dust particles floating in spotlight pool. natural fabric
texture on suit with visible weave, skin texture with subtle sweat sheen under hot
lights, LED screen pixel grid barely visible in background. cinematography inspired
by Emmanuel Lubezki.

16:9 aspect ratio. no text visible. no competitor branding.
```

## Expected Impact

- Image quality: from "generic stock photo" → "cinematic editorial photography"
- Prompt length: ~50 words → ~300-500 words (6-10x longer)
- Compiled reference size: refs-write.md grows ~15KB (from 49KB → ~64KB)
- Generation time: minimal impact (writing is model-bound, not prompt-bound)
- Image generation cost: same (GeminiGen charges per image, not per prompt length)

## Dependencies

None. Pure plugin changes. Backend and frontend unchanged.

## Next Step

→ `gaspol-plan` to turn this design into an implementation plan with phases + verification.

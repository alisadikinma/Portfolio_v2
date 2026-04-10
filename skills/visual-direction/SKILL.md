---
name: visual-direction
description: "Emotion-to-visual profile mapping (authority, curiosity, excitement, shock, warmth, warning), 3-layer prompt synthesis architecture, and carousel/thumbnail psychology. Use for image direction in any visual content."
---

# Visual Direction & Prompt Synthesis

## 9. Visual Direction per Hook/Emotion Category

> **Source:** SparkFluence `_shared/knowledge/carousel/visual-action-bank.ts` + `hook-science.ts` — per-emotion visual profiles for image generation.

### Emotion → Visual Profile Mapping

Use this to select the right visual direction when generating images for different article sections.

**Authority / Confidence (HOOK, CTA sections)**
```
Expression: Steady gaze, knowing smile, composed
Body Language: Arms crossed or hands clasped, upright posture
Lighting: Rembrandt 4:1 ratio, 3200K warm tungsten
Camera: MCU (Medium Close-Up), 50-85mm lens
Environment: Clean studio or modern office backdrop
Color Grade: Warm amber, deep contrast
Film Stock: Kodak Vision3 500T
```

**Curiosity / Mystery (HOOK, FORESHADOW sections)**
```
Expression: Raised eyebrow, slight lean forward, intrigued
Body Language: Hand on chin, searching gaze, head tilt
Lighting: Split 8:1 ratio or low-key Rembrandt 4:1
Camera: CU (Close-Up), 85mm f/1.8
Environment: Dark/moody with mystery shadows, single key light
Color Grade: Teal and amber, lifted shadows
Film Stock: CineStill 800T (halation glow)
```

**Excitement / Energy (PEAK, transformation reveals)**
```
Expression: Wide smile, bright eyes, dynamic gesture
Body Language: Open arms, leaning forward, animated
Lighting: Butterfly 2:1 ratio, 3500K warm golden
Camera: MCU or MS, slightly low angle for power
Environment: Vibrant, energetic space with warm tones
Color Grade: Warm golden hour, saturated
Film Stock: Portra 400 warm skin tones
```

**Shock / Surprise (HOOK pattern interrupts)**
```
Expression: Wide eyes, open mouth, dramatic gesture
Body Language: Stepping back, hands raised, startled
Lighting: Rim 6:1+ ratio, dramatic edge light
Camera: CU, quick zoom feel, 85mm
Environment: High contrast, dark background with spotlight
Color Grade: High contrast, deep blacks
Film Stock: Ektar 100 saturated vivid
```

**Warmth / Relatability (BODY, personal stories)**
```
Expression: Genuine smile, soft eyes, open stance
Body Language: Welcoming gesture, relaxed posture
Lighting: Loop 2:1 ratio, 3500K warm soft
Camera: MS (Medium Shot), 50mm natural perspective
Environment: Cozy workspace, natural light from window
Color Grade: Warm, muted, approachable
Film Stock: Portra 400
```

**Problem / Warning (Negative bias hooks)**
```
Expression: Wide alarmed eyes, tight jaw, concern
Body Language: Aggressive forward lean, pointing
Lighting: Short-side Rembrandt 4:1-6:1, dramatic contrast
Camera: MCU, slightly Dutch angle for unease
Environment: Dark, constrained, urgent atmosphere
Color Grade: Cool desaturated with warm accent
Film Stock: Kodak Vision3 500T with cool grade
```

### Blog Section → Visual Direction Quick Reference

| Article Section | Emotion | Shot | Lighting | Mood |
|---|---|---|---|---|
| Hero Image | Authority or Curiosity | WS or EWS 16:9 | Rembrandt/Split 4:1 | Cinematic, inviting |
| Problem Statement | Warning/Negative | MCU 16:9 | Short-side 4:1-6:1 | Tense, urgent |
| Solution Reveal | Excitement | MS 4:3 | Butterfly 2:1 | Energetic, warm |
| Case Study Before | Problem | WS 16:9 | Flat, desaturated | Struggling |
| Case Study After | Joy/Excitement | WS 16:9 | Golden hour 3500K | Transformed |
| Technical Diagram | Neutral | Top-down or isometric | Daylight 5600K | Clean, clear |
| Author/Personal | Warmth | MCU 1:1 | Loop 2:1, warm | Approachable |
| CTA Image | Authority + Desire | MCU 16:9 | Rembrandt 4:1 | Confident, inviting |

---

## 10. Prompt Synthesis Architecture

> **Source:** SparkFluence `_shared/prompts/cinematicImageKnowledge.ts` + `promptSynthesizer.ts` — the LLM-based prompt construction system.

### Three-Layer Prompt Building

**Layer 1: Cinematography Specs Injection**
Maps emotions to specific visual parameters:
```
Input: emotion = "authority"
Output:
  expression: "steady gaze, knowing smile"
  body_language: "arms crossed"
  lighting: "Rembrandt 4:1 ratio"
  prompt_phrase: "steady unwavering gaze, composed expression, knowing smile"
```

**Layer 2: LLM-Based Prompt Synthesis**
System role: "You are an expert cinematographer and AI image prompt engineer"

Input variables for prompt generation:
```
visualDirection:     Base scene description (from article context)
scriptText:          Article excerpt or section summary (for context)
emotion:             Hook category (authority, curiosity, shock, etc.)
aspectRatio:         16:9, 4:3, 1:1
hasReferenceImage:   Boolean (using author photo?)
segmentType:         HOOK, BODY, PEAK, CTA (article section)
```

**Layer 3: Contextual Outfit/Setting Selection**
LLM picks appropriate attire/environment based on topic:
```
TECH:      "Dark hoodie, casual tech wear, modern workspace"
FINANCE:   "Navy blazer, white shirt, clean office"
EDUCATION: "Smart casual, glasses, whiteboard background"
CREATIVE:  "Artistic attire, colorful studio, creative tools"
MEDICAL:   "White coat, stethoscope, clinical setting"
```

### Blog-Specific Prompt Enhancement Rules

When generating images for blog articles, apply these additional rules:

1. **No watermarks** — blog content is original, no branding overlay needed
2. **Professional film stocks** — Portra 400, Vision3 500T, Ektar 100 (not amateur)
3. **Standard 3:1-4:1 lighting ratio** — clarity over extreme drama (avoid 8:1 for blog)
4. **Include atmosphere** — "subtle ambient haze, depth of field separation"
5. **16:9 for heroes, 4:3 for in-article** — match blog layout
6. **Descriptive alt text context** — generate prompt that naturally describes the scene (helps write alt text)
7. **Consistent style across article** — use same film stock + color grade for all images in one article

### Complete Prompt Template for Blog Hero Image

```
[SUBJECT]: A confident [ethnicity] [profession] [action/pose]
[SETTING]: in a [specific environment] with [environmental details]
[CAMERA]: [shot type] framed with [composition rule] using [lens]mm lens
[LIGHTING]: [pattern] lighting at [ratio] ratio with [temperature]K [quality]
[STYLE]: shot on [film stock] film stock, [rendering quality]
[TEXTURE]: [texture detail], [atmosphere], [depth effect]
[COLOR]: [primary color tone] and [secondary tone] color grading with [shadow treatment]
```

**Example filled in:**
```
A confident Southeast Asian male AI engineer presenting code on a
holographic display, in a futuristic co-working space with panoramic
city views and ambient blue LED accents, wide shot framed with golden
ratio using 24mm lens, Rembrandt lighting at 4:1 ratio with 3500K
golden hour warmth, shot on Kodak Vision3 500T tungsten film stock
with photorealistic cinematography quality, subtle lens flare and
atmospheric haze creating natural depth separation, warm amber and
teal color grading with lifted blacks and controlled highlights
```

---

## 11. Carousel & Thumbnail Image Knowledge

> **Source:** SparkFluence `_shared/knowledge/carousel/` — 52 headline formulas + visual psychology for scroll-stopping images.

### 8 Psychology-Based Visual Styles for Blog Thumbnails

| Psychology | Visual Profile | When to Use |
|---|---|---|
| **Unbeatable Value** | Wide eyes, open smile, Butterfly 2:1, 3500K | "Free tool" articles, resource lists |
| **Problem-Solver** | Empathetic knowing gaze, Rembrandt 3:1 | Tutorial, troubleshooting articles |
| **Plot Twist** | Eyes widening, incredulous expression, Rembrandt 4:1 | "I tested X" experimental articles |
| **FOMO/Urgency** | Alarmed eyes, tight jaw, forward lean, 6:1 contrast | Time-sensitive, trending topic articles |
| **Social Proof** | Confident gaze, knowing smile, Butterfly 2:1, 4000K | "X people use this" authority articles |
| **Comparison** | One eyebrow raised, asymmetric smirk, Rembrandt 3:1 | Tool comparison, vs articles |
| **Hyper-Targeted** | Soft intense eye contact, gentle smile, Loop 2:1 | Niche audience articles ("For Laravel devs") |
| **Curiosity/Teaser** | Eyebrows raised, suppressed grin, Rembrandt 4:1 | Recommendation, listicle articles |

### OG Image / Social Share Specifications

```
Dimensions: 1200 x 630 (1.91:1 ratio) — universal social share
Quality: Maximum (DALL-E 3 or Nano Banana /Edit recommended)
Text overlay: Blog title in large readable font (if using AI text rendering)
Branding: Subtle — small logo or name, not dominant
Face: Including author's face increases CTR by 38%
Colors: Match blog's design system (gold #D4A843 + dark #050506)
```

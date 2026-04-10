# RAG Knowledge: AI Image Generation for Blog Articles

> **Purpose:** Reference guide for generating AI images to accompany blog articles on alisadikinma.com.
> **Source:** Extracted from SparkFluence platform (`D:\Projects\sparkfluence_platform`) + GeminiGen API docs.
> **Last Updated:** 2026-04-10

---

## Table of Contents

1. [Model Selection Guide](#1-model-selection-guide)
2. [API Integration (GeminiGen AI)](#2-api-integration-geminigen-ai)
3. [API Integration (FAL.AI - Nano Banana 2)](#3-api-integration-falai---nano-banana-2)
4. [Prompt Engineering Rules](#4-prompt-engineering-rules)
5. [Blog-Specific Image Strategy](#5-blog-specific-image-strategy)
6. [Cost Optimization](#6-cost-optimization)
7. [Fallback Chain Logic](#7-fallback-chain-logic)
8. [Quick Reference Tables](#8-quick-reference-tables)

---

## 1. Model Selection Guide

### Decision Matrix: Which Model for Which Blog Image?

| Image Type | Recommended Model | Cost | Why |
|---|---|---|---|
| **Hero/Featured Image** | Seedream v4 (T2I) | $0.03 | High-res, excellent quality, best value |
| **Hero with Person** | Nano Banana 2 /Edit | $0.08 | Face consistency with reference images |
| **In-Article Illustration** | Imagen-pro (GeminiGen) | FREE | Rate-limited but free, good for volume |
| **Technical Diagram Style** | Qwen Image 2 (T2I) | $0.035 | Supports negative prompts to exclude noise |
| **Premium Thumbnail** | DALL-E 3 | $0.08 | Highest quality, best for social sharing |
| **Bulk Generation** | Imagen-4-Fast (GeminiGen) | $0.02 | Cheapest paid option, no rate limit |
| **Fallback (Free)** | FLUX.1-schnell (HuggingFace) | FREE | No rate limit, lower quality |

### Model Capability Matrix

| Model | Provider | Cost | Reference Images | Negative Prompt | Max Resolution | Text in Image |
|---|---|---|---|---|---|---|
| Nano Banana 2 /Edit | FAL.AI | $0.08 | Up to 14 | No | 2048x2048 | Limited |
| Nano Banana 2 T2I | FAL.AI | $0.06 | No | No | 2048x2048 | Limited |
| Seedream v4 | FAL.AI | $0.03 | No | No | 2048x2048 | Excellent |
| Seedream v5 Lite | FAL.AI | $0.035 | No | No | 2048x2048 | Good |
| Seedream v5 Lite /Edit | FAL.AI | $0.035 | Yes | No | 2048x2048 | Good |
| Qwen Image 2 T2I | FAL.AI | $0.035 | No | Yes | 2048x2048 | Good |
| Qwen Image 2 Pro /Edit | FAL.AI | $0.075 | Yes | No | 2048x2048 | Good |
| FLUX Kontext Max Multi | FAL.AI | $0.10 | Multi-ref | No | Standard | Limited |
| Imagen-pro | GeminiGen | FREE | Yes | No | Standard | Moderate |
| Imagen-4-Fast | GeminiGen | $0.02 | Yes | No | Standard | Good |
| Imagen-4-Ultra | GeminiGen | $0.05 | Yes | No | Premium | Good |
| DALL-E 3 | OpenAI | $0.08 | No | No | 1792x1024 | Moderate |
| GPT-Image-1 | OpenAI | $0.04 | Edit API | No | Standard | Moderate |
| FLUX.1-schnell | HuggingFace | FREE | No | No | Standard | Poor |

---

## 2. API Integration (GeminiGen AI)

### Authentication
```
API Key: Store in .env as GEMINIGEN_API_KEY
Base URL: https://api.geminigen.ai/uapi/v1
```

### Generate Image (Synchronous + Async Polling)

**Request:**
```http
POST https://api.geminigen.ai/uapi/v1/generate_image
Headers:
  x-api-key: {GEMINIGEN_API_KEY}
Content-Type: multipart/form-data

Body (FormData):
  prompt: string              # Image description
  model: "imagen-pro"         # or "imagen-4-fast", "imagen-4-ultra"
  aspect_ratio: "16:9"        # or "9:16", "1:1", "4:3", "3:4"
  style: "Photorealistic"     # Optional, see style list below
  file_urls: string           # Optional, reference image URL
```

**Response:**
```json
{
  "status": 2,
  "generate_result": "https://cdn.geminigen.ai/...",
  "uuid": "abc-123"
}
```

- `status: 2` = Image ready immediately (use `generate_result` URL)
- `status: 1` = Processing (poll with UUID)
- `status: 3` = Failed (check `error_message`)

**Polling (when status=1):**
```http
GET https://api.geminigen.ai/uapi/v1/history/{uuid}
Headers:
  x-api-key: {GEMINIGEN_API_KEY}

Poll every 2 seconds, max 30 attempts (60s timeout)
```

### Available Styles (GeminiGen)

**Imagen-pro (FREE):** Photorealistic, Portrait Cinematic, 3D Render, Watercolor, Illustration, Anime General, Flat Design, Sketch, Oil Painting, Low Poly, Paper Craft, Pixel Art, Pop Art, Sticker, Vintage Photo, Comic Book

**Imagen-4-Fast ($0.02):** Photorealistic, Illustration, Anime General, 3D Render, Creative, Dynamic

**Imagen-4-Ultra ($0.05):** Portrait Cinematic, Ray Traced, Fashion, Photorealistic

### Rate Limits (Imagen-pro FREE Tier)
- 5 requests/minute
- 100 requests/hour
- 1000 requests/day

---

## 3. API Integration (FAL.AI - Nano Banana 2)

### Nano Banana 2 /Edit (With Reference Images)

**Request:**
```http
POST https://fal.run/fal-ai/nano-banana-2/edit
Headers:
  Authorization: Key {FAL_AI_API_KEY}
  Content-Type: application/json

Body:
{
  "prompt": "...",
  "image_urls": ["https://...ref1.jpg", "https://...ref2.jpg"],
  "image_size": { "width": 1792, "height": 1024 },
  "num_inference_steps": 25,
  "guidance_scale": 5.0,
  "output_format": "png"
}
```

**Response:**
```json
{
  "images": [{
    "url": "https://fal.media/files/...",
    "width": 1792,
    "height": 1024
  }]
}
```

**Reference Image Rules:**
- Up to 14 reference images via `image_urls` array
- Index 0 = primary reference (face/subject to preserve)
- Index 1+ = scene/style references
- Semantic weighting: auto-prioritizes based on prompt context
- For face preservation, append to prompt: "Maintain exact facial features, eye color, hairstyle, and expression from the first reference image."

### Nano Banana 2 T2I (Text-to-Image, No Reference)

```http
POST https://fal.run/fal-ai/nano-banana-2/text-to-image
Headers:
  Authorization: Key {FAL_AI_API_KEY}
  Content-Type: application/json

Body:
{
  "prompt": "...",
  "image_size": { "width": 1792, "height": 1024 },
  "num_inference_steps": 25,
  "guidance_scale": 5.0,
  "output_format": "png"
}
```

### Seedream v4 (Best Value B-ROLL)

```http
POST https://fal.run/fal-ai/bytedance/seedream/v4/text-to-image
Headers:
  Authorization: Key {FAL_AI_API_KEY}
  Content-Type: application/json

Body:
{
  "prompt": "...",
  "image_size": { "width": 1792, "height": 1024 },
  "num_images": 1,
  "num_inference_steps": 25,
  "guidance_scale": 5.0,
  "enhance_prompt_mode": "standard"
}
```

### Aspect Ratio → Dimension Mapping

| Aspect Ratio | Width | Height | Use Case |
|---|---|---|---|
| 16:9 | 1792 | 1024 | Blog hero images, headers |
| 1:1 | 1024 | 1024 | Social media thumbnails |
| 4:3 | 1024 | 768 | In-article illustrations |
| 3:4 | 768 | 1024 | Portrait, mobile-first |
| 9:16 | 1024 | 1792 | Stories, vertical content |

---

## 4. Prompt Engineering Rules

### 8-Element Mandatory Prompt Structure

Every image prompt MUST include these 8 elements in order:

1. **Subject** — who/what (always first in prompt)
2. **Action/Pose** — what they're doing
3. **Setting/Location** — where the scene takes place
4. **Camera/Composition** — shot type, angle, lens
5. **Lighting/Atmosphere** — lighting pattern, ratio, Kelvin temperature
6. **Style/Medium** — film stock, rendering style
7. **Texture/Cinematic Detail** — realism cues, DP reference
8. **Color Grading** — overall tone, palette

### Example: Blog Hero Image Prompt

```
A confident Southeast Asian male developer working at a sleek desk with
dual monitors showing code, modern co-working space with floor-to-ceiling
windows and city skyline at golden hour, wide shot framed with rule of thirds
using 24mm lens, Rembrandt lighting at 4:1 ratio with warm 3500K fill,
shot on Kodak Vision3 500T tungsten film stock, subtle lens flare and
atmospheric haze creating depth separation, warm amber and teal color
grading with lifted shadows
```

### Lighting Patterns Reference

| Pattern | Position | Shadow | Mood | Ratio |
|---|---|---|---|---|
| Rembrandt | 45 deg side, above | Triangle on cheek | Dramatic, authority | 4:1 |
| Butterfly | Directly above | Butterfly under nose | Glamorous, beauty | 2:1 |
| Split | 90 deg direct side | Half-face shadow | Intense, duality | 8:1+ |
| Loop | 30-45 deg from camera | Small nose loop | Natural, flattering | 4:1 |
| Rim | Behind subject | Glowing outline | Separation, drama | 6:1+ |
| Broad | Lit side toward camera | Shadow away | Wider appearance | 3:1 |

### Film Stock Specs

| Stock | ISO | Balance | Character | Prompt Phrase |
|---|---|---|---|---|
| Kodak Vision3 500T | 500 | 3200K Tungsten | Hollywood standard | "Kodak Vision3 500T tungsten" |
| Portra 400 | 400 | Daylight | Warm skin, portrait | "Portra 400 warm skin tones" |
| Kodak 250D | 250 | 5500K Daylight | Crisp, accurate | "Kodak 250D daylight crisp" |
| CineStill 800T | 800 | 3200K Tungsten | Halation glow, neon | "CineStill 800T halation neon" |
| Ektar 100 | 100 | Daylight | Saturated, vivid | "Ektar saturated vivid colors" |

### Color Temperature (Kelvin)

| Name | K | Character | Use |
|---|---|---|---|
| Candlelight | 1900 | Deep warm orange | Intimate scenes |
| Tungsten | 3200 | Classic warm | Interior, studio |
| Golden Hour | 3500 | Magic gold | Epic, warm, hero images |
| Daylight | 5600 | Neutral white | Standard, technical |
| Overcast | 6500 | Cool soft | Diffused, calm |
| Blue Hour | 9000 | Twilight blue | Mysterious, moody |

### Shot Types

| Shot | Frame | Lens | Purpose |
|---|---|---|---|
| ECU (Extreme Close-Up) | Eyes/detail only | 100mm macro | Product detail, intense emotion |
| CU (Close-Up) | Face fills frame | 85mm f/1.8 | Strong emotion, portraits |
| MCU (Medium Close-Up) | Head + shoulders | 50-85mm | Dialogue, connection |
| MS (Medium Shot) | Waist up | 50mm | Standard coverage |
| WS (Wide Shot) | Full body + environment | 24-35mm | Context, establishing |
| EWS (Extreme Wide Shot) | Landscape dominant | 14-18mm | Epic scale, hero images |

### Emotion-to-Visual Mapping

| Emotion Category | Expression | Body Language | Lighting |
|---|---|---|---|
| Authority/Confidence | Steady gaze, knowing smile | Arms crossed, composed | Rembrandt 4:1 |
| Curiosity/Mystery | Raised eyebrow, slight lean | Hand on chin, searching | Split 8:1 |
| Excitement/Energy | Wide smile, bright eyes | Dynamic pose, gesturing | Butterfly 2:1 |
| Warmth/Relatability | Genuine smile, soft eyes | Open stance, welcoming | Loop 4:1 |
| Shock/Surprise | Wide eyes, open mouth | Dramatic gesture | Rim 6:1 |

---

## 5. Blog-Specific Image Strategy

### Image Types per Article Section

| Article Section | Image Type | Aspect Ratio | Model Recommendation |
|---|---|---|---|
| **Hero/Featured** | Cinematic wide shot | 16:9 | Seedream v4 ($0.03) or Nano Banana /Edit ($0.08) |
| **Section Breaks** | Thematic illustration | 16:9 or 4:3 | Imagen-pro (FREE) |
| **Concept Visualization** | Abstract/metaphorical | 4:3 | Qwen Image 2 ($0.035) |
| **Step-by-Step** | Process illustration | 4:3 | Imagen-4-Fast ($0.02) |
| **Social Share/OG** | Eye-catching thumbnail | 1.91:1 (1200x630) | DALL-E 3 ($0.08) |
| **Author Avatar** | Consistent portrait | 1:1 | Nano Banana /Edit ($0.08) with reference |

### Image Density Guideline

- **1 hero image** per article (featured image, OG image)
- **1 image per 300-500 words** of body content
- **Minimum 3 images** for articles under 1500 words
- **Minimum 5 images** for articles over 2000 words
- Images break up text walls and improve readability + time-on-page

### Alt Text & SEO Rules

- Every image MUST have descriptive alt text (50-125 characters)
- Include target keyword naturally in hero image alt text
- Use descriptive filenames: `laravel-api-authentication-flow.webp` not `image-001.webp`
- Serve as WebP format for performance (convert after generation)
- Lazy-load all images except hero (above the fold)

### Prompt Templates for Common Blog Topics

**Tech Tutorial Hero:**
```
A photorealistic top-down view of a modern developer workspace with
[SPECIFIC TECH] code visible on a high-res monitor, mechanical keyboard,
coffee cup, and subtle [TECH BRAND] stickers, soft overhead lighting
at 5600K daylight balance, shot on Kodak 250D film stock, shallow
depth of field with tilt-shift miniature effect, clean minimalist
composition with rule of thirds
```

**AI/Machine Learning Concept:**
```
Abstract visualization of [CONCEPT] represented as glowing neural
pathways and data streams in a dark void, cyan and gold color palette,
volumetric lighting with 6500K cool tone, photorealistic 3D render
style, depth of field creating foreground-background separation,
cinematic wide shot at 16:9 aspect ratio
```

**Web Design/UI Topic:**
```
A sleek mockup of [DESCRIPTION] displayed on a floating MacBook Pro
in a minimal studio environment, soft gradient background in deep
charcoal (#0C0C0F), golden hour side lighting at 3500K creating
subtle reflections, shot on Portra 400 film stock, clean product
photography composition with centered framing
```

---

## 6. Cost Optimization

### Budget Tiers

**Tier 1: Free/Minimal ($0/article)**
- Hero: Imagen-pro (FREE, rate limited)
- In-article: FLUX.1-schnell (FREE)
- Limitation: Lower quality, rate limits

**Tier 2: Budget ($0.10-0.15/article)**
- Hero: Seedream v4 ($0.03)
- In-article x3: Imagen-4-Fast ($0.02 each = $0.06)
- OG image: Seedream v4 ($0.03)
- Total: ~$0.12 per article

**Tier 3: Standard ($0.20-0.35/article)**
- Hero: Nano Banana /Edit with reference ($0.08)
- In-article x3: Seedream v4 ($0.03 each = $0.09)
- OG image: DALL-E 3 ($0.08)
- Total: ~$0.25 per article

**Tier 4: Premium ($0.50+/article)**
- Hero: Nano Banana /Edit ($0.08)
- In-article x5: Mix of Seedream + Qwen ($0.03-0.035 each = $0.16)
- OG image: DALL-E 3 ($0.08)
- Author portrait: Nano Banana /Edit ($0.08)
- Total: ~$0.40 per article

### Monthly Cost Estimate (4 articles/month)
- Budget tier: ~$0.50/month
- Standard tier: ~$1.00/month
- Premium tier: ~$1.60/month

---

## 7. Fallback Chain Logic

### When Primary Model Fails

```
CREATOR/Reference Image Chain:
  Nano Banana 2 /Edit ($0.08)
    --> Qwen Image 2 Pro /Edit ($0.075)
    --> GPT-Image-1 ($0.04)
    --> Imagen-pro (FREE)
    --> FLUX.1-schnell (FREE)

Text-to-Image Chain (No Reference):
  Seedream v4 ($0.03)
    --> Qwen Image 2 T2I ($0.035)
    --> Nano Banana 2 T2I ($0.06)
    --> Imagen-4-Fast ($0.02)
    --> FLUX.1-schnell (FREE)
```

### Decision Logic

```
IF article needs person/face with consistency:
  USE Nano Banana 2 /Edit (with reference images)
ELSE IF article needs scene/landscape/abstract:
  USE Seedream v4 (best value T2I)
ELSE IF need to exclude specific elements:
  USE Qwen Image 2 (has negative prompts)
ELSE IF budget is $0:
  USE Imagen-pro (FREE) or FLUX.1-schnell (FREE)
```

---

## 8. Quick Reference Tables

### API Endpoints Summary

| Model | Endpoint |
|---|---|
| Nano Banana /Edit | `POST https://fal.run/fal-ai/nano-banana-2/edit` |
| Nano Banana T2I | `POST https://fal.run/fal-ai/nano-banana-2/text-to-image` |
| Seedream v4 | `POST https://fal.run/fal-ai/bytedance/seedream/v4/text-to-image` |
| Seedream v5 Lite | `POST https://fal.run/fal-ai/bytedance/seedream/v5/lite/text-to-image` |
| Seedream v5 /Edit | `POST https://fal.run/fal-ai/bytedance/seedream/v5/lite/edit` |
| Qwen Image 2 T2I | `POST https://fal.run/fal-ai/qwen-image-2/text-to-image` |
| Qwen Image 2 /Edit | `POST https://fal.run/fal-ai/qwen-image-2/pro/edit` |
| FLUX Kontext Multi | `POST https://fal.run/fal-ai/flux-pro/kontext/max/multi` |
| Imagen-pro | `POST https://api.geminigen.ai/uapi/v1/generate_image` |
| Imagen-4-Fast | `POST https://api.geminigen.ai/uapi/v1/generate_image` |
| Imagen-4-Ultra | `POST https://api.geminigen.ai/uapi/v1/generate_image` |
| DALL-E 3 | `POST https://api.openai.com/v1/images/generations` |
| FLUX.1-schnell | `POST https://router.huggingface.co/hf-inference/models/black-forest-labs/FLUX.1-schnell` |

### Authentication Headers

| Provider | Header |
|---|---|
| FAL.AI | `Authorization: Key {FAL_AI_API_KEY}` |
| GeminiGen | `x-api-key: {GEMINIGEN_API_KEY}` |
| OpenAI | `Authorization: Bearer {OPENAI_API_KEY}` |
| HuggingFace | `Authorization: Bearer {HF_TOKEN}` |

### Env Variables Needed

```env
FAL_AI_API_KEY=
GEMINIGEN_API_KEY=
OPENAI_API_KEY=
HF_TOKEN=
```

---

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

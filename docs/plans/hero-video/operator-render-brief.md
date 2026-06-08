# Hero Video — Operator Render Brief (Task 1)

> The geminigen MCP renders **images** only. This brief is for YOU to render the final
> hero **video loop** on geminigen.ai web (image-to-video / VEO), then drop the files in.
> Until then, `HeroOperator.vue` shows the generated still as the poster over the interim
> `hero-bg.mp4` loop.

## Source still
Use the generated hero poster as the image-to-video seed:
`frontend/public/videos/hero-poster.jpg` (16:9, generated from your face ref
`https://alisadikinma.com/uploads/about/1776545803_creator-face.png`).

## What to render
A **5–8 second seamless loop**, person-forward, that matches the still's identity + lighting.

- **Subject:** you (bald, glasses, navy suit), right third of frame, lower-left kept as dark
  negative space for the wordmark/manifesto overlay.
- **Motion:** ONE slow, premium move — a gentle push-in (≈3–5%) OR a subtle parallax drift.
  No hard cuts, no whip pans, no morphing faces. Micro-motion only (breath, faint haze drift,
  rim-light shimmer). It must loop cleanly (first frame ≈ last frame).
- **Lighting/mood:** deep near-black charcoal background, warm gold rim light from the right,
  subtle cyan fill from the left, volumetric haze, shallow DoF, cinematic AI-founder tone.
- **No text, no logos, no watermark** baked in (overlay lives in the DOM).
- **Duration:** 5–8s loop. **Aspect:** 16:9 master (the section uses object-cover, so a 16:9
  master crops fine on mobile).

## Suggested image-to-video prompt (geminigen.ai web / VEO)
> Slow cinematic push-in on a confident bald man in glasses and a navy suit, dark studio,
> warm gold rim light + subtle cyan fill, volumetric haze, shallow depth of field, premium
> AI-founder mood. Subtle, elegant micro-motion only; seamless loop; no cuts; no text.

## Export + drop-in
Export 3 files into `frontend/public/videos/`:
- `hero-loop.webm` (VP9/AV1 — primary, smallest)
- `hero-loop.mp4` (H.264 — fallback)
- `hero-poster.jpg` (already generated — or re-export the first frame)

Then in `HeroOperator.vue` swap the media bindings:
```js
const posterSrc = '/videos/hero-poster.jpg'
const webmSrc   = '/videos/hero-loop.webm'
const mp4Src    = '/videos/hero-loop.mp4'   // replaces the interim hero-bg.mp4
```
Keep the file sizes lean (target < 6 MB mp4) so the hero stays fast.

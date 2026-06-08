# Hero Video — Person-Forward Brief (SUPERSEDES the abstract Genesis Triptych)

**Owner:** Ali Sadikin Ma · alisadikinma.com
**Status:** Active concept (June 8, 2026). Replaces `end-goal-brief.md` + `image-prompts.md` (the 3-act abstract "Genesis Triptych" — rejected by operator as "jelek sekali, no meaningful at all": pillars rendered as flat 2D containers, Ali's face not meaningfully present).
**Why the pivot:** The homepage redesign ("The Operator") is identity-led. The hero must make a visitor feel *who Ali is* in the first 3 seconds — a real operator who builds, not an abstract metaphor.

---

## 1. What the video must achieve

A **10–15s silent, autoplay-muted, seamlessly-looping** hero background behind the "ALI SADIKIN MA" wordmark + manifesto (text is live HTML in `HeroOperator.vue`, NOT baked into the video). On land:

1. **Stop-scroll in 2s** — feels expensive, cinematic, human. Not AI stock, not a Canva template.
2. **Read "operator who ships" in ~5s** — Ali working: at the keyboard, reviewing output on screens, in a real workspace.
3. **Anchor identity to a real face** — Ali is clearly visible (he IS the subject), so the wordmark over the video reads as *this human*.
4. **Loop clean** — last frame dissolves into the first; no visible cut.

Screenshot test: any frame should look like a still from a premium founder documentary, color-graded Dark Cinema.

---

## 2. Concept (person-forward montage)

A short, intimate montage of Ali in his element. 3–4 micro-shots, ~3–4s each, slow and deliberate (no frantic cuts):

| Shot | ~Time | What we see | Feeling |
|---|---|---|---|
| A | 0–4s | Ali at the keyboard, side/over-shoulder, code + AI output glowing on dark screens; warm gold key light, cyan screen spill | focus, craft |
| B | 4–8s | Slow push-in on Ali's face lit by the monitor as he reviews output, a small nod/decision | judgment, presence |
| C | 8–12s | Wider: the workspace — multiple screens (an agent run, a render, a deploy log), Ali leaning back | command, range |
| D | 12–15s | Quiet beat / hands on keyboard or a confident look to camera; dissolve to black for the loop | the operator |

Text/wordmark is overlaid by the page (lower-left), so keep the **lower-left third calm** and the **right side** carrying visual interest. Leave headroom — the manifesto sits over the bottom.

---

## 3. Visual identity (locked)

Dark Cinema. Void `#050506` / elevated `#0C0C0F` backgrounds; gold `#D4A843`/`#F5A623` key light; cyan `#06B6D4` screen spill; indigo `#5E6AD2` rim. Hyperrealistic, anti-AI-look: real skin texture, natural catchlights, motivated lighting, shallow depth of field, subtle film grain, gentle handheld or slider motion (no robotic zooms). Mood: Villeneuve / premium founder doc.

Mobile dead zones: keep critical subject out of the bottom ~35% (manifesto) and top ~15% (nav).

---

## 4. Production pipeline — who does what

This is a **media task that needs Ali in the loop** (his likeness + a tool that renders video). Three viable routes:

| Route | How | Pros | Cons | Best when |
|---|---|---|---|---|
| **R1 — Real footage (recommended)** | Ali (or a videographer) shoots 30–60s of B-roll at his desk on a phone/mirrorless; color-grade to Dark Cinema; cut to 12s loop | Truly authentic, zero AI-likeness risk, fastest to "feels real" | needs a short shoot + a grade pass | Ali can self-shoot this week |
| **R2 — AI image-to-video** | Generate 3–4 photoreal keyframes of Ali (needs a strong face reference) → drive each through Kling 2.5 / Veo 3.1 / Seedance → stitch | no shoot needed | likeness drift risk (the exact thing that sank the abstract attempt was *generic* faces); needs Ali's face ref + iteration | no time to shoot; have a clean face ref |
| **R3 — Hybrid** | One real anchor clip of Ali + AI-generated screen/B-roll inserts | balances authenticity + polish | most editing effort | want cinematic inserts around real footage |

**My recommendation: R1.** A person-forward hero lives or dies on the face reading as real; a 12s self-shot desk montage + a LUT grade beats any AI render for trust, and it's faster than iterating likeness in R2.

### What I can do autonomously
- ✅ Done: refreshed this brief (person-forward), wired the hero to render **today** via the existing `hero-bg.mp4` as an interim loop (graceful — page no longer waits on missing assets).
- ✅ Can do next if you choose **R2**: generate the 3–4 photoreal keyframes via the geminigen image tool **once you give me a face reference** (or confirm I should pull `settings.about.profile_photo`), plus write per-shot image-to-video prompts in this folder.
- ✅ Can do for any route: write the exact shot list + a `ffmpeg` encode recipe + drop the final files in `frontend/public/videos/` and flip the 3 consts in `HeroOperator.vue`.

### What needs you
- **Pick a route (R1/R2/R3).**
- For R1: the raw footage. For R2: a clean, front-lit face reference (or OK to use the site profile photo).
- Tool access for the video render (Kling/Veo/Seedance) if R2/R3.

---

## 5. Technical delivery spec (any route)

- **Container/codecs:** `hero-loop.mp4` (H.264, yuv420p) **and** `hero-loop.webm` (VP9) — `<source>` order webm→mp4.
- **Poster:** `hero-poster.jpg` — first frame, ~1920×1080, for reduced-motion + first paint. (Interim currently omits poster; the section's dark glow stands in.)
- **Dimensions:** 1920×1080 (16:9) master; `object-cover` crops responsively. Subject framed for safe lower-left text.
- **Length:** 10–15s, true seamless loop (match first/last frame; crossfade if needed).
- **Budget:** target **≤ 3–4 MB** mp4 (current interim `hero-bg.mp4` ≈ 4.6 MB). Autoplay-muted, `playsinline`, `preload="metadata"`.
- **Place in** `frontend/public/videos/` and set in `HeroOperator.vue`:
  ```js
  const posterSrc = '/videos/hero-poster.jpg'
  const webmSrc   = '/videos/hero-loop.webm'
  const mp4Src    = '/videos/hero-loop.mp4'
  ```
- **Encode recipe (reference):**
  ```bash
  # mp4 (H.264)
  ffmpeg -i master.mov -vf "scale=1920:-2,format=yuv420p" -c:v libx264 -crf 24 -preset slow -an -movflags +faststart hero-loop.mp4
  # webm (VP9)
  ffmpeg -i master.mov -vf "scale=1920:-2" -c:v libvpx-vp9 -crf 33 -b:v 0 -an hero-loop.webm
  # poster
  ffmpeg -i master.mov -vf "select=eq(n\,0),scale=1920:-2" -frames:v 1 hero-poster.jpg
  ```

---

## 6. Acceptance

- Loops seamlessly, autoplay-muted, no layout shift, ≤ 4 MB.
- Ali is clearly the subject and reads as real (no uncanny/generic face).
- Lower-left stays calm under the manifesto; legible on mobile + desktop.
- `prefers-reduced-motion` → poster (or dark glow) instead of motion.

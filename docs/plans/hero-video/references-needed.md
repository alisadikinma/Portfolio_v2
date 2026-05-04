# References Needed — Operator Action

**Project:** Hero video Genesis Triptych (with founder reveal)
**Phase:** 4 (3-keyframe approach + face ref, locked 2026-05-04)

---

## External Refs Required: 1

| Asset | Need? | Source URL | Used in |
|---|---|---|---|
| **Ali profile photo** | ✅ REQUIRED | https://alisadikinma.com/uploads/about/1761935897_alisadikin_profile_photo.png | KF-03 face identity lock |
| Outskill logo | ❌ | — | Credential is text-only ("1ST · OUTSKILL DEMO DAY 2026") |
| Trophy photo | ❌ | — | Out of scope (silent text kicker, not visual trophy) |
| Cursor / n8n / VEO screenshots | ❌ | — | Columns use abstract textures, not literal product UI |

---

## Operator Workflow

```
STEP 0 — Download face ref:
  Download https://alisadikinma.com/uploads/about/1761935897_alisadikin_profile_photo.png
  Save locally as: alisadikin_profile_photo.png
  (Place in D:\Projects\Portfolio_v2\docs\plans\hero-video\ref\ for organization,
   but at upload time NB2 just needs the filename)

STEP 1 — Generate keyframes via GeminiGen.AI / Nano Banana 2:

  KF-01: keyframe-01-triptych-split.png
    Upload: none (fresh start)
    Prompt: from image-prompts.md KEYFRAME 1 section
    Settings: CFG 6.5, denoise 0.40, thinking High
    Save to: keyframes/

  KF-02: keyframe-02-orbit-mid.png
    Upload: keyframe-01-triptych-split.png
    Prompt: from image-prompts.md KEYFRAME 2 section
    Settings: CFG 6.5, denoise 0.40, thinking High
    Save to: keyframes/

  KF-03: keyframe-03-founder-reveal.png  ← contains Ali's face
    Upload (BOTH): keyframe-02-orbit-mid.png + alisadikin_profile_photo.png
    Prompt: from image-prompts.md KEYFRAME 3 section
    Settings: CFG 6.5, denoise 0.35 (lower — text + face crisp)
    Save to: keyframes/
    CRITICAL CHECKS:
      ✓ Ali's face matches reference (bald, glasses, ethnic features preserved)
      ✓ NOT a generic AI-generated man (must be recognizably Ali)
      ✓ Kicker text 100% legible character-by-character
      ✓ Wordmark "AI GENERALIST" sharp + positioned ABOVE Ali

STEP 2 — Approve all 3 keyframes, then trigger /video-gen Phase 5
```

**Total effort:** ~6 min generation + ~15 min review = ~21 min wall-clock
**Total cost:** ~free / very low via GeminiGen
**Phase 5 cost:** $0.33-5 depending on chosen render path (see below)

---

## Folder Structure

```
D:\Projects\Portfolio_v2\docs\plans\hero-video\
├── strategic-brief.md           (Phase 1 ✅)
├── cast-profile.md               (Phase 1 ✅)
├── image-prompts.md              (Phase 4 ✅ — 3 keyframes, KF-03 with face)
├── references-needed.md          (this file ✅)
├── ref/
│   └── alisadikin_profile_photo.png   ⏳ operator downloads from VPS
└── keyframes/                    (Phase 4 renders ⏳)
    ├── keyframe-01-triptych-split.png
    ├── keyframe-02-orbit-mid.png
    └── keyframe-03-founder-reveal.png
```

---

## ⚠️ Phase 5 Pipeline Implications

KF-03 contains Ali's real face. Seedance 2.0 has hard ban on real-face inputs (per `seedance_edge_cases §5`).

**3 paths Phase 5 can take — decision deferred until KF-03 is rendered and tested:**

| Path | Pipeline | Cost | Pros | Cons |
|---|---|---|---|---|
| **A** Pure Seedance test | Upload KF-03 to Seedance anyway. If face is stylized enough via NB2 dark-cinema treatment, Seedance might accept | ~$0.33 | Single render, simplest | Likely rejected by safety filter |
| **B** Hybrid Seedance+VEO | Seedance 0-12s abstract + VEO 3.1 12-15s face. Compose in DaVinci Resolve | ~$3-5 | Best motion quality (Seedance for abstract) + face works (VEO allows) | 2 renders + ~30 min compositing |
| **C** Pivot to Kling AI 2.5 | Kling allows faces. Single 15s render with all 3 keyframes | ~$1-2 | Single platform, face works | Less specialized for abstract motion than Seedance |

**Recommended Phase 5 order:**
1. Try Path A first (cheap test) — if Seedance accepts, done
2. If rejected, default to Path B (hybrid) — most premium quality
3. Path C only if hybrid compositing skill not available

Phase 5 (`/video-gen`) handles this decision — operator just needs to render the 3 keyframes via NB2 first.

---

## Why Face Was Added (Decision Log)

Operator request 2026-05-04: *"saya tetap mau ada face saya sebagai personal branding"*. Hero video must include recognizable Ali face for personal-brand attribution. Original abstract Genesis Triptych modified to include founder reveal in KF-03 (final keyframe = Ali portrait + wordmark + kicker).

Trade-off accepted: lost some abstract-purity premium feel, gained direct personal-brand attribution. Section 5 Awards + Section 5.5 Testimonials still carry additional face presence on homepage — total face exposure across homepage now strongly identifies "this is Ali Sadikin Ma".

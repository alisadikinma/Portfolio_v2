---
name: scoring-engine
description: "Content quality scoring system per article section (HOOK, BODY, PEAK, CTA). Point-based evaluation with minimum passing scores. Includes pacing rules and engagement benchmarks. Use for quality assurance."
---

# Scoring Engine & Pacing Rules

## Pacing & Density Rules (Beast-Mozi Layer)

> **Source:** SparkFluence `_shared/prompts/beastMoziLayer.ts` — MrBeast retention + Alex Hormozi value density, adapted for written content.

### Core Principle: "Every Paragraph Earns The Next"

> Just as every second of video must justify the viewer watching the next second, every paragraph of a blog post must justify the reader continuing to the next paragraph. The reader can leave at ANY moment.

### Energy Curve for Blog Sections

```
HOOK:       ████████████████████ 100%  (Maximum energy, grab attention)
FORESHADOW: ████████████         60%  (Slight dip OK — building anticipation)
BODY-1:     ██████████████       70%  (Rising — foundation)
BODY-2:     ████████████████     80%  (Higher — deeper insight)
BODY-3:     ██████████████████   90%  (Highest body — surprising reveal)
PEAK:       ████████████████████ 100%  (Maximum — climax/transformation)
CTA:        ██████████████████   90%  (High — leverage peak momentum)
```

### Value Density Rules (Hormozi for Text)

- **Specificity > Generality:** "Reduced load time from 8.2s to 1.4s" beats "Made it faster"
- **Deletion Test:** If you can delete a paragraph and the article loses nothing specific, delete it.
- **Stakes Escalation:** Each body section must be MORE interesting/surprising than the previous one.
  - Body-1: Interesting foundation
  - Body-2: Surprising insight
  - Body-3: Wild/counterintuitive finding
  - Peak: Mind-blowing transformation or data

### Pattern Interrupt Frequency

- **Visual change every 300 words** (image, code block, table, callout)
- **Bucket brigade every 300-400 words**
- **Open/close loop every 400-600 words**
- **Tone shift** at section boundaries (technical → personal → data → story)

---

## Scoring Checklist (Adapted from SparkFluence Scoring Engine)

> **Source:** SparkFluence `_shared/knowledge/12-scoring-engine.ts` — segment-specific scoring weights adapted for blog sections.

### HOOK Score (Target: 80+/100)

| Signal | Points | How to Check |
|---|---|---|
| Contains question | +12 | Provocative question in first 50 words |
| Contains number/statistic | +15 | Specific data point in hook |
| Contains power word (emotion lexicon) | +10 | At least 1 high-intensity word |
| Uses negative framing | +8 | Warning, loss, mistake, wrong |
| Word density optimal (under 100 words) | +10 | Tight, no filler |
| Under word limit (150 max) | +8 | Concise hook |
| Pattern interrupt element | +7 | Unexpected format, one-word sentence |
| Matches recommended hook category for topic | +5 | See topic mapping table |
| Payoff NOT revealed in hook | +10 | Curiosity gap maintained |
| **Minimum passing score** | **70** | |

### BODY Section Score (Target: 75+/100 per section)

| Signal | Points | How to Check |
|---|---|---|
| Contains pattern interrupt | +15 | Bucket brigade, visual break, question |
| Contains specific detail/data | +12 | Concrete numbers, tool names, version |
| Contains transition to next section | +5 | Forward tease or question bridge |
| Word density optimal | +10 | No filler paragraphs |
| Delivers new value (not rehashing) | +13 | Each section introduces NEW information |
| Stakes higher than previous section | +10 | Escalating interest/surprise |
| **Minimum passing score** | **65** | |

### PEAK Score (Target: 85+/100)

| Signal | Points | How to Check |
|---|---|---|
| Contains emotional climax | +18 | Transformation, big reveal, "aha moment" |
| Contains unexpected twist | +15 | Counterintuitive finding or surprise |
| Contains specific proof | +10 | Data, screenshot, case study result |
| Emotional intensity high | +12 | Multiple power words from emotion lexicon |
| Quotable/screenshot-worthy | +10 | 1-2 sentences that stand alone as valuable |
| **Minimum passing score** | **75** | |

### CTA Score (Target: 80+/100)

| Signal | Points | How to Check |
|---|---|---|
| Clear single action | +12 | ONE thing to do, no ambiguity |
| First-person framing | +10 | "Get my template" beats "Get your template" (+90% CTR) |
| Single focus (not multiple CTAs) | +18 | ONE action = +371% clicks |
| Contains urgency/value word | +8 | Desire-category power word |
| Under word limit (concise) | +10 | Short, punchy, decisive |
| All open loops closed | +15 | Every promise from earlier is resolved |
| **Minimum passing score** | **70** | |

---

## Viral/Engagement Benchmarks (Adapted for Blog)

| Metric | Average | Good | Viral |
|---|---|---|---|
| Scroll depth | 50% | 65% | 80%+ |
| Time on page | 2 min | 4 min | 7+ min |
| Bounce rate | 70% | 45% | 25% |
| Social shares | 5 | 25 | 100+ |
| Return visitors | 5% | 15% | 30%+ |

---

## Complete Article Blueprint with Scoring Integration

```
SECTION          WORD RANGE    KEY ELEMENTS                           SCORE TARGET
──────────────────────────────────────────────────────────────────────────────────
1. HOOK          0-100         Shocking stat / question / micro-story   80+/100
                               Acknowledge reader's problem
                               Promise of value

2. FORESHADOW    100-200       "By the end, you'll know..."            N/A (part of hook)
                               Bullet-point teaser list (3-5 items)
                               Open MAIN loop
                               Direct answer to query (GEO)

3. BODY          200-1800      3-5 escalating H2 sections              75+/100 each
  Section A      200-600       Foundation knowledge + first open loop
  Section B      600-1000      Deeper insight + data + close A, open B
  Section C      1000-1400     Advanced technique + case study
  Section D      1400-1800     Counterintuitive finding → peak

4. PEAK          1800-2100     Biggest insight / transformation         85+/100
                               Quotable, screenshot-worthy content
                               Original data or unique analysis

5. CTA           2100-2400     Close ALL remaining loops                80+/100
                               Bulleted key takeaways (scannable)
                               Single clear call to action
                               FAQ section (2-4 questions)
```

---

## Pre-Publish Quality Gate

### Structure Check
- [ ] HOOK: First 100 words spark curiosity + make a promise
- [ ] FORESHADOW: Words 100-200 preview content + open main loop
- [ ] BODY: 3-5 escalating sections, each more valuable than the last
- [ ] PEAK: Biggest insight is quotable and screenshot-worthy
- [ ] CTA: All open loops closed, single clear action

### Readability Check
- [ ] Flesch-Kincaid grade level 7-9
- [ ] Sentence length varies (5 to 35 words)
- [ ] Paragraphs max 4 sentences
- [ ] No walls of text — visual break every 300 words
- [ ] At least 3 formatting types used (lists, quotes, images, code, tables)

### Engagement Check
- [ ] At least 3 open/close loops throughout
- [ ] Bucket brigade or pattern interrupt every 300-400 words
- [ ] Image/visual every 300 words
- [ ] Statistic/citation every 150-200 words

### SEO Check
- [ ] Primary keyword in H1, first 100 words, and at least 2 H2s
- [ ] Meta title under 60 characters
- [ ] Meta description 140-160 characters
- [ ] Internal links to 3-5 related cluster articles
- [ ] Descriptive image alt text (50-125 chars) with keyword in hero image
- [ ] Descriptive filenames: `topic-keyword.webp` not `image-001.webp`

### GEO Check
- [ ] First 40-60 words directly answer the primary query
- [ ] First 200 words are self-contained and citation-worthy
- [ ] 1-2 quotable standalone passages per section
- [ ] FAQ section with clear Q&A format
- [ ] Article + Person + FAQ schema markup
- [ ] Sources/References section at bottom

### E-E-A-T Check
- [ ] Author bio with credentials, photo, and links
- [ ] Original data, metrics, or case study results
- [ ] "What I'd do differently" or lessons learned section
- [ ] Specific tools, versions, and dates mentioned

### Multi-Language Check (if bilingual)
- [ ] Hreflang tags for EN/ID versions
- [ ] Per-locale keyword optimization (different keywords per language)
- [ ] Adapted (not literally translated) intro/CTA for each language
- [ ] Indonesian version uses code-mixing, "kita" framing, warm tone

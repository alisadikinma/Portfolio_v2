# INDEX — IG + TikTok B2B Best Practice Research (2026-05-07)

Research for `social-short-form-writer` plugin RAG seeding. 10 files covering Instagram + TikTok caption/hashtag/hook/storytelling/posting-time rules for B2B tech (AI engineering, automation, vibe coding) carousel reposts.

---

## Files

| File | Topic | Most Actionable Rule |
|---|---|---|
| 01-instagram-caption-format.md | Caption length, structure, CTA | Hook in first 125 chars; specific save CTA at end |
| 02-instagram-hashtag-strategy.md | Instagram hashtag limits | Hard 5-tag cap since Dec 2025; 3–5 niche tags only |
| 03-instagram-hook-patterns.md | First line patterns for B2B | Contrarian claim + specific number outperforms all |
| 04-instagram-carousel-storytelling.md | Slide structure, arc, last CTA | 7–10 slides; each swipe is a ranking signal |
| 05-tiktok-caption-format.md | TikTok caption length + keywords | 150–300 chars; keyword in first 150 chars for search |
| 06-tiktok-hashtag-strategy.md | TikTok hashtag pyramid | 3–5 tags; niche > broad; #fyp is a confirmed no-op |
| 07-tiktok-hook-patterns-photo-mode.md | TikTok Photo Mode hook | 1.5-second hook window; first slide = thumbnail + hook |
| 08-tiktok-music-suggestion-language.md | Music descriptor vocabulary | B2B default: lofi hip-hop / focused / slow-medium |
| 09-cross-platform-posting-time-b2b-tech.md | Posting times (WIB, UTC+7) | Tue–Thu 07:00–09:00 WIB primary; TikTok adds 20:00–22:00 |
| 10-anti-patterns-both-platforms.md | What tanks reach | TikTok watermark on IG = hard penalty; links in caption = suppressed |

---

## Top 5 Key Findings for Plugin Design

1. **Instagram enforced a 5-hashtag hard cap in December 2025.** Not a recommendation — a platform constraint. Any plugin output with >5 hashtags will be automatically truncated or rejected. Use 3–4 hashtags for B2B.

2. **TikTok Photo Mode completion rate = Instagram's dwell time.** Both platforms reward the same behavior (viewer staying engaged through all slides), but TikTok reads it as a "completed view" with the same weight as a 100% video watch. Every slide must earn the next swipe.

3. **TikTok's caption is a search index, not just a description.** In 2026, TikTok in-app search indexes full caption text. Keyword placement in the first 150 characters drives discovery reach, often more than hashtags. This is a 2025–2026 shift worth explicitly encoding in the plugin's caption-generation instructions.

4. **Cross-platform hook compression required.** The same LinkedIn caption hook (~800 chars) must be compressed to <125 chars for Instagram and further compressed to <8 words for TikTok slide 1. The plugin must produce platform-specific hook variants, not one hook for all.

5. **Saves are the primary engagement signal on both platforms for B2B carousels.** DM shares count 3–4x a like on Instagram; saves signal durable value to both algorithms. Every carousel should engineer at least one "save-worthy" slide (checklist, comparison table, step-by-step framework).

---

## Source Quality Assessment

High-confidence claims (confirmed by official platform sources or multiple Tier-1 analytics platforms):
- Instagram 5-hashtag hard cap (Official IG @creators Threads post + Social Media Today)
- Saves and DM shares outweigh likes (Sprout Social, Later, Buffer — all 2026)
- TikTok #fyp has no algorithmic boost (TikTok official creator guidelines)

Lower-confidence claims (single marketing blog only, treat as directional):
- 1.5-second TikTok hook window (single source: instacarousel.com — directional but unverified by platform)
- Specific engagement multipliers (e.g., "DM shares count 3–4x a like") — directional, not official

---

## Scope Limitations

- No B2B-specific Instagram data from Meta's official Business API or Meta Ads Manager benchmark reports — only third-party analytics platforms
- TikTok Photo Mode B2B-specific data is sparse; most TikTok carousel research covers consumer/lifestyle content; rules inferred from general carousel performance + algorithm behavior
- Indonesia-specific audience data is directional; actual optimal times for @alisadikinma's specific follower distribution require 30-day native analytics validation

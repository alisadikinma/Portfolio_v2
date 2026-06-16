# Eval contract — `video_full` non-deterministic steps

Success contract for the LLM-driven steps of the `video_full` pipeline. Run by
`gaspol-verify`. Deterministic unit tests (`video-full-worker/__tests__/`) cover
the pure logic; these evals cover model output quality.

Reference reel: `https://www.instagram.com/p/DZmqSoRKOQ9/` (vaibhavsisinty, talking-head + b-roll + split-screen).

## E1 — Segment classification (`segment.js::classifySpans`)
**Capability:** each span correctly labeled `to_camera` / `b_roll` / `split_screen`.
**Fixtures (to capture once the reel is downloaded — pending real-reel run):**
- A frame where the creator's face fills the frame → `to_camera`.
- A pure screen-recording / graphic frame → `b_roll`.
- A frame split top/bottom (b-roll top, face bottom) → `split_screen`.
**Pass:** ≥ 90% of hand-labeled spans match. Unknown/garbled output must degrade to
`b_roll` (asserted by `parseClassifyResponse` unit test) — never crash the pipeline.

## E2 — EN→ID translation (`translate.js::translateSpans`)
**Capability:** natural conversational Indonesian; brand/technical names kept in
English; "(no speech)" → empty string; length roughly matches source timing.
**Fixtures (pending real-reel run):** 3–5 representative transcript lines + their
acceptable Indonesian renderings.
**Pass:** fluent ID (no literal/word-salad), names preserved, no empty-for-spoken or
text-for-silent mismatches.

## Status
- [x] Pure-logic guards shipped (parse/normalize for both steps) — unit-tested.
- [ ] Real-reel fixtures — BLOCKED on Phase-A validation run (needs Whisper model
      download + Instagram network + authenticated `claude` CLI). Capture the actual
      frames + transcript lines here after the first end-to-end run.

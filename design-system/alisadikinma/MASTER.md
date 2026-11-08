# Design System Master — alisadikinma.com (PF-2)

> When building a page, check `pages/<page>.md` first; its rules override this file.

Process (per `~/.claude/CLAUDE.md`): ui-ux-pro-max (research + UX/a11y rules) → frontend-design
(final visual decisions, this file) → gsap-core (motion code).

## What frontend-design changed from the ui-ux-pro-max draft, and why

The database draft returned Brutalism, Caveat/Quicksand, a light zinc palette and a blue CTA.
All of it is replaced: the brief pins the INDUSIA.ai brand (dark navy, teal CTA, gold highlight,
Space Grotesk / Inter / JetBrains Mono). Brand > frontend-design > ui-ux-pro-max for colour, type
and layout. What is KEPT from ui-ux-pro-max: every UX/a11y rule below (contrast, form labels,
error summary, focus-not-obscured, reduced motion, one hero animation, Astro islands + `<Image>`).

## Subject, audience, job

- Subject: Ali Sadikin Ma, pakar AI Indonesia / AI consultant Indonesia, founder INDUSIA.ai.
- Audience: Indonesian company leaders (ops, HR/L&D, event organisers) deciding who to call.
- Job: make "who is this and why trust him" answerable in five seconds, then route to one of three
  actions: consultation (WhatsApp), training (waitlist), speaker (WhatsApp).

## The one memorable thing

**The hero scans Ali the way INDUSIA's machine-vision hero scans a PCB.** Ali's portrait sits in a
camera viewport (corner brackets, live HUD strip). On load a scan line sweeps once and detection
boxes lock onto proof, each with a small label:
`Juara 1 · Global AI Demo Day 2026`, `56+ produk dibangun`, `17 tahun`, `16 negara`.
Same visual language as indusia.ai (family), but the subject is the person (personal brand).
Everything else on the page is quiet.

## Colour (locked by brand — `tokens.css` from Indusia-ai-web)

| Token | Hex | Use |
|---|---|---|
| `--bg-deep` | #0a1220 | page |
| `--bg-surface` | #0e1828 | alternate sections |
| `--bg-elevated` | #16243a | forms, raised panels |
| `--text-primary` | #e8eef8 | headings, body |
| `--text-secondary` | #9fb0c7 | supporting text, small labels |
| `--accent-primary` | #3fb8c5 | the ONLY CTA / link / active / focus colour |
| `--accent-gold` | #d4a24d | proof only: numbers, awards, detection labels for wins |

Measured contrast (WCAG): text-primary/deep 16.1, text-secondary/deep 8.5, teal/deep 7.9,
on-accent/teal 7.9, gold/deep 8.1. **`--text-tertiary` #67788f = 4.16 on deep, 3.46 on elevated →
never for body or small text**; decorative or ≥24px only.

## Type

- Space Grotesk 600 — headings. Tight tracking (-0.02em) at display sizes. The H1 is set large and
  left-aligned; the name and the keyword sit on separate lines so both read at a glance.
- Inter 400/500 — body, 17px / 1.6, measure ≤ 68ch.
- JetBrains Mono 500 — only for machine-vision data: detection labels, HUD strip, numbers.
  Sentence case. Not used for section eyebrows.
- Scale (px): 14 · 17 · 21 · 26 · 33 · 44 · 64 (hero H1 clamps 40→64).

## Layout

- 12-col, max 1200px, 24px gutter, 16px side padding on mobile. Left-aligned text everywhere.
- Hero: text left (5 cols), camera viewport right (7 cols); stacks text-first on mobile.
- Services: three full-width rows (not identical cards), each row = what it is + who it is for +
  its own CTA verb. No numbering (not a sequence).
- Sections separated by background shift (deep ↔ surface), not hairline rules or shadows.
- Radius: 10px controls, 18px panels. Shadow only on the hero viewport.

## Anti-defaults (checked)

- No all-caps tracked eyebrow above every heading; headings stand alone.
- No single-word colour accent in headlines.
- No "→" appended to button text; verbs say what happens ("Chat via WhatsApp", "Daftar waitlist").
- No middle-dot meta strings in body copy (the detection labels use `·` because that is the HUD
  vocabulary indusia.ai already uses — confined to the viewport).
- No fade-up on every section. Motion = the hero scan + feedback on user actions only.

## UX / a11y rules (from ui-ux-pro-max — these win over style)

- Forms: visible `<label>` per field, validate on blur, error text under the field, error summary
  at top focused after a failed submit, button disabled + "Mengirim…" while sending, on-screen
  success state.
- `scroll-padding-top` = header height so a sticky nav never hides focus or anchored headings.
- Touch targets ≥ 44px; visible teal focus ring (2px + 2px offset).
- `prefers-reduced-motion`: hero renders in its final state (boxes visible, no sweep).
- Astro: static HTML by default, JS only for the hero scan and form islands; `<Image>` for photos.

## Open asset note

Current portrait (`/uploads/about/…creator-face.png`, 2048², light grey studio background) works
inside the viewport. A darker-background portrait would sit better on navy — optional later.

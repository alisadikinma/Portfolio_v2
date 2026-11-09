import type { Lang } from "./i18n";
import { STAGE_AWARDS } from "./career";

type L = Record<Lang, string>;

/**
 * Events where Ali PRESENTED. Facts only; a field with no confirmed source is left out, never guessed.
 * Google Startup Grind is NOT here: Ali only attended it (award page marks him "peserta").
 * Sources (all already in the repo):
 *  - Global AI Demo Day: award text (Outskill, Bengaluru, India, 17 Jan 2026, 1st place) + copy stages.demoDayDesc (Sparkfluence).
 *  - UN-UNCTAD × Alibaba eFounders: copy stages.unctadYear "2019 · Tiongkok", alt "Hangzhou"; mockup "Fellowship, Hangzhou, Tiongkok".
 *  - Telkomsel NextDev: copy stages.nextdev* ("2018 · Indonesia", "Juara 1").
 *  - Fenox Startup World Cup: copy stages.fenox* ("2017 · Indonesia", "Juara 1 Wild Card").
 */
export interface Session {
  event: string;
  /** award row id; the slug is looked up from /api/awards */
  awardId: number;
  when: L;
  place: L;
  result: L;
  topic?: L;
}

export const SESSIONS: Session[] = [
  {
    event: "Global AI Demo Day",
    awardId: STAGE_AWARDS.demoDay,
    when: { id: "17 Januari 2026", en: "17 January 2026" },
    place: { id: "Bengaluru, India", en: "Bengaluru, India" },
    result: { id: "Juara 1", en: "1st place" },
    topic: { id: "Sparkfluence, platform konten AI", en: "Sparkfluence, an AI content platform" },
  },
  {
    event: "UN-UNCTAD × Alibaba eFounders",
    awardId: STAGE_AWARDS.unctad,
    when: { id: "2019", en: "2019" },
    place: { id: "Hangzhou, Tiongkok", en: "Hangzhou, China" },
    result: { id: "Fellowship", en: "Fellowship" },
  },
  {
    event: "Telkomsel NextDev",
    awardId: STAGE_AWARDS.nextdev,
    when: { id: "2018", en: "2018" },
    place: { id: "Indonesia", en: "Indonesia" },
    result: { id: "Juara 1", en: "1st place" },
  },
  {
    event: "Fenox Startup World Cup",
    awardId: STAGE_AWARDS.fenox,
    when: { id: "2017", en: "2017" },
    place: { id: "Indonesia", en: "Indonesia" },
    result: { id: "Juara 1 Wild Card", en: "1st place, Wild Card" },
  },
];

/**
 * Pure, deterministic merge of the three timeline signals into one ordered
 * segment manifest — the contract every downstream worker module consumes.
 *
 * Inputs:
 *   sceneCuts      sorted boundary seconds INCLUDING 0 (start) and the video end.
 *                  N boundaries → N-1 consecutive spans.
 *   words          Whisper words [{ word, start, end }] (English ASR).
 *   classifications index-aligned to spans: classifications[i] = { type, ... }
 *                  where type ∈ 'to_camera' | 'b_roll' | 'split_screen'.
 *   translations   index-aligned Indonesian text per span (may be sparse).
 *
 * Output: [{ index, start, end, duration, type, sourceTextEn, textId, words }]
 * A word belongs to the span whose [start,end) window contains the word midpoint
 * (the final span is inclusive of the end boundary).
 */
export function buildSegmentManifest({
  sceneCuts,
  words = [],
  classifications = [],
  translations = [],
} = {}) {
  if (!Array.isArray(sceneCuts) || sceneCuts.length < 2) {
    return [];
  }
  const cuts = [...sceneCuts].sort((a, b) => a - b);
  const lastSpan = cuts.length - 2;
  const segments = [];

  for (let i = 0; i <= lastSpan; i++) {
    const start = cuts[i];
    const end = cuts[i + 1];
    const spanWords = words.filter((w) => {
      const mid = (w.start + w.end) / 2;
      return mid >= start && (i === lastSpan ? mid <= end : mid < end);
    });
    const cls = classifications[i] || {};
    segments.push({
      index: i,
      start,
      end,
      duration: end - start,
      type: cls.type ?? 'b_roll',
      sourceTextEn: spanWords.map((w) => w.word).join(' '),
      textId: translations[i] ?? '',
      words: spanWords,
    });
  }

  return segments;
}

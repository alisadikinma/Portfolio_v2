import { run, extractJson } from './lib/run.js';

/** Pure: build the EN→ID translation prompt (testable without the LLM). */
export function buildTranslatePrompt(spanTexts) {
  const items = spanTexts.map((t, i) => `${i}: ${t || '(no speech)'}`);
  return [
    'Translate each numbered English line of a tech/AI Instagram reel into natural,',
    'conversational Indonesian (gaya santai, untuk audiens profesional AI Indonesia).',
    'Keep brand/product/technical names in English. Match the speaking length so it',
    'fits the same on-screen timing. A "(no speech)" line translates to an empty string.',
    '',
    'Lines:',
    ...items,
    '',
    `Reply with ONLY a JSON array of ${spanTexts.length} objects, index-aligned,`,
    'each {"index":N,"id":"<Indonesian text>"}. No prose, no code fences.',
  ].join('\n');
}

/** Pure: normalize the LLM response into an index-aligned Indonesian string array. */
export function parseTranslateResponse(text, count) {
  const arr = extractJson(text);
  if (!Array.isArray(arr)) throw new Error('translate: expected a JSON array');
  const byIndex = new Map(arr.map((o) => [o.index, o.id]));
  const out = [];
  for (let i = 0; i < count; i++) {
    const v = byIndex.get(i);
    out.push(typeof v === 'string' ? v : '');
  }
  return out;
}

/**
 * Translate per-span English text to Indonesian via the claude CLI.
 * Returns an index-aligned array of Indonesian strings (empty for no-speech spans).
 */
export async function translateSpans(spanTexts, { claudeBin = 'claude', model = 'sonnet' } = {}) {
  const prompt = buildTranslatePrompt(spanTexts);
  const { stdout } = await run(claudeBin, ['-p', prompt, '--model', model]);
  return parseTranslateResponse(stdout, spanTexts.length);
}

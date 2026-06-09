// Pure tier classifier for the admin Scheduler tab.
//
// The scheduled_commands table mixes 3 audiences: rows the operator actually
// tunes, self-healing internals they should never touch, and locked placeholders
// for not-yet-shipped platforms. This module is the single source of truth for
// which bucket a row falls into — SchedulerSettings.vue renders operator rows
// expanded and collapses the rest into accordions.
//
// Frontend display concern only — no scheduled_commands schema change.

/**
 * The ~8 schedules the operator legitimately retimes or runs on demand.
 * Everything NOT in this set (and not a placeholder) is treated as a
 * self-healing internal and hidden behind a collapsed accordion.
 *
 * MAINTENANCE: when a new operator-tunable command ships, add its signature
 * here — otherwise it silently classifies as 'system' (collapsed). The size
 * assertion in schedulerTiers.test.mjs will fail as a forcing reminder.
 */
export const OPERATOR_SIGNATURES = new Set([
  'content:auto-pipeline',
  'content:pull-trending-daily',
  'content:flag-stale-posts',
  'social:publish-slot',
  'linkedin:scan-blog',
  'linkedin:auto-schedule',
  'social-cross-post:scan',
  'newsletter:send-weekly',
])

/**
 * Classify a scheduled-command row into a display tier.
 *
 * @param {string} signature   e.g. 'linkedin:scan-blog'
 * @param {boolean} isPlaceholder  the row's is_placeholder flag
 * @returns {'operator'|'system'|'placeholder'}
 */
export function classifyTier(signature, isPlaceholder) {
  if (isPlaceholder) return 'placeholder'
  if (OPERATOR_SIGNATURES.has(signature)) return 'operator'
  return 'system'
}

import { spawn } from 'node:child_process';

/**
 * Spawn a command and resolve with { stdout, stderr, code }. Never uses a shell
 * (args passed as an array) — safe against injection from URLs/filenames.
 * Rejects on non-zero exit unless opts.allowNonZero.
 */
export function run(cmd, args = [], opts = {}) {
  return new Promise((resolve, reject) => {
    const child = spawn(cmd, args, { stdio: ['ignore', 'pipe', 'pipe'], ...opts });
    let stdout = '';
    let stderr = '';
    child.stdout.on('data', (d) => (stdout += d));
    child.stderr.on('data', (d) => (stderr += d));
    child.on('error', reject);
    child.on('close', (code) => {
      if (code !== 0 && !opts.allowNonZero) {
        reject(new Error(`${cmd} exited ${code}: ${stderr.trim().slice(0, 500)}`));
        return;
      }
      resolve({ stdout, stderr, code });
    });
  });
}

/**
 * Extract the first balanced JSON value (object or array) from a string that may
 * carry preamble narration and/or trailing ```fences — mirrors the backend's
 * balanced-brace scanner (LinkedInGenerationService::parseOrchestratorOutput).
 */
export function extractJson(text) {
  const open = text.search(/[[{]/);
  if (open === -1) throw new Error('no JSON found in output');
  const opener = text[open];
  const closer = opener === '{' ? '}' : ']';
  let depth = 0;
  let inStr = false;
  let esc = false;
  for (let i = open; i < text.length; i++) {
    const c = text[i];
    if (inStr) {
      if (esc) esc = false;
      else if (c === '\\') esc = true;
      else if (c === '"') inStr = false;
      continue;
    }
    if (c === '"') inStr = true;
    else if (c === opener) depth++;
    else if (c === closer) {
      depth--;
      if (depth === 0) return JSON.parse(text.slice(open, i + 1));
    }
  }
  throw new Error('unbalanced JSON in output');
}

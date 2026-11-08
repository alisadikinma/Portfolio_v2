/**
 * Journey board: one chapter at a time (Ali 2026-11-09). The colour video shows only through the
 * active island's window; the rest of the board is the still image in grey. The timeline fills up
 * to the active dot, then the cycle starts over at 2008.
 */
export const STEP_MS = 4500;
export const STEPS = 4;

/** Next chapter, 1-based, wrapping 4 -> 1. */
export function nextStep(step: number, total = STEPS): number {
  return step >= total ? 1 : step + 1;
}

export function initJourneyCycle(doc: Document = document): void {
  const board = doc.querySelector<HTMLElement>(".jr-board");
  if (!board) return;
  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;

  let step = 1;
  let timer: number | undefined;
  let inView = false;
  let held = false;
  const show = (s: number) => { step = s; board.dataset.step = String(s); };
  const stop = () => { if (timer !== undefined) { clearInterval(timer); timer = undefined; } };
  const start = () => {
    stop();
    if (inView && !held) timer = window.setInterval(() => show(nextStep(step)), STEP_MS);
  };

  board.classList.add("is-cycling");
  show(1);

  // Pointing at an island or a chapter jumps to it and holds there.
  for (let i = 1; i <= STEPS; i++) {
    for (const el of board.querySelectorAll<HTMLElement>(`.jr-spot.s${i}, .journey .c${i}`)) {
      el.addEventListener("pointerenter", () => { held = true; stop(); show(i); });
      el.addEventListener("pointerleave", () => { held = false; start(); });
      el.addEventListener("focusin", () => { held = true; stop(); show(i); });
      el.addEventListener("focusout", () => { held = false; start(); });
    }
  }

  if ("IntersectionObserver" in window) {
    new IntersectionObserver(([e]) => { inView = e.isIntersecting; if (inView) start(); else stop(); }, { threshold: 0.25 }).observe(board);
  } else {
    inView = true;
    start();
  }
}

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

/** 0-based index of the card whose start (px) is closest to scrollLeft. */
export function nearestIndex(lefts: number[], scrollLeft: number): number {
  let best = 0;
  for (let i = 1; i < lefts.length; i++)
    if (Math.abs(lefts[i] - scrollLeft) < Math.abs(lefts[best] - scrollLeft)) best = i;
  return best;
}

export function initJourneyCycle(doc: Document = document): void {
  const board = doc.querySelector<HTMLElement>(".jr-board");
  if (!board) return;
  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;

  let step = 1;
  let timer: number | undefined;
  let inView = false;
  let held = false;
  // Phones: the chapter cards are a swipe carousel (.journey). It follows the timeline, and a
  // swipe moves the timeline (Ali 2026-11-09). On desktop the list does not scroll, so this is a no-op.
  const list = board.querySelector<HTMLElement>(".journey");
  const cards = list ? Array.from(list.children) as HTMLElement[] : [];
  const lefts = () => cards.map((c) => c.offsetLeft - cards[0].offsetLeft);
  const isCarousel = () => !!list && cards.length > 0 && list.scrollWidth > list.clientWidth + 1;
  const show = (s: number, fromUser = false) => {
    step = s;
    board.dataset.step = String(s);
    if (!fromUser && isCarousel()) list!.scrollTo({ left: lefts()[s - 1] ?? 0, behavior: "smooth" });
  };
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
      el.addEventListener("pointerenter", () => { held = true; stop(); show(i, true); });
      el.addEventListener("pointerleave", () => { held = false; start(); });
      el.addEventListener("focusin", () => { held = true; stop(); show(i, true); });
      el.addEventListener("focusout", () => { held = false; start(); });
    }
  }

  // A swipe settles on a card: make that the active chapter and restart the clock from there.
  // Our own smooth scroll also lands here, on the card that is already active, so it is a no-op.
  if (list) {
    let settle: number | undefined;
    list.addEventListener("scroll", () => {
      clearTimeout(settle);
      settle = window.setTimeout(() => {
        if (!isCarousel()) return;
        const s = nearestIndex(lefts(), list.scrollLeft) + 1;
        if (s !== step) { show(s, true); start(); }
      }, 140);
    }, { passive: true });
  }

  if ("IntersectionObserver" in window) {
    new IntersectionObserver(([e]) => { inView = e.isIntersecting; if (inView) start(); else stop(); }, { threshold: 0.25 }).observe(board);
  } else {
    inView = true;
    start();
  }
}

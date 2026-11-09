/**
 * Phone carousels (CSS scroll-snap, see components.css "phone carousels"). Touch swipes work on
 * their own; a mouse cannot swipe (Ali 2026-11-09: "saya gak bisa geser ke card berikutnya"), so
 * each carousel gets ‹ › buttons with a "1 / 3" position and can be dragged with the mouse.
 * The controls only show while the list actually scrolls, so desktop grids are untouched.
 */
export const CAROUSELS = ".tms, .th-topics, .sp-topics, ul.stages:has(> .sp-ev), .rows, .journey";
const EPS = 1; // sub-pixel scroll positions

/** scrollLeft of the next (dir 1) or previous (dir -1) card start; stays put at either end. */
export function stepTarget(lefts: number[], scrollLeft: number, dir: 1 | -1): number {
  if (!lefts.length) return scrollLeft;
  if (dir === 1) return lefts.find((l) => l > scrollLeft + EPS) ?? lefts[lefts.length - 1];
  return [...lefts].reverse().find((l) => l < scrollLeft - EPS) ?? lefts[0];
}

/** "2 / 3": the card whose start is closest to scrollLeft. */
export function positionLabel(lefts: number[], scrollLeft: number): string {
  let best = 0;
  for (let i = 1; i < lefts.length; i++)
    if (Math.abs(lefts[i] - scrollLeft) < Math.abs(lefts[best] - scrollLeft)) best = i;
  return `${best + 1} / ${lefts.length}`;
}

/** A clamped block needs "see more" only if it hides more than rounding. */
export function needsClamp(scrollHeight: number, clientHeight: number): boolean {
  return scrollHeight - clientHeight > 2;
}

type Labels = { prev: string; next: string; more: string; less: string };

/** Long card text on phones: clamp it ([data-clamp], CSS .is-clamped) with a toggle. Text stays in the HTML. */
function clampCards(list: HTMLElement, labels: Labels): void {
  for (const el of list.querySelectorAll<HTMLElement>("[data-clamp]")) {
    const full = el.scrollHeight; // natural height, every paragraph shown
    el.classList.add("is-clamped");
    if (!needsClamp(full, el.clientHeight)) { el.classList.remove("is-clamped"); continue; }
    const b = document.createElement("button");
    b.type = "button";
    b.className = "clamp-more";
    b.textContent = labels.more;
    b.setAttribute("aria-expanded", "false");
    el.after(b);
    b.addEventListener("click", () => {
      const open = el.classList.toggle("is-clamped") === false;
      b.textContent = open ? labels.less : labels.more;
      b.setAttribute("aria-expanded", String(open));
    });
  }
}

function enhance(list: HTMLElement, labels: Labels): void {
  const cards = () => Array.from(list.children) as HTMLElement[];
  const lefts = () => { const c = cards(); return c.map((el) => el.offsetLeft - c[0].offsetLeft); };
  const max = () => list.scrollWidth - list.clientWidth;

  const ctl = document.createElement("div");
  ctl.className = "car-ctl";
  ctl.innerHTML =
    `<button type="button" class="car-btn" data-dir="-1" aria-label="${labels.prev}">‹</button>` +
    `<span class="car-pos" aria-hidden="true"></span>` +
    `<button type="button" class="car-btn" data-dir="1" aria-label="${labels.next}">›</button>`;
  list.after(ctl);
  const [prev, next] = Array.from(ctl.querySelectorAll<HTMLButtonElement>(".car-btn"));
  const pos = ctl.querySelector<HTMLElement>(".car-pos")!;

  const sync = () => {
    const scrolls = max() > EPS;
    ctl.hidden = !scrolls;
    if (!scrolls) return;
    const atEnd = list.scrollLeft >= max() - EPS;
    const l = lefts();
    pos.textContent = atEnd ? `${l.length} / ${l.length}` : positionLabel(l, list.scrollLeft);
    prev.disabled = list.scrollLeft <= EPS;
    next.disabled = atEnd;
  };
  ctl.addEventListener("click", (e) => {
    const b = (e.target as HTMLElement).closest<HTMLButtonElement>(".car-btn");
    if (!b) return;
    const dir = Number(b.dataset.dir) as 1 | -1;
    list.scrollTo({ left: Math.min(stepTarget(lefts(), list.scrollLeft, dir), max()), behavior: "smooth" });
  });
  list.addEventListener("scroll", () => requestAnimationFrame(sync), { passive: true });
  if (typeof ResizeObserver !== "undefined") new ResizeObserver(sync).observe(list);
  else window.addEventListener("resize", sync);
  sync();
  if (max() > EPS) clampCards(list, labels); // phones only: on desktop the list does not scroll

  // Mouse drag. Touch and pen keep the native swipe. A drag that moved never fires the card's click.
  let startX = 0, startLeft = 0, moved = false, dragging = false;
  list.addEventListener("pointerdown", (e) => {
    if (e.pointerType !== "mouse" || e.button !== 0 || max() <= EPS) return;
    dragging = true; moved = false; startX = e.clientX; startLeft = list.scrollLeft;
  });
  window.addEventListener("pointermove", (e) => {
    if (!dragging) return;
    const dx = e.clientX - startX;
    if (!moved && Math.abs(dx) < 6) return;
    if (!moved) { moved = true; list.classList.add("is-dragging"); }
    list.scrollLeft = startLeft - dx;
  });
  window.addEventListener("pointerup", () => {
    if (!dragging) return;
    dragging = false;
    if (!moved) return;
    list.classList.remove("is-dragging"); // snap comes back and settles on the nearest card
    const l = lefts();
    const near = l[Number(positionLabel(l, list.scrollLeft).split(" / ")[0]) - 1];
    list.scrollTo({ left: Math.min(near, max()), behavior: "smooth" });
  });
  list.addEventListener("click", (e) => { if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; } }, true);
  list.addEventListener("dragstart", (e) => { if (dragging) e.preventDefault(); });
}

export function initCarousels(doc: Document = document): void {
  const en = doc.documentElement.lang === "en";
  const labels: Labels = en
    ? { prev: "Previous", next: "Next", more: "See more", less: "See less" }
    : { prev: "Sebelumnya", next: "Berikutnya", more: "Selengkapnya", less: "Tutup" };
  doc.querySelectorAll<HTMLElement>(CAROUSELS).forEach((l) => enhance(l, labels));
}

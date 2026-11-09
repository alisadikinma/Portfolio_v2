export const ROOT_MARGIN = "-45% 0px -50% 0px";

/** Last id (document order) that is currently visible; null when none (hero = no highlight). */
export function pickActive(visible: Iterable<string>, orderedIds: readonly string[]): string | null {
  const seen = new Set(visible);
  let active: string | null = null;
  for (const id of orderedIds) if (seen.has(id)) active = id;
  return active;
}

export function initScrollspy(doc: Document = document): void {
  const links = Array.from(doc.querySelectorAll<HTMLAnchorElement>(".nav a[data-spy]"));
  const ids = links.map((a) => a.hash.slice(1)).filter(Boolean);
  const sections = ids.map((i) => doc.getElementById(i)).filter((s): s is HTMLElement => s !== null);
  if (sections.length === 0 || !("IntersectionObserver" in window)) return;

  const setActive = (sid: string | null): void => {
    for (const a of links) {
      const on = a.hash.slice(1) === sid;
      a.classList.toggle("is-active", on);
      if (on) a.setAttribute("aria-current", "location");
      else a.removeAttribute("aria-current");
    }
  };

  const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  for (const a of links) {
    a.addEventListener("click", (e) => {
      const target = doc.getElementById(a.hash.slice(1));
      if (!target) return;
      e.preventDefault();
      target.scrollIntoView({ behavior: reduce ? "auto" : "smooth", block: "start" });
      history.replaceState(null, "", a.hash);
    });
  }

  const visible = new Set<string>();
  const ordered = sections.map((s) => s.id);
  const io = new IntersectionObserver(
    (entries) => {
      for (const e of entries) {
        if (e.isIntersecting) visible.add(e.target.id);
        else visible.delete(e.target.id);
      }
      setActive(pickActive(visible, ordered));
    },
    { rootMargin: ROOT_MARGIN },
  );
  sections.forEach((s) => io.observe(s));
}

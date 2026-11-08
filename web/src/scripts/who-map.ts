// "Who I am" career map: the section's one motion moment. When the map scrolls into view the
// path through the four chapters draws itself and the chapter badges settle in, in order.
// Without JS, with reduced motion, or when the map is already on screen at load, the final
// frame is simply shown (nothing is ever hidden that would not be revealed).

/** Animate only when motion is allowed and the map has not been seen yet (below the fold). */
export function shouldAnimate(rect: { top: number }, viewportH: number, reduce: boolean): boolean {
  return !reduce && rect.top > viewportH;
}

export function initWhoMap(doc: Document = document): void {
  const map = doc.querySelector<HTMLElement>("[data-who-map]");
  if (!map) return;
  const win = doc.defaultView ?? window;
  const reduce = typeof win.matchMedia === "function" && win.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (!shouldAnimate(map.getBoundingClientRect(), win.innerHeight, reduce)) return;
  if (!("IntersectionObserver" in win)) return;

  void import("gsap").then(({ gsap }) => {
    // re-check: the user may have scrolled while gsap was loading
    if (!shouldAnimate(map.getBoundingClientRect(), win.innerHeight, reduce)) return;
    const lines = map.querySelectorAll<SVGPathElement>(".wm-line, .wm-glowline");
    const nodes = map.querySelectorAll<SVGGElement>(".wm-node");
    const labels = map.querySelectorAll<HTMLElement>(".wm-ch");

    const mm = gsap.matchMedia();
    mm.add("(prefers-reduced-motion: no-preference)", () => {
      gsap.set(lines, { strokeDasharray: 1, strokeDashoffset: 1 });
      gsap.set(nodes, { autoAlpha: 0, scale: 0.6, transformOrigin: "50% 50%", transformBox: "fill-box" });
      gsap.set(labels, { autoAlpha: 0, y: 8 });
      const tl = gsap.timeline({ paused: true, defaults: { ease: "power2.out" } });
      tl.to(lines, { strokeDashoffset: 0, duration: 1.6, ease: "power1.inOut" }, 0)
        .to(nodes, { autoAlpha: 1, scale: 1, duration: 0.5, stagger: 0.38, ease: "back.out(1.6)" }, 0.05)
        .to(labels, { autoAlpha: 1, y: 0, duration: 0.45, stagger: 0.38 }, 0.2);

      const io = new IntersectionObserver((entries) => {
        if (entries.some((e) => e.isIntersecting)) { tl.play(); io.disconnect(); }
      }, { threshold: 0.35 });
      io.observe(map);
      return () => { io.disconnect(); tl.progress(1).kill(); };
    });
  });
}

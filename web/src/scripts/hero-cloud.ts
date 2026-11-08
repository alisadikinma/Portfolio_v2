// Hero client island: portrait -> AI point cloud (ogl) orchestrated by GSAP.
// Starts after window `load` (so the <img> LCP is already painted), lazy-imports ogl + gsap,
// and degrades silently to the duotone <img> on any failure. Never throws.
import { samplePortrait, pointBudget } from "./portrait-sample";

const VERTEX = /* glsl */ `
  attribute vec2 position;
  attribute vec4 random;
  attribute float lum;
  uniform float uProgress, uTime, uAspect, uScale, uDpr, uPoint;
  uniform vec2 uOffset, uMouse;
  varying float vLum, vIn;
  void main() {
    float d = clamp((uProgress - random.w * 0.45) / 0.55, 0.0, 1.0);
    float e = 1.0 - pow(1.0 - d, 3.0);
    vec2 target = position * uScale + uOffset;
    vec2 scatter = target + (random.xy - 0.5) * vec2(3.2, 2.4);
    vec2 p = mix(scatter, target, e);
    p += vec2(sin(uTime * 0.9 + random.x * 30.0), cos(uTime * 0.8 + random.y * 30.0)) * 0.0018;
    vec2 diff = p - uMouse;
    float dist = length(diff);
    p += normalize(diff + 1e-5) * smoothstep(0.22, 0.0, dist) * 0.07 * e;
    gl_Position = vec4(p.x / uAspect, p.y, 0.0, 1.0);
    gl_PointSize = uPoint * uDpr * (0.95 + lum * 0.5) * (0.75 + random.z * 0.5);
    vLum = lum; vIn = e;
  }`;

const FRAGMENT = /* glsl */ `
  precision highp float;
  varying float vLum, vIn;
  void main() {
    float r = length(gl_PointCoord - 0.5);
    if (r > 0.5) discard;
    float a = smoothstep(0.5, 0.12, r);
    vec3 teal = vec3(0.247, 0.722, 0.773);
    vec3 col = mix(teal * 0.7, vec3(0.93, 0.96, 1.0), smoothstep(0.3, 0.85, vLum));
    gl_FragColor = vec4(col, a * (0.62 + vLum * 0.38) * (0.2 + 0.8 * vIn));
  }`;

interface Layout { aspect: number; scale: number; offset: [number, number] }

/** Where the portrait sits in shader space (y in [-1,1]). */
function layout(w: number, h: number): Layout {
  const aspect = w / h;
  return w < 760
    ? { aspect, scale: 1.05, offset: [0, 0.38] }
    : { aspect, scale: 1.8, offset: [aspect * 0.4, -0.06] };
}

/** Image-space detection boxes (0..1, top-left origin) -> CSS px inside the hero. */
function placeBoxes(dets: HTMLElement[], w: number, h: number, L: Layout): void {
  for (const el of dets) {
    const [u, v, bw, bh] = (el.dataset.box ?? "").split(",").map(Number) as [number, number, number, number];
    const x0 = (u - 0.5) * L.scale + L.offset[0];
    const y0 = (0.5 - v) * L.scale + L.offset[1];
    el.style.left = `${((x0 / L.aspect + 1) / 2) * w}px`;
    el.style.top = `${((1 - y0) / 2) * h}px`;
    el.style.width = `${((bw * L.scale) / L.aspect / 2) * w}px`;
    el.style.height = `${((bh * L.scale) / 2) * h}px`;
  }
}

function hasWebGL(): boolean {
  try {
    const c = document.createElement("canvas");
    return !!(c.getContext("webgl2") ?? c.getContext("webgl"));
  } catch {
    return false;
  }
}

function wireMoreToggle(hero: HTMLElement): void {
  const btn = hero.querySelector<HTMLButtonElement>(".hx-more");
  const lead = hero.querySelector<HTMLElement>("#hero-lead");
  if (!btn || !lead) return;
  btn.addEventListener("click", () => {
    const open = btn.getAttribute("aria-expanded") !== "true";
    btn.setAttribute("aria-expanded", String(open));
    lead.classList.toggle("open", open);
    btn.textContent = (open ? btn.dataset.less : btn.dataset.more) ?? btn.textContent;
  });
}

async function startCloud(hero: HTMLElement): Promise<void> {
  const host = hero.querySelector<HTMLElement>("[data-cloud]");
  const photo = hero.querySelector<HTMLImageElement>(".hx-fallback");
  if (!host || !photo || !hasWebGL()) return; // duotone image stays

  const dets = [...hero.querySelectorAll<HTMLElement>(".det")];
  const status = hero.querySelector<HTMLElement>("[data-status]");
  const counters = [...hero.querySelectorAll<HTMLElement>("[data-count]")];

  const [{ Renderer, Geometry, Program, Mesh }, { gsap }] = await Promise.all([import("ogl"), import("gsap")]);
  if (!photo.complete || photo.naturalWidth === 0) await photo.decode();

  const nav = navigator as Navigator & { deviceMemory?: number };
  const n = pointBudget({ cores: nav.hardwareConcurrency, memory: nav.deviceMemory, width: window.innerWidth });
  const c = document.createElement("canvas");
  c.width = c.height = n;
  const ctx = c.getContext("2d", { willReadFrequently: true });
  if (!ctx) return;
  ctx.drawImage(photo, 0, 0, n, n);
  const s = samplePortrait(ctx.getImageData(0, 0, n, n), n);
  if (s.lum.length === 0) return;

  const renderer = new Renderer({ dpr: Math.min(window.devicePixelRatio, 2), alpha: true, antialias: false });
  const gl = renderer.gl;
  gl.clearColor(0, 0, 0, 0);
  gl.enable(gl.BLEND);
  gl.blendFunc(gl.SRC_ALPHA, gl.ONE);
  host.appendChild(gl.canvas);

  const mobile = window.innerWidth < 760;
  const geometry = new Geometry(gl, {
    position: { size: 2, data: s.pos },
    random: { size: 4, data: s.rnd },
    lum: { size: 1, data: s.lum },
  });
  const uniforms = {
    uProgress: { value: 0 }, uTime: { value: 0 }, uAspect: { value: 1 },
    uScale: { value: 1 }, uOffset: { value: [0, 0] }, uMouse: { value: [9, 9] },
    uDpr: { value: renderer.dpr }, uPoint: { value: mobile ? 2.0 : 2.4 },
  };
  const program = new Program(gl, { vertex: VERTEX, fragment: FRAGMENT, uniforms, transparent: true, depthTest: false });
  const mesh = new Mesh(gl, { mode: gl.POINTS, geometry, program });

  let L = layout(1, 1);
  const draw = () => renderer.render({ scene: mesh });
  const resize = () => {
    const r = hero.getBoundingClientRect();
    renderer.setSize(r.width, r.height);
    L = layout(r.width, r.height);
    uniforms.uAspect.value = L.aspect;
    uniforms.uScale.value = L.scale;
    uniforms.uOffset.value = L.offset;
    placeBoxes(dets, r.width, r.height, L);
    draw();
  };
  window.addEventListener("resize", resize);
  resize();

  const finalState = () => {
    uniforms.uProgress.value = 1;
    gsap.set(dets, { autoAlpha: 1, scale: 1 });
    for (const el of counters) el.textContent = el.dataset.count ?? el.textContent;
    if (status) status.textContent = status.dataset.done ?? status.textContent;
    draw();
  };

  // render only while the hero is on screen and the tab is visible
  let onScreen = true;
  const io = new IntersectionObserver(([en]) => { onScreen = !!en?.isIntersecting; });
  io.observe(hero);

  document.documentElement.classList.add("has-cloud");

  const mm = gsap.matchMedia();
  mm.add(
    { reduce: "(prefers-reduced-motion: reduce)", motion: "(prefers-reduced-motion: no-preference)" },
    (mctx) => {
      if (mctx.conditions?.reduce) {
        finalState(); // no ripple, no ticker, no timeline
        return;
      }
      if (status) status.textContent = status.dataset.busy ?? status.textContent;
      uniforms.uProgress.value = 0;
      const mouse = { x: 9, y: 9 };
      const onMove = (e: PointerEvent) => {
        const r = hero.getBoundingClientRect();
        gsap.to(mouse, {
          x: (((e.clientX - r.left) / r.width) * 2 - 1) * L.aspect,
          y: -(((e.clientY - r.top) / r.height) * 2 - 1),
          duration: 0.6, ease: "power2.out", overwrite: true,
        });
      };
      const onLeave = () => { gsap.to(mouse, { x: 9, y: 9, duration: 0.8, overwrite: true }); };
      hero.addEventListener("pointermove", onMove);
      hero.addEventListener("pointerleave", onLeave);

      const tick = (t: number) => {
        if (!onScreen || document.hidden) return;
        uniforms.uTime.value = t;
        uniforms.uMouse.value = [mouse.x, mouse.y];
        draw();
      };
      gsap.ticker.add(tick);

      const tl = gsap.timeline({ delay: 0.2 });
      tl.to(uniforms.uProgress, { value: 1, duration: 2.8, ease: "power2.inOut" })
        .fromTo(dets, { autoAlpha: 0, scale: 1.3 }, { autoAlpha: 1, scale: 1, duration: 0.5, ease: "back.out(1.7)", stagger: 0.22 }, 2.0);
      for (const el of counters) {
        const o = { v: 0 };
        const target = Number(el.dataset.count);
        tl.to(o, { v: target, duration: 1.6, ease: "power1.out", onUpdate: () => { el.textContent = String(Math.round(o.v)); } }, 1.0);
      }
      tl.call(() => { if (status) status.textContent = status.dataset.done ?? status.textContent; });

      return () => {
        hero.removeEventListener("pointermove", onMove);
        hero.removeEventListener("pointerleave", onLeave);
        gsap.ticker.remove(tick);
        tl.kill();
      };
    },
  );
}

export function initHero(): void {
  const hero = document.querySelector<HTMLElement>("[data-hero]");
  if (!hero) return;
  wireMoreToggle(hero);
  const go = () => {
    startCloud(hero).catch(() => {
      document.documentElement.classList.remove("has-cloud");
    });
  };
  if (document.readyState === "complete") setTimeout(go, 0);
  else window.addEventListener("load", () => setTimeout(go, 0), { once: true });
}

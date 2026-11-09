// PF-2 hero v2 — Ali's portrait as an AI point cloud.
// GSAP drives the one orchestrated moment (assemble -> lock detections -> HUD);
// the shader only does per-point work (position mix, mouse ripple, shimmer).
import { Renderer, Geometry, Program, Mesh } from "https://cdn.jsdelivr.net/npm/ogl@1.0.11/src/index.js";

const hero = document.querySelector("[data-hero]");
const canvasHost = hero.querySelector("[data-cloud]");
const dets = [...hero.querySelectorAll(".det")];
const status = hero.querySelector("[data-status]");
const counters = [...hero.querySelectorAll("[data-count]")];
const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

function finalDom() {
  gsap.set(dets, { autoAlpha: 1, scale: 1 });
  counters.forEach((el) => (el.textContent = el.dataset.count));
  status.textContent = status.dataset.done;
}

// ---------- 1. sample the photo into points (background removed by flood fill) ----------
function samplePortrait(img, n) {
  const c = document.createElement("canvas");
  c.width = c.height = n;
  const ctx = c.getContext("2d", { willReadFrequently: true });
  ctx.drawImage(img, 0, 0, n, n);
  const px = ctx.getImageData(0, 0, n, n).data;
  const bg = [px[0], px[1], px[2]];
  const isBgColor = (i) => Math.hypot(px[i] - bg[0], px[i + 1] - bg[1], px[i + 2] - bg[2]) < 26;
  const seen = new Uint8Array(n * n);
  const stack = [];
  for (let k = 0; k < n; k++) stack.push(k, (n - 1) * n + k, k * n, k * n + n - 1);
  while (stack.length) {
    const p = stack.pop();
    if (seen[p] || !isBgColor(p * 4)) continue;
    seen[p] = 1;
    const x = p % n, y = (p / n) | 0;
    if (x > 0) stack.push(p - 1);
    if (x < n - 1) stack.push(p + 1);
    if (y > 0) stack.push(p - n);
    if (y < n - 1) stack.push(p + n);
  }
  const pos = [], rnd = [], lum = [];
  for (let p = 0; p < n * n; p++) {
    if (seen[p]) continue;
    const i = p * 4;
    const l = (0.2126 * px[i] + 0.7152 * px[i + 1] + 0.0722 * px[i + 2]) / 255;
    const x = p % n, y = (p / n) | 0;
    pos.push(x / n - 0.5 + (Math.random() - 0.5) / n, 0.5 - y / n + (Math.random() - 0.5) / n);
    rnd.push(Math.random(), Math.random(), Math.random(), Math.random());
    lum.push(l);
  }
  const sorted = [...lum].sort((a, b) => a - b);
  const lo = sorted[(sorted.length * 0.03) | 0], hi = sorted[(sorted.length * 0.97) | 0];
  for (let k = 0; k < lum.length; k++) lum[k] = Math.min(1, Math.max(0, (lum[k] - lo) / (hi - lo)));
  return { pos: new Float32Array(pos), rnd: new Float32Array(rnd), lum: new Float32Array(lum) };
}

const vertex = /* glsl */ `
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

const fragment = /* glsl */ `
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

// ---------- 2. layout: where the portrait sits, in shader space (y in [-1,1]) ----------
function layout(w, h) {
  const aspect = w / h;
  const mobile = w < 760;
  return mobile
    ? { aspect, scale: 1.05, offset: [0, 0.38] }
    : { aspect, scale: 1.8, offset: [aspect * 0.4, -0.06] };
}

// image-space box (0..1, top-left origin) -> CSS px inside the hero
function placeBoxes(w, h, L) {
  dets.forEach((el) => {
    const [u, v, bw, bh] = el.dataset.box.split(",").map(Number);
    const x0 = (u - 0.5) * L.scale + L.offset[0], y0 = (0.5 - v) * L.scale + L.offset[1];
    const left = ((x0 / L.aspect + 1) / 2) * w, top = ((1 - y0) / 2) * h;
    el.style.left = left + "px";
    el.style.top = top + "px";
    el.style.width = ((bw * L.scale) / L.aspect / 2) * w + "px";
    el.style.height = ((bh * L.scale) / 2) * h + "px";
  });
}

function start(img) {
  const renderer = new Renderer({ dpr: Math.min(window.devicePixelRatio, 2), alpha: true, antialias: false });
  const gl = renderer.gl;
  gl.clearColor(0, 0, 0, 0);
  gl.enable(gl.BLEND);
  gl.blendFunc(gl.SRC_ALPHA, gl.ONE);
  canvasHost.appendChild(gl.canvas);

  const mobile = window.innerWidth < 760;
  const s = samplePortrait(img, mobile ? 160 : 250);
  const geometry = new Geometry(gl, {
    position: { size: 2, data: s.pos },
    random: { size: 4, data: s.rnd },
    lum: { size: 1, data: s.lum },
  });
  const uniforms = {
    uProgress: { value: reduce ? 1 : 0 }, uTime: { value: 0 }, uAspect: { value: 1 },
    uScale: { value: 1 }, uOffset: { value: [0, 0] }, uMouse: { value: [9, 9] },
    uDpr: { value: renderer.dpr }, uPoint: { value: mobile ? 2.0 : 2.4 },
  };
  const program = new Program(gl, { vertex, fragment, uniforms, transparent: true, depthTest: false });
  const mesh = new Mesh(gl, { mode: gl.POINTS, geometry, program });

  let L;
  function resize() {
    const r = hero.getBoundingClientRect();
    renderer.setSize(r.width, r.height);
    L = layout(r.width, r.height);
    uniforms.uAspect.value = L.aspect;
    uniforms.uScale.value = L.scale;
    uniforms.uOffset.value = L.offset;
    placeBoxes(r.width, r.height, L);
    if (reduce) renderer.render({ scene: mesh });
  }
  window.addEventListener("resize", resize);
  resize();

  if (reduce) { finalDom(); return; }

  // mouse ripple (eased, so the cloud breathes rather than snaps)
  const mouse = { x: 9, y: 9 };
  hero.addEventListener("pointermove", (e) => {
    const r = hero.getBoundingClientRect();
    gsap.to(mouse, {
      x: (((e.clientX - r.left) / r.width) * 2 - 1) * L.aspect,
      y: -(((e.clientY - r.top) / r.height) * 2 - 1),
      duration: 0.6, ease: "power2.out", overwrite: true,
    });
  });
  hero.addEventListener("pointerleave", () => gsap.to(mouse, { x: 9, y: 9, duration: 0.8, overwrite: true }));

  // render only while the hero is on screen
  let visible = true;
  new IntersectionObserver(([en]) => (visible = en.isIntersecting)).observe(hero);
  gsap.ticker.add((t) => {
    if (!visible) return;
    uniforms.uTime.value = t;
    uniforms.uMouse.value = [mouse.x, mouse.y];
    renderer.render({ scene: mesh });
  });

  // the orchestrated moment
  const tl = gsap.timeline({ delay: 0.2 });
  tl.to(uniforms.uProgress, { value: 1, duration: 2.8, ease: "power2.inOut" })
    .fromTo(dets, { autoAlpha: 0, scale: 1.3 }, { autoAlpha: 1, scale: 1, duration: 0.5, ease: "back.out(1.7)", stagger: 0.22 }, 2.0);
  counters.forEach((el) => {
    const o = { v: 0 };
    tl.to(o, { v: +el.dataset.count, duration: 1.6, ease: "power1.out", onUpdate: () => (el.textContent = Math.round(o.v)) }, 1.0);
  });
  tl.call(() => (status.textContent = status.dataset.done));
}

// WebGL or image failure -> the static photo fallback stays visible
const img = new Image();
img.src = hero.dataset.photo;
img.onload = () => {
  try {
    if (!document.createElement("canvas").getContext("webgl")) throw new Error("no webgl");
    document.documentElement.classList.add("has-cloud");
    start(img);
  } catch (e) {
    document.documentElement.classList.remove("has-cloud");
    counters.forEach((el) => (el.textContent = el.dataset.count));
    status.textContent = status.dataset.done;
  }
};

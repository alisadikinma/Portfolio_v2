// @vitest-environment happy-dom
import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";

const HERO = `
<section class="hx" data-hero>
  <div class="hx-cloud" data-cloud></div>
  <img class="hx-fallback" src="/p.jpg" width="1200" height="1200">
  <span class="det" data-box="0.5,0.5,0.2,0.1"><b>a</b></span>
  <span class="det" data-box="bogus"><b>b</b></span>
  <p class="hx-lead" id="hero-lead">lead</p>
  <button type="button" class="hx-more" hidden aria-expanded="false" aria-controls="hero-lead" data-more="More" data-less="Less">More</button>
  <span data-status data-busy="busy" data-done="done">done</span>
  <b data-count="56">56</b>
</section>`;

const flush = async () => { for (let i = 0; i < 10; i++) await new Promise((r) => setTimeout(r, 0)); };

interface Env { loseContext: ReturnType<typeof vi.fn>; tickerAdd: ReturnType<typeof vi.fn>; tickerRemove: ReturnType<typeof vi.fn>; gl: Record<string, unknown>; reduce: boolean; linkOk: boolean }
let env: Env;

function setup(opts: { webgl?: boolean; reduce?: boolean; linkOk?: boolean; ogl?: "ok" | "reject" } = {}) {
  const { webgl = true, reduce = false, linkOk = true, ogl = "ok" } = opts;
  document.body.innerHTML = HERO;
  document.documentElement.classList.remove("has-cloud");
  const photo = document.querySelector<HTMLImageElement>(".hx-fallback")!;
  Object.defineProperty(photo, "complete", { value: true });
  Object.defineProperty(photo, "naturalWidth", { value: 1200 });
  const loseContext = vi.fn();
  const gl: Record<string, unknown> = {
    canvas: document.createElement("canvas"), clearColor() {}, enable() {}, blendFunc() {},
    getExtension: () => ({ loseContext }), getProgramParameter: () => linkOk, LINK_STATUS: 1, POINTS: 0, BLEND: 1, SRC_ALPHA: 2, ONE: 3,
  };
  const tickerAdd = vi.fn(), tickerRemove = vi.fn();
  env = { loseContext, tickerAdd, tickerRemove, gl, reduce, linkOk };
  const grid = new Uint8ClampedArray(160 * 160 * 4).fill(20);
  vi.spyOn(HTMLCanvasElement.prototype, "getContext").mockImplementation(((kind: string) => {
    if (kind === "2d") return { drawImage() {}, getImageData: (_x: number, _y: number, w: number, h: number) => {
      const d = new Uint8ClampedArray(w * h * 4).fill(20);
      for (let y = h / 4; y < (3 * h) / 4; y++) for (let x = w / 4; x < (3 * w) / 4; x++) d.set([200, 200, 200, 255], (y * w + x) * 4);
      void grid; return { data: d, width: w, height: h };
    } };
    return webgl ? gl : null;
  }) as never);
  if (ogl === "reject") vi.doMock("ogl", () => { throw new Error("boom"); });
  else vi.doMock("ogl", () => ({
    Renderer: class { gl = gl; dpr = 1; setSize() {} render() {} },
    Geometry: class {}, Mesh: class {},
    Program: class { program = {}; },
  }));
  vi.doMock("gsap", () => {
    const g = {
      set: vi.fn(), to: vi.fn(), fromTo: vi.fn(),
      ticker: { add: tickerAdd, remove: tickerRemove },
      timeline: () => { const t: Record<string, unknown> = {}; t.to = () => t; t.fromTo = () => t; t.call = () => t; t.kill = () => {}; return t; },
      matchMedia: () => {
        let cleanup: unknown;
        return { add: (_c: unknown, fn: (c: unknown) => unknown) => { cleanup = fn({ conditions: { reduce } }); }, revert: () => { if (typeof cleanup === "function") cleanup(); } };
      },
    };
    return { gsap: g, default: g };
  });
  return import("./hero-cloud");
}

beforeEach(() => { vi.resetModules(); });
afterEach(() => { vi.restoreAllMocks(); vi.doUnmock("ogl"); vi.doUnmock("gsap"); });

describe("wireMoreToggle", () => {
  it("unhides, marks hero js-active, and flips aria-expanded/.open/label", async () => {
    const m = await setup();
    const hero = document.querySelector<HTMLElement>("[data-hero]")!;
    m.wireMoreToggle(hero);
    const btn = hero.querySelector<HTMLButtonElement>(".hx-more")!;
    const lead = hero.querySelector("#hero-lead")!;
    expect(btn.hidden).toBe(false);
    expect(hero.classList.contains("js")).toBe(true);
    btn.click();
    expect(btn.getAttribute("aria-expanded")).toBe("true");
    expect(lead.classList.contains("open")).toBe(true);
    expect(btn.textContent).toBe("Less");
    btn.click();
    expect(btn.getAttribute("aria-expanded")).toBe("false");
    expect(lead.classList.contains("open")).toBe(false);
    expect(btn.textContent).toBe("More");
  });
});

describe("layout / placeBoxes", () => {
  it("760 is mobile, 761 desktop", async () => {
    const m = await setup();
    expect(m.layout(760, 600).scale).toBe(1.05);
    expect(m.layout(761, 600).scale).toBe(1.8);
  });
  it("places a box from image space to CSS px (mobile, 400x800)", async () => {
    const m = await setup();
    const el = document.createElement("span");
    el.dataset.box = "0.25,0.25,0.5,0.5";
    m.placeBoxes([el], 400, 800, m.layout(400, 800));
    // aspect .5, scale 1.05, offset [0,.38]: x0=(.25-.5)*1.05=-.2625 ; y0=(.5-.25)*1.05+.38=.6425
    expect(parseFloat(el.style.left)).toBeCloseTo(((-0.2625 / 0.5 + 1) / 2) * 400, 3);
    expect(parseFloat(el.style.top)).toBeCloseTo(((1 - 0.6425) / 2) * 800, 3);
    expect(parseFloat(el.style.width)).toBeCloseTo(((0.5 * 1.05) / 0.5 / 2) * 400, 3);
    expect(parseFloat(el.style.height)).toBeCloseTo(((0.5 * 1.05) / 2) * 800, 3);
  });
  it("skips missing/malformed data-box", async () => {
    const m = await setup();
    const a = document.createElement("span"), b = document.createElement("span");
    b.dataset.box = "1,2,x,4";
    m.placeBoxes([a, b], 400, 800, m.layout(400, 800));
    expect(a.style.left).toBe("");
    expect(b.style.left).toBe("");
  });
});

describe("initHero", () => {
  it("runs when readyState is already complete", async () => {
    const m = await setup({ reduce: true });
    expect(document.readyState).toBe("complete");
    m.initHero();
    await flush();
    expect(document.documentElement.classList.contains("has-cloud")).toBe(true);
  });

  it("no WebGL: keeps the fallback, never adds has-cloud, never throws, releases probe", async () => {
    const m = await setup({ webgl: false });
    expect(() => m.initHero()).not.toThrow();
    await flush();
    expect(document.documentElement.classList.contains("has-cloud")).toBe(false);
    expect(document.querySelector(".hx-fallback")).not.toBeNull();
    expect(document.querySelector("[data-cloud] canvas")).toBeNull();
  });

  it("releases the probe context when WebGL exists", async () => {
    const m = await setup({ reduce: true });
    m.initHero();
    await flush();
    expect(env.loseContext).toHaveBeenCalled();
  });

  it("rejected import('ogl') leaves no has-cloud and no canvas", async () => {
    const m = await setup({ ogl: "reject" });
    m.initHero();
    await flush();
    expect(document.documentElement.classList.contains("has-cloud")).toBe(false);
    expect(document.querySelector("[data-cloud] canvas")).toBeNull();
  });

  it("shader link failure: cleanup, no has-cloud, no canvas", async () => {
    const m = await setup({ linkOk: false });
    m.initHero();
    await flush();
    expect(document.documentElement.classList.contains("has-cloud")).toBe(false);
    expect(document.querySelector("[data-cloud] canvas")).toBeNull();
  });

  it("reduced motion: final state, canvas present, no ticker", async () => {
    const m = await setup({ reduce: true });
    m.initHero();
    await flush();
    expect(document.documentElement.classList.contains("has-cloud")).toBe(true);
    expect(env.tickerAdd).not.toHaveBeenCalled();
    expect(document.querySelector("[data-status]")!.textContent).toBe("done");
  });

  it("motion: ticker added; context loss tears everything down, final values, boxes hidden", async () => {
    const m = await setup({ reduce: false });
    m.initHero();
    await flush();
    expect(env.tickerAdd).toHaveBeenCalled();
    expect(document.querySelector("[data-cloud] canvas")).not.toBeNull();
    (env.gl.canvas as HTMLCanvasElement).dispatchEvent(new Event("webglcontextlost", { cancelable: true }));
    await flush();
    expect(document.documentElement.classList.contains("has-cloud")).toBe(false);
    expect(document.querySelector("[data-cloud] canvas")).toBeNull();
    expect(env.tickerRemove).toHaveBeenCalled();
    expect(document.querySelector("[data-status]")!.textContent).toBe("done");
    expect(document.querySelector("[data-count]")!.textContent).toBe("56");
    // boxes match the cloud layout, not the photo: hidden after fallback
    expect(document.querySelector<HTMLElement>(".det")!.style.visibility).toBe("hidden");
  });
});

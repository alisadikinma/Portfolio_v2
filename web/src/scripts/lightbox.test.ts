// @vitest-environment happy-dom
import { describe, it, expect, beforeEach } from "vitest";
import { initLightbox } from "./lightbox";

const ITEMS = ["/a.jpg", "/b.jpg", "/c.jpg"];
const html = (items = ITEMS) => `
<button type="button" id="t1" data-lightbox data-lightbox-title="Marlin" data-lightbox-items='${JSON.stringify(items)}' data-lightbox-thumbs='${JSON.stringify(items.map((x) => x.replace(".jpg", "-640.webp")))}'>open</button>
<button type="button" id="t6" data-lightbox data-lightbox-title="NoThumbs" data-lightbox-items='["/p.jpg","/q.jpg"]' data-lightbox-thumbs='["/only-one.webp"]'>nothumbs</button>
<button type="button" id="t7" data-lightbox data-lightbox-title="Page" data-lightbox-start="1" data-lightbox-items='["/a.jpg","/b.jpg","/c.jpg"]'>page thumb</button>
<button type="button" id="t2" data-lightbox data-lightbox-title="Solo" data-lightbox-items='["/solo.jpg"]'>solo</button>
<button type="button" id="t3" data-lightbox data-lightbox-title="Empty" data-lightbox-items='[]'>empty</button>
<button type="button" id="t5" data-lightbox data-lightbox-title="&lt;img src=x onerror=alert(1)&gt;" data-lightbox-items='["/x.jpg"]'>xss</button>
<button type="button" id="t4" data-lightbox data-lightbox-title="Bad" data-lightbox-items='not json'>bad</button>
<dialog class="lb" id="lb" aria-label="Galeri foto" data-alt="{title}, foto {n}" data-photos="{n} foto">
  <header><h2 data-lb-heading></h2><span data-lb-total></span><button type="button" data-lb="close" aria-label="Tutup">x</button></header>
  <ul data-lb-grid hidden></ul>
  <button type="button" data-lb="grid" hidden>Semua foto</button>
  <button type="button" data-lb="prev" aria-label="Sebelumnya">‹</button>
  <figure><p data-lb-error hidden>Foto tidak bisa dimuat</p><figcaption aria-live="polite"><span data-lb-title></span> · <span data-lb-count></span></figcaption></figure>
  <button type="button" data-lb="next" aria-label="Berikutnya">›</button>
</dialog>`;

const $ = <T extends Element>(s: string) => document.querySelector<T>(s)!;
const dialog = () => $<HTMLDialogElement>("#lb");
const count = () => $("[data-lb-count]").textContent;
const img = () => $<HTMLImageElement>("#lb figure img");
const src = () => img().getAttribute("src");
const grid = () => $<HTMLElement>("[data-lb-grid]");
const thumbs = () => [...document.querySelectorAll<HTMLButtonElement>("[data-lb-grid] button")];
/** Open a gallery and pick photo n from the grid (the old single-photo entry point). */
const openAt = (id: string, n = 0) => { $<HTMLButtonElement>(id).click(); thumbs()[n].click(); };
const key = (k: string) => dialog().dispatchEvent(new KeyboardEvent("keydown", { key: k, bubbles: true, cancelable: true }));

let dispose: () => void;
beforeEach(() => {
  document.body.innerHTML = html();
  dispose?.();
  dispose = initLightbox(document);
});

describe("lightbox", () => {
  it("opens on a grid of every photo (Ali 2026-11-08), small thumbs, lazy, with alt", () => {
    $<HTMLButtonElement>("#t1").click();
    expect(dialog().open).toBe(true);
    expect(grid().hidden).toBe(false);
    expect($<HTMLElement>("figure").hidden).toBe(true);
    expect($("[data-lb-heading]").textContent).toBe("Marlin");
    expect($("[data-lb-total]").textContent).toBe("3 foto");
    const imgs = [...grid().querySelectorAll("img")];
    expect(imgs.map((i) => i.getAttribute("src"))).toEqual(["/a-640.webp", "/b-640.webp", "/c-640.webp"]);
    expect(imgs.every((i) => i.loading === "lazy" && i.alt.startsWith("Marlin, foto"))).toBe(true);
    expect(thumbs().every((b) => b.type === "button")).toBe(true);
  });

  it("thumbs fall back to the full image when the thumb list does not match", () => {
    $<HTMLButtonElement>("#t6").click();
    expect([...grid().querySelectorAll("img")].map((i) => i.getAttribute("src"))).toEqual(["/p.jpg", "/q.jpg"]);
  });

  it("clicking a thumb shows that photo large; 'all photos' goes back to the grid and focuses that thumb", () => {
    openAt("#t1", 2);
    expect(grid().hidden).toBe(true);
    expect($<HTMLElement>("figure").hidden).toBe(false);
    expect(src()).toBe("/c.jpg");
    expect(count()).toBe("3 / 3");
    expect($<HTMLButtonElement>('[data-lb="grid"]').hidden).toBe(false);
    $<HTMLButtonElement>('[data-lb="grid"]').click();
    expect(grid().hidden).toBe(false);
    expect(document.activeElement).toBe(thumbs()[2]);
  });

  it("Escape in the large view goes back to the grid; Escape on the grid closes", () => {
    openAt("#t1", 1);
    key("Escape");
    expect(dialog().open).toBe(true);
    expect(grid().hidden).toBe(false);
    key("Escape");
    expect(dialog().open).toBe(false);
  });

  it("data-lightbox-start opens that photo large at once (page thumbnails); 'all photos' still works", () => {
    $<HTMLButtonElement>("#t7").click();
    expect(grid().hidden).toBe(true);
    expect(src()).toBe("/b.jpg");
    expect(count()).toBe("2 / 3");
    $<HTMLButtonElement>('[data-lb="grid"]').click();
    expect(thumbs()).toHaveLength(3);
    expect(document.activeElement).toBe(thumbs()[1]);
  });

  it("a native cancel (browser Escape / Android back) in the large view returns to the grid, on the grid it closes", () => {
    openAt("#t1", 1);
    const cancel = () => { const e = new Event("cancel", { cancelable: true }); dialog().dispatchEvent(e); return e.defaultPrevented; };
    expect(cancel()).toBe(true);
    expect(grid().hidden).toBe(false);
    expect(cancel()).toBe(false);
  });

  it("arrow keys do nothing on the grid", () => {
    $<HTMLButtonElement>("#t1").click();
    key("ArrowRight");
    expect(grid().hidden).toBe(false);
  });

  it("single view: counter 1 / N, title, first image and alt", () => {
    openAt("#t1");
    expect(dialog().open).toBe(true);
    expect(count()).toBe("1 / 3");
    expect($("[data-lb-title]").textContent).toBe("Marlin");
    expect(src()).toBe("/a.jpg");
    expect(img().alt).toBe("Marlin, foto 1");
  });

  it("next and prev wrap around via buttons", () => {
    openAt("#t1");
    $<HTMLButtonElement>('[data-lb="next"]').click();
    expect(count()).toBe("2 / 3");
    expect(src()).toBe("/b.jpg");
    $<HTMLButtonElement>('[data-lb="next"]').click();
    $<HTMLButtonElement>('[data-lb="next"]').click();
    expect(count()).toBe("1 / 3");
    $<HTMLButtonElement>('[data-lb="prev"]').click();
    expect(count()).toBe("3 / 3");
    expect(src()).toBe("/c.jpg");
  });

  it("ArrowRight / ArrowLeft navigate with wrap", () => {
    openAt("#t1");
    key("ArrowLeft");
    expect(count()).toBe("3 / 3");
    key("ArrowRight");
    expect(count()).toBe("1 / 3");
    key("ArrowRight");
    expect(count()).toBe("2 / 3");
  });

  it("loads only the current image (lazy as you navigate)", () => {
    openAt("#t1");
    expect(src()).toBe("/a.jpg");
    key("ArrowRight");
    expect(src()).toBe("/b.jpg");
  });

  it("Escape closes (from the grid)", () => {
    $<HTMLButtonElement>("#t1").click();
    key("Escape");
    expect(dialog().open).toBe(false);
  });

  it("close button closes", () => {
    $<HTMLButtonElement>("#t1").click();
    $<HTMLButtonElement>('[data-lb="close"]').click();
    expect(dialog().open).toBe(false);
  });

  it("backdrop click (target is the dialog) closes; click inside does not", () => {
    openAt("#t1");
    img().click();
    expect(dialog().open).toBe(true);
    const rect = { left: 100, top: 100, right: 500, bottom: 400, width: 400, height: 300, x: 100, y: 100 } as DOMRect;
    dialog().getBoundingClientRect = () => rect;
    const click = (x: number, y: number) => dialog().dispatchEvent(new MouseEvent("click", { bubbles: true, clientX: x, clientY: y }));
    click(300, 200); // inside the dialog box (e.g. figcaption margin): stays open
    expect(dialog().open).toBe(true);
    click(20, 20); // real backdrop
    expect(dialog().open).toBe(false);
  });

  it("returns focus to the trigger on close", () => {
    const t = $<HTMLButtonElement>("#t1");
    t.focus();
    t.click();
    thumbs()[0].click();
    key("Escape");
    key("Escape");
    expect(document.activeElement).toBe(t);
  });

  it("a single photo skips the grid: large at once, 1 / 1, no nav and no 'all photos'", () => {
    $<HTMLButtonElement>("#t2").click();
    expect(grid().hidden).toBe(true);
    expect(count()).toBe("1 / 1");
    expect($<HTMLButtonElement>('[data-lb="grid"]').hidden).toBe(true);
    expect($<HTMLButtonElement>('[data-lb="next"]').hidden).toBe(true);
    expect($<HTMLButtonElement>('[data-lb="prev"]').hidden).toBe(true);
    $<HTMLButtonElement>('[data-lb="close"]').click();
    openAt("#t1");
    expect($<HTMLButtonElement>('[data-lb="next"]').hidden).toBe(false);
  });

  it("ignores triggers with empty or malformed item lists", () => {
    $<HTMLButtonElement>("#t3").click();
    expect(dialog().open).toBe(false);
    $<HTMLButtonElement>("#t4").click();
    expect(dialog().open).toBe(false);
  });

  it("creates the large <img> lazily on first use, once; the grid is rebuilt per gallery", () => {
    expect(document.querySelectorAll("#lb img").length).toBe(0);
    openAt("#t1");
    expect(document.querySelectorAll("#lb figure img").length).toBe(1);
    key("Escape");
    key("Escape");
    $<HTMLButtonElement>("#t2").click();
    expect(document.querySelectorAll("#lb figure img").length).toBe(1);
    $<HTMLButtonElement>('[data-lb="close"]').click();
    $<HTMLButtonElement>("#t6").click();
    expect(thumbs()).toHaveLength(2);
  });

  it("reopening starts on the grid again, even after navigating", () => {
    openAt("#t1");
    key("ArrowRight");
    $<HTMLButtonElement>('[data-lb="close"]').click();
    $<HTMLButtonElement>("#t1").click();
    expect(grid().hidden).toBe(false);
    expect($<HTMLElement>("figure").hidden).toBe(true);
  });

  it("renders an untrusted title as text, never markup", () => {
    $<HTMLButtonElement>("#t5").click();
    expect($("[data-lb-title]").textContent).toBe("<img src=x onerror=alert(1)>");
    expect($("[data-lb-heading]").textContent).toBe("<img src=x onerror=alert(1)>");
    expect(document.querySelectorAll("figcaption img, [data-lb-heading] img").length).toBe(0);
  });

  it("shows an error message when the photo fails to load and hides it on the next one", () => {
    openAt("#t1");
    img().dispatchEvent(new Event("error"));
    expect($<HTMLElement>("[data-lb-error]").hidden).toBe(false);
    key("ArrowRight");
    img().dispatchEvent(new Event("load"));
    expect($<HTMLElement>("[data-lb-error]").hidden).toBe(true);
  });

  it("dispose removes every listener", () => {
    dispose();
    $<HTMLButtonElement>("#t1").click();
    expect(dialog().open).toBe(false);
    dispose = initLightbox(document);
    openAt("#t1");
    expect(dialog().open).toBe(true);
    dispose();
    key("ArrowRight");
    expect(count()).toBe("1 / 3");
    key("Escape");
    expect(dialog().open).toBe(true);
    dialog().close();
  });

  it("falls back to the open attribute when showModal is unavailable", () => {
    (dialog() as unknown as { showModal: unknown }).showModal = undefined;
    $<HTMLButtonElement>("#t1").click();
    expect(dialog().hasAttribute("open")).toBe(true);
    expect(thumbs()).toHaveLength(3);
  });

  it("is a no-op when the dialog is missing", () => {
    document.body.innerHTML = "<p>none</p>";
    expect(() => initLightbox(document)()).not.toThrow();
  });
});

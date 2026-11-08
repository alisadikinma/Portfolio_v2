// @vitest-environment happy-dom
import { describe, it, expect, beforeEach } from "vitest";
import { initLightbox } from "./lightbox";

const ITEMS = ["/a.jpg", "/b.jpg", "/c.jpg"];
const html = (items = ITEMS) => `
<button type="button" id="t1" data-lightbox data-lightbox-title="Marlin" data-lightbox-items='${JSON.stringify(items)}'>open</button>
<button type="button" id="t2" data-lightbox data-lightbox-title="Solo" data-lightbox-items='["/solo.jpg"]'>solo</button>
<button type="button" id="t3" data-lightbox data-lightbox-title="Empty" data-lightbox-items='[]'>empty</button>
<button type="button" id="t5" data-lightbox data-lightbox-title="&lt;img src=x onerror=alert(1)&gt;" data-lightbox-items='["/x.jpg"]'>xss</button>
<button type="button" id="t4" data-lightbox data-lightbox-title="Bad" data-lightbox-items='not json'>bad</button>
<dialog class="lb" id="lb" aria-label="Galeri foto" data-alt="{title}, foto {n}">
  <button type="button" data-lb="close" aria-label="Tutup">x</button>
  <button type="button" data-lb="prev" aria-label="Sebelumnya">‹</button>
  <figure><p data-lb-error hidden>Foto tidak bisa dimuat</p><figcaption aria-live="polite"><span data-lb-title></span> · <span data-lb-count></span></figcaption></figure>
  <button type="button" data-lb="next" aria-label="Berikutnya">›</button>
</dialog>`;

const $ = <T extends Element>(s: string) => document.querySelector<T>(s)!;
const dialog = () => $<HTMLDialogElement>("#lb");
const count = () => $("[data-lb-count]").textContent;
const img = () => $<HTMLImageElement>("#lb figure img");
const src = () => img().getAttribute("src");
const key = (k: string) => dialog().dispatchEvent(new KeyboardEvent("keydown", { key: k, bubbles: true, cancelable: true }));

let dispose: () => void;
beforeEach(() => {
  document.body.innerHTML = html();
  dispose?.();
  dispose = initLightbox(document);
});

describe("lightbox", () => {
  it("opens modal with counter 1 / N, title, first image and alt", () => {
    $<HTMLButtonElement>("#t1").click();
    expect(dialog().open).toBe(true);
    expect(count()).toBe("1 / 3");
    expect($("[data-lb-title]").textContent).toBe("Marlin");
    expect(src()).toBe("/a.jpg");
    expect(img().alt).toBe("Marlin, foto 1");
  });

  it("next and prev wrap around via buttons", () => {
    $<HTMLButtonElement>("#t1").click();
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
    $<HTMLButtonElement>("#t1").click();
    key("ArrowLeft");
    expect(count()).toBe("3 / 3");
    key("ArrowRight");
    expect(count()).toBe("1 / 3");
    key("ArrowRight");
    expect(count()).toBe("2 / 3");
  });

  it("loads only the current image (lazy as you navigate)", () => {
    $<HTMLButtonElement>("#t1").click();
    expect(src()).toBe("/a.jpg");
    key("ArrowRight");
    expect(src()).toBe("/b.jpg");
  });

  it("Escape closes", () => {
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
    $<HTMLButtonElement>("#t1").click();
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
    $<HTMLButtonElement>('[data-lb="next"]').focus();
    key("Escape");
    expect(document.activeElement).toBe(t);
  });

  it("hides nav for a single photo and shows 1 / 1", () => {
    $<HTMLButtonElement>("#t2").click();
    expect(count()).toBe("1 / 1");
    expect($<HTMLButtonElement>('[data-lb="next"]').hidden).toBe(true);
    expect($<HTMLButtonElement>('[data-lb="prev"]').hidden).toBe(true);
    $<HTMLButtonElement>('[data-lb="close"]').click();
    $<HTMLButtonElement>("#t1").click();
    expect($<HTMLButtonElement>('[data-lb="next"]').hidden).toBe(false);
  });

  it("ignores triggers with empty or malformed item lists", () => {
    $<HTMLButtonElement>("#t3").click();
    expect(dialog().open).toBe(false);
    $<HTMLButtonElement>("#t4").click();
    expect(dialog().open).toBe(false);
  });

  it("creates the <img> lazily on first open, once", () => {
    expect(document.querySelectorAll("#lb img").length).toBe(0);
    $<HTMLButtonElement>("#t1").click();
    expect(document.querySelectorAll("#lb img").length).toBe(1);
    key("Escape");
    $<HTMLButtonElement>("#t2").click();
    expect(document.querySelectorAll("#lb img").length).toBe(1);
  });

  it("starts at 1 / N again after reopening, even after navigating", () => {
    $<HTMLButtonElement>("#t1").click();
    key("ArrowRight");
    key("ArrowRight");
    key("Escape");
    $<HTMLButtonElement>("#t1").click();
    expect(count()).toBe("1 / 3");
    expect(src()).toBe("/a.jpg");
  });

  it("renders an untrusted title as text, never markup", () => {
    $<HTMLButtonElement>("#t5").click();
    expect($("[data-lb-title]").textContent).toBe("<img src=x onerror=alert(1)>");
    expect(document.querySelectorAll("figcaption img").length).toBe(0);
  });

  it("shows an error message when the photo fails to load and hides it on the next one", () => {
    $<HTMLButtonElement>("#t1").click();
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
    $<HTMLButtonElement>("#t1").click();
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
    expect(count()).toBe("1 / 3");
  });

  it("is a no-op when the dialog is missing", () => {
    document.body.innerHTML = "<p>none</p>";
    expect(() => initLightbox(document)()).not.toThrow();
  });
});

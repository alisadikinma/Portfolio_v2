// @vitest-environment happy-dom
import { describe, it, expect, beforeEach } from "vitest";
import { initLightbox } from "./lightbox";

const ITEMS = ["/a.jpg", "/b.jpg", "/c.jpg"];
const html = (items = ITEMS) => `
<button type="button" id="t1" data-lightbox data-lightbox-title="Marlin" data-lightbox-items='${JSON.stringify(items)}'>open</button>
<button type="button" id="t2" data-lightbox data-lightbox-title="Solo" data-lightbox-items='["/solo.jpg"]'>solo</button>
<button type="button" id="t3" data-lightbox data-lightbox-title="Empty" data-lightbox-items='[]'>empty</button>
<button type="button" id="t4" data-lightbox data-lightbox-title="Bad" data-lightbox-items='not json'>bad</button>
<dialog class="lb" id="lb" aria-label="Galeri foto" data-alt="{title}, foto {n}">
  <button type="button" data-lb="close" aria-label="Tutup">x</button>
  <button type="button" data-lb="prev" aria-label="Sebelumnya">‹</button>
  <figure><img id="lb-img" alt=""><figcaption aria-live="polite"><span data-lb-title></span> · <span data-lb-count></span></figcaption></figure>
  <button type="button" data-lb="next" aria-label="Berikutnya">›</button>
</dialog>`;

const $ = <T extends Element>(s: string) => document.querySelector<T>(s)!;
const dialog = () => $<HTMLDialogElement>("#lb");
const count = () => $("[data-lb-count]").textContent;
const src = () => $<HTMLImageElement>("#lb-img").getAttribute("src");
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
    expect($<HTMLImageElement>("#lb-img").alt).toBe("Marlin, foto 1");
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
    $<HTMLImageElement>("#lb-img").click();
    expect(dialog().open).toBe(true);
    dialog().click();
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

  it("is a no-op when the dialog is missing", () => {
    document.body.innerHTML = "<p>none</p>";
    expect(() => initLightbox(document)()).not.toThrow();
  });
});

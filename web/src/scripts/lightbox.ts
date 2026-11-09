// Native <dialog> lightbox. Triggers: any element with data-lightbox, data-lightbox-title,
// data-lightbox-items (JSON array of image URLs) and optionally data-lightbox-thumbs (same length,
// small variants for the grid). One dialog (#lb) per page, see Lightbox.astro.
// A gallery opens on a grid of every photo; a click shows that photo large (Ali 2026-11-08).
// Images are created on open, so the server HTML carries none (nothing to load, no gate noise).

function parseItems(raw: string | undefined): string[] {
  if (!raw) return [];
  try {
    const v: unknown = JSON.parse(raw);
    return Array.isArray(v) ? v.filter((x): x is string => typeof x === "string" && x !== "") : [];
  } catch {
    return [];
  }
}

/** Wires the lightbox; returns a disposer. No-op when the page has no dialog. */
export function initLightbox(doc: Document = document): () => void {
  const dialog = doc.getElementById("lb") as HTMLDialogElement | null;
  if (!dialog || dialog.dataset.lbReady === "1") return () => undefined;
  const figure = dialog.querySelector<HTMLElement>("figure");
  const titleEl = dialog.querySelector<HTMLElement>("[data-lb-title]");
  const countEl = dialog.querySelector<HTMLElement>("[data-lb-count]");
  const errorEl = dialog.querySelector<HTMLElement>("[data-lb-error]");
  const prevBtn = dialog.querySelector<HTMLButtonElement>('[data-lb="prev"]');
  const nextBtn = dialog.querySelector<HTMLButtonElement>('[data-lb="next"]');
  const gridBtn = dialog.querySelector<HTMLButtonElement>('[data-lb="grid"]');
  const gridEl = dialog.querySelector<HTMLElement>("[data-lb-grid]");
  const headingEl = dialog.querySelector<HTMLElement>("[data-lb-heading]");
  const totalEl = dialog.querySelector<HTMLElement>("[data-lb-total]");
  const altTpl = dialog.dataset.alt ?? "{title} {n}";
  const photosTpl = dialog.dataset.photos ?? "{n}";
  dialog.dataset.lbReady = "1";

  let img: HTMLImageElement | null = null;
  let list: string[] = [];
  let thumbs: string[] = [];
  let i = 0;
  let inGrid = false;
  let escToGrid = false;
  const altOf = (n: number) => altTpl.replace("{title}", title).replace("{n}", String(n + 1));
  let title = "";
  let trigger: HTMLElement | null = null;

  const ensureImg = (): HTMLImageElement => {
    if (img) return img;
    img = doc.createElement("img");
    img.decoding = "async";
    img.addEventListener("error", () => {
      if (!img?.getAttribute("src")) return;
      img.hidden = true;
      if (errorEl) errorEl.hidden = false;
    });
    img.addEventListener("load", () => {
      if (img) img.hidden = false;
      if (errorEl) errorEl.hidden = true;
    });
    figure?.insertBefore(img, figure.querySelector("figcaption"));
    return img;
  };

  const setMode = (grid: boolean) => {
    inGrid = grid;
    if (gridEl) gridEl.hidden = !grid;
    if (figure) figure.hidden = grid;
    const nav = !grid && list.length > 1;
    if (prevBtn) prevBtn.hidden = !nav;
    if (nextBtn) nextBtn.hidden = !nav;
    if (gridBtn) gridBtn.hidden = !nav;
  };
  const buildGrid = () => {
    if (!gridEl) return;
    gridEl.replaceChildren(
      ...list.map((_, n) => {
        const li = doc.createElement("li");
        const b = doc.createElement("button");
        b.type = "button";
        b.dataset.lbIdx = String(n);
        const im = doc.createElement("img");
        im.loading = "lazy";
        im.decoding = "async";
        im.alt = altOf(n);
        im.setAttribute("src", thumbs[n]);
        b.append(im);
        li.append(b);
        return li;
      }),
    );
  };
  const showGrid = (focus = false) => {
    setMode(true);
    img?.removeAttribute("src");
    if (focus) gridEl?.querySelectorAll<HTMLButtonElement>("button")[i]?.focus({ preventScroll: false });
  };

  const show = () => {
    setMode(false);
    const el = ensureImg();
    el.hidden = false;
    if (errorEl) errorEl.hidden = true;
    el.setAttribute("src", list[i]);
    el.alt = altOf(i);
    if (titleEl) titleEl.textContent = title;
    if (countEl) countEl.textContent = `${i + 1} / ${list.length}`;
  };
  const step = (d: number) => {
    if (inGrid || list.length < 2) return;
    i = (i + d + list.length) % list.length;
    show();
  };

  const onTrigger = (e: Event) => {
    const t = e.target instanceof Element ? e.target.closest<HTMLElement>("[data-lightbox]") : null;
    if (!t || !doc.contains(t)) return;
    const items = parseItems(t.dataset.lightboxItems);
    if (items.length === 0) return;
    list = items;
    const th = parseItems(t.dataset.lightboxThumbs);
    thumbs = th.length === items.length ? th : items;
    i = 0;
    title = t.dataset.lightboxTitle ?? "";
    trigger = t;
    if (headingEl) headingEl.textContent = title;
    if (totalEl) totalEl.textContent = photosTpl.replace("{n}", String(items.length));
    // A page that already shows its own thumbnails opens straight on the clicked photo.
    const start = Number(t.dataset.lightboxStart);
    if (items.length > 1) buildGrid();
    if (items.length > 1 && Number.isInteger(start) && start >= 0 && start < items.length && t.dataset.lightboxStart !== undefined) {
      i = start;
      show();
    } else if (items.length > 1) {
      showGrid();
    } else {
      gridEl?.replaceChildren();
      show();
    }
    if (!dialog.open) {
      // Very old browsers have no showModal: fall back to a plain open dialog.
      if (typeof dialog.showModal === "function") dialog.showModal();
      else dialog.setAttribute("open", "");
    }
  };
  const outside = (e: MouseEvent) => {
    const r = dialog.getBoundingClientRect();
    return e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom;
  };
  const closeDialog = () => {
    if (typeof dialog.close === "function") dialog.close();
    else {
      dialog.removeAttribute("open");
      onClose(); // no native close event in the fallback
    }
  };
  const onDialogClick = (e: Event) => {
    const t = e.target;
    // Backdrop = click lands on the dialog element itself AND outside its box (inside = e.g. a caption margin).
    if (t === dialog) return outside(e as MouseEvent) ? closeDialog() : undefined;
    const thumb = t instanceof Element ? t.closest<HTMLElement>("[data-lb-idx]") : null;
    if (thumb) {
      i = Number(thumb.dataset.lbIdx) || 0;
      show();
      return;
    }
    const action = t instanceof Element ? t.closest<HTMLElement>("[data-lb]")?.dataset.lb : undefined;
    if (action === "close") closeDialog();
    else if (action === "grid") showGrid(true);
    else if (action === "next") step(1);
    else if (action === "prev") step(-1);
  };
  const onKey = (e: KeyboardEvent) => {
    if (e.key === "ArrowRight") step(1);
    else if (e.key === "ArrowLeft") step(-1);
    else if (e.key === "Escape") {
      e.preventDefault();
      if (!inGrid && list.length > 1) {
        // Chrome may still fire a native "cancel" for this same key press; onCancel keeps the dialog open.
        escToGrid = true;
        setTimeout(() => (escToGrid = false), 0);
        showGrid(true);
      } else closeDialog();
    }
  };
  // Native close request (Escape the browser handles itself, Android back): large view -> grid first.
  const onCancel = (e: Event) => {
    if (escToGrid) return e.preventDefault();
    if (!inGrid && list.length > 1) {
      e.preventDefault();
      showGrid(true);
    }
  };
  const onClose = () => {
    img?.removeAttribute("src");
    gridEl?.replaceChildren();
    // preventScroll: returning focus must not jump the page.
    trigger?.focus({ preventScroll: true });
    trigger = null;
  };

  doc.addEventListener("click", onTrigger);
  dialog.addEventListener("click", onDialogClick);
  dialog.addEventListener("keydown", onKey);
  dialog.addEventListener("close", onClose);
  dialog.addEventListener("cancel", onCancel);
  return () => {
    dialog.removeEventListener("cancel", onCancel);
    doc.removeEventListener("click", onTrigger);
    dialog.removeEventListener("click", onDialogClick);
    dialog.removeEventListener("keydown", onKey);
    dialog.removeEventListener("close", onClose);
    delete dialog.dataset.lbReady;
  };
}

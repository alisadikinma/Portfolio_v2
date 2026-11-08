// Native <dialog> lightbox. Triggers: any element with data-lightbox, data-lightbox-title and
// data-lightbox-items (JSON array of image URLs). One dialog (#lb) per page, see Lightbox.astro.

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
  const img = dialog.querySelector<HTMLImageElement>("img");
  const titleEl = dialog.querySelector<HTMLElement>("[data-lb-title]");
  const countEl = dialog.querySelector<HTMLElement>("[data-lb-count]");
  const prevBtn = dialog.querySelector<HTMLButtonElement>('[data-lb="prev"]');
  const nextBtn = dialog.querySelector<HTMLButtonElement>('[data-lb="next"]');
  const altTpl = dialog.dataset.alt ?? "{title} {n}";
  dialog.dataset.lbReady = "1";

  let list: string[] = [];
  let i = 0;
  let title = "";
  let trigger: HTMLElement | null = null;

  const show = () => {
    if (img) {
      img.setAttribute("src", list[i]);
      img.alt = altTpl.replace("{title}", title).replace("{n}", String(i + 1));
    }
    if (titleEl) titleEl.textContent = title;
    if (countEl) countEl.textContent = `${i + 1} / ${list.length}`;
  };
  const step = (d: number) => {
    if (list.length < 2) return;
    i = (i + d + list.length) % list.length;
    show();
  };

  const onTrigger = (e: Event) => {
    const t = e.target instanceof Element ? e.target.closest<HTMLElement>("[data-lightbox]") : null;
    if (!t || !doc.contains(t)) return;
    const items = parseItems(t.dataset.lightboxItems);
    if (items.length === 0) return;
    list = items;
    i = 0;
    title = t.dataset.lightboxTitle ?? "";
    trigger = t;
    if (prevBtn) prevBtn.hidden = items.length < 2;
    if (nextBtn) nextBtn.hidden = items.length < 2;
    show();
    if (!dialog.open) dialog.showModal();
  };
  const onDialogClick = (e: Event) => {
    const t = e.target;
    if (t === dialog) return dialog.close();
    const action = t instanceof Element ? t.closest<HTMLElement>("[data-lb]")?.dataset.lb : undefined;
    if (action === "close") dialog.close();
    else if (action === "next") step(1);
    else if (action === "prev") step(-1);
  };
  const onKey = (e: KeyboardEvent) => {
    if (e.key === "ArrowRight") step(1);
    else if (e.key === "ArrowLeft") step(-1);
    else if (e.key === "Escape") {
      e.preventDefault();
      dialog.close();
    }
  };
  const onClose = () => {
    img?.removeAttribute("src");
    // preventScroll: returning focus must not jump the page.
    trigger?.focus({ preventScroll: true });
    trigger = null;
  };

  doc.addEventListener("click", onTrigger);
  dialog.addEventListener("click", onDialogClick);
  dialog.addEventListener("keydown", onKey);
  dialog.addEventListener("close", onClose);
  return () => {
    doc.removeEventListener("click", onTrigger);
    dialog.removeEventListener("click", onDialogClick);
    dialog.removeEventListener("keydown", onKey);
    dialog.removeEventListener("close", onClose);
    delete dialog.dataset.lbReady;
  };
}

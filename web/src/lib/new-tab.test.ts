import { describe, expect, it } from "vitest";
import { newTabLinks } from "./new-tab";

describe("newTabLinks (Ali 2026-11-09: every link opens a new tab)", () => {
  it("adds target=_blank to page links, internal and external", () => {
    expect(newTabLinks('<a href="/id/faq">FAQ</a>')).toBe('<a href="/id/faq" target="_blank">FAQ</a>');
    expect(newTabLinks('<a class="x" href="https://indusia.ai/id/">I</a>')).toBe('<a class="x" href="https://indusia.ai/id/" target="_blank">I</a>');
  });

  it("keeps an existing target and its rel untouched", () => {
    const a = '<a href="https://wa.me/1" target="_blank" rel="noopener">W</a>';
    expect(newTabLinks(a)).toBe(a);
    const self = '<a href="/x" target="_self">X</a>';
    expect(newTabLinks(self)).toBe(self);
  });

  it("leaves in-page section jumps in the same tab", () => {
    for (const h of ["#topik", "/id/#tentang", "/en/#writing"]) {
      const a = `<a href="${h}" data-spy>S</a>`;
      expect(newTabLinks(a)).toBe(a);
    }
  });

  it("leaves the language switch (hreflang) in the same tab", () => {
    const a = '<a class="lang flag" href="/en/about" hreflang="en" lang="en">EN</a>';
    expect(newTabLinks(a)).toBe(a);
  });

  it("leaves mailto, tel, javascript, download and href-less anchors alone", () => {
    for (const a of ['<a href="mailto:a@b.c">m</a>', '<a href="tel:+62">t</a>', '<a href="javascript:void 0">j</a>',
      '<a href="/press/bio.txt" download>d</a>', '<a name="top">n</a>']) {
      expect(newTabLinks(a)).toBe(a);
    }
  });

  it("handles single quotes, newlines in the tag and does not touch <abbr>/<area>", () => {
    expect(newTabLinks("<a\n  href='/id/blog'>B</a>")).toBe("<a\n  href='/id/blog' target=\"_blank\">B</a>");
    const o = '<abbr title="x">AI</abbr><area href="/m">';
    expect(newTabLinks(o)).toBe(o);
  });

  it("does not rewrite anchors inside <script> or JSON-LD", () => {
    const s = '<script type="application/ld+json">{"x":"<a href=\\"/y\\">"}</script><script>el.innerHTML = \'<a href="/z">\';</script>';
    expect(newTabLinks(s)).toBe(s);
  });

  it("leaves links inside a data-same-tab region (site header: menu + logo) in the same tab", () => {
    const h = '<header class="site-head" data-same-tab><a class="brand" href="/id">Logo</a><nav><a href="/id/pelatihan-ai">P</a></nav></header>';
    expect(newTabLinks(h + '<a href="/id/faq">F</a>')).toBe(h + '<a href="/id/faq" target="_blank">F</a>');
  });
});

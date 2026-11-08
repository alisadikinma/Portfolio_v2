import { describe, it, expect } from "vitest";
import { sanitizeHtml } from "./sanitize";

describe("sanitizeHtml: removes dangerous markup", () => {
  it("removes <script> incl. uppercase, attributes and content", () => {
    const out = sanitizeHtml('<p>a</p><script>alert(1)</script><SCRIPT type="text/javascript">alert(2)</SCRIPT><p>b</p>');
    expect(out).not.toMatch(/script/i);
    expect(out).not.toContain("alert");
    expect(out).toContain("<p>a</p>");
    expect(out).toContain("<p>b</p>");
  });

  it("removes on* handlers: quoted, single-quoted, unquoted", () => {
    const out = sanitizeHtml(
      `<img src="/a.png" alt="x" onerror="alert(1)"><a href="/x" onclick='alert(2)'>l</a><p onmouseover=alert(3)>t</p>`,
    );
    expect(out).not.toMatch(/onerror|onclick|onmouseover|alert/i);
    expect(out).toContain('src="/a.png"');
    expect(out).toContain('href="/x"');
  });

  it("removes javascript:, vbscript: and data:text/html URLs in href/src", () => {
    const out = sanitizeHtml(
      `<a href="javascript:alert(1)">a</a><a href=" JaVaScRiPt:alert(1)">b</a><a href="vbscript:x">c</a>` +
        `<a href="data:text/html;base64,PHNjcmlwdD4=">d</a><img src="javascript:alert(1)" alt="i"><img src="data:text/html,<b>" alt="j">`,
    );
    expect(out).not.toMatch(/javascript:|vbscript:|data:text\/html/i);
  });

  it("removes iframe, object, embed, style, form with content", () => {
    const out = sanitizeHtml(
      `<iframe src="https://evil.test"></iframe><object data="x"></object><embed src="x"><style>body{display:none}</style>` +
        `<form action="/x"><input name="a"><button>go</button></form><p>ok</p>`,
    );
    expect(out).not.toMatch(/<(iframe|object|embed|style|form|input|button)/i);
    expect(out).not.toContain("display:none");
    expect(out).toContain("<p>ok</p>");
  });

  it("survives nested/obfuscated script tags", () => {
    const out = sanitizeHtml(`<scr<script>ipt>alert(1)</scr</script>ipt><svg onload=alert(1)><p>x</p>`);
    expect(out).not.toMatch(/<script|onload|<svg/i);
  });

  it("returns empty string for empty or non-string input", () => {
    expect(sanitizeHtml("")).toBe("");
    expect(sanitizeHtml(undefined as unknown as string)).toBe("");
    expect(sanitizeHtml(null as unknown as string)).toBe("");
  });
});

describe("sanitizeHtml: keeps article HTML", () => {
  const article =
    `<h2>Judul</h2><h3>Sub</h3><h4>Sub2</h4><p>Teks <strong>tebal</strong> <em>miring</em><br>baris</p>` +
    `<ul><li>satu</li></ul><ol><li>dua</li></ol>` +
    `<a href="https://indusia.ai/id/">link</a><a href="/id/blog/x">internal</a>` +
    `<figure><img src="/storage/a.webp" alt="alt" width="640" height="360" srcset="/a-320.webp 320w, /a-640.webp 640w" loading="lazy"><figcaption>cap</figcaption></figure>` +
    `<blockquote>kutipan</blockquote><pre><code>const a = 1;</code></pre>` +
    `<table><thead><tr><th>h</th></tr></thead><tbody><tr><td>d</td></tr></tbody></table>`;

  it("keeps all allowed tags and attributes", () => {
    const out = sanitizeHtml(article);
    for (const frag of [
      "<h2>Judul</h2>", "<h3>Sub</h3>", "<h4>Sub2</h4>", "<strong>tebal</strong>", "<em>miring</em>", "<br />",
      "<ul><li>satu</li></ul>", "<ol><li>dua</li></ol>", 'href="https://indusia.ai/id/"', 'href="/id/blog/x"',
      "<figure>", "<figcaption>cap</figcaption>", 'src="/storage/a.webp"', 'alt="alt"', 'width="640"', 'height="360"',
      'srcset="/a-320.webp 320w, /a-640.webp 640w"', 'loading="lazy"', "<blockquote>kutipan</blockquote>",
      "<pre><code>const a = 1;</code></pre>", "<table>", "<th>h</th>", "<td>d</td>",
    ]) {
      expect(out, frag).toContain(frag);
    }
  });

  it("keeps mailto and tel links", () => {
    expect(sanitizeHtml('<a href="mailto:a@b.co">m</a>')).toContain('href="mailto:a@b.co"');
  });

  it("escapes stray text instead of executing it", () => {
    expect(sanitizeHtml("<p>1 < 2 & 3</p>")).toBe("<p>1 &lt; 2 &amp; 3</p>");
  });
});

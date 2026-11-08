"""og:image cards (1200x630) for the main pages (Ali 2026-11-09: real stage photo + proof).
Run: python3 og.py -> og-*.html; screenshot each at 1200x630 into web/public/og/<page>-<lang>.jpg."""
import html

PAGES = {
  "home":     {"photo": "home.jpg",     "pos": "40% 40%", "id": ("Pakar AI", "Indonesia"),              "en": ("AI Consultant", "Indonesia")},
  "training": {"photo": "training.jpg", "pos": "30% 50%", "id": ("Pelatihan AI", "untuk Perusahaan"),   "en": ("AI Training", "for Companies")},
  "speaker":  {"photo": "speaker.jpg",  "pos": "18% 50%", "id": ("Pembicara AI", "Indonesia"),          "en": ("AI Speaker", "Indonesia")},
}
BADGE = {"id": "Global AI Demo Day 2026", "en": "Global AI Demo Day 2026"}
SUB = {"id": "Founder INDUSIA.ai · Batam", "en": "Founder of INDUSIA.ai · Batam"}

CSS = """
* { box-sizing: border-box; margin: 0; }
body { width: 1200px; height: 630px; overflow: hidden; background: #0d1626; font-family: 'Inter', sans-serif; color: #eef3fa; }
.card { position: relative; width: 1200px; height: 630px; display: grid; grid-template-columns: 640px 1fr; }
.ph { position: relative; overflow: hidden; }
.ph img { width: 100%; height: 100%; object-fit: cover; }
.ph::after { content: ""; position: absolute; inset: 0; background: linear-gradient(90deg, rgba(13,22,38,0) 55%, #0d1626 99%), linear-gradient(0deg, rgba(13,22,38,.35), transparent 40%); }
.tx { position: relative; padding: 56px 56px 48px 24px; display: flex; flex-direction: column; }
.tx::before { content: ""; position: absolute; right: -120px; top: -120px; width: 420px; height: 420px; border-radius: 50%; background: radial-gradient(circle, rgba(63,184,197,.22), transparent 70%); }
.badge { align-self: flex-start; display: inline-flex; align-items: center; gap: 10px; padding: 10px 18px; border-radius: 999px; background: #d4a24d; color: #1a1205; font: 700 17px/1 'JetBrains Mono', monospace; white-space: nowrap; box-shadow: 0 0 34px rgba(212,162,77,.45); }
.badge .n { font-size: 19px; }
h1 { margin-top: 34px; font: 700 70px/1.0 'Space Grotesk', sans-serif; letter-spacing: -.03em; }
h1 span { display: block; color: #3fb8c5; }
.name { margin-top: 30px; font: 700 34px/1.1 'Space Grotesk', sans-serif; }
.sub { margin-top: 8px; font-size: 22px; color: #a9b6c8; }
.foot { margin-top: auto; display: flex; align-items: center; justify-content: space-between; }
.foot img { height: 30px; }
.foot .url { font: 600 20px/1 'JetBrains Mono', monospace; color: #a9b6c8; }
"""

def card(page, lang):
    p = PAGES[page]; l1, l2 = p[lang]; e = html.escape
    return f"""<!doctype html><html><head><meta charset="utf-8">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Space+Grotesk:wght@700&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
<style>{CSS}</style></head><body><div class="card">
<div class="ph"><img src="og-photos/{p['photo']}" style="object-position:{p['pos']}" alt=""></div>
<div class="tx">
  <span class="badge"><span class="n">★ #1</span>{e(BADGE[lang])}</span>
  <h1>{e(l1)}<span>{e(l2)}</span></h1>
  <p class="name">Ali Sadikin Ma</p>
  <p class="sub">{e(SUB[lang])}</p>
  <div class="foot"><img src="og-photos/indusia-logo-light.png" alt=""><span class="url">alisadikinma.com</span></div>
</div></div></body></html>"""

for page in PAGES:
    for lang in ("id", "en"):
        open(f"og-{page}-{lang}.html", "w").write(card(page, lang))
print("ok")

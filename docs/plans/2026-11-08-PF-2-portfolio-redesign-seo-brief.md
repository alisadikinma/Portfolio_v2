# Brief: rombak total alisadikinma.com — satu gaya dengan indusia.ai

Dibuat 2026-11-08 di sesi Indusia-ai-web (hanya perencanaan). Eksekusi di repo Portfolio_v2.
Belum ada tiket — sesi eksekusi buat tiket PF berikutnya, lalu pindahkan file ini ke branch tiket
(jangan biarkan salinan untracked di main; bisa memblokir merge).

Data keyword: `docs/seo/2026-11-08-keyword-research-personal-brand.md`.

## 1. Tujuan
1. Ali dikenal di Google dan LLM sebagai **pakar AI Indonesia** / **AI consultant Indonesia**.
2. Situs pribadi dan indusia.ai terasa satu keluarga: warna, huruf, ilustrasi, gerak sama.
3. Portfolio jadi pintu masuk pelatihan AI, workshop AI, dan pembicara AI.
4. Link kontekstual ke indusia.ai (backlink), dan indusia.ai /founder/ menautkan balik.

## 2. Pembagian keyword dengan indusia.ai (jangan saling rebut)
| Situs | Membidik | Tidak membidik |
|---|---|---|
| indusia.ai | perusahaan AI Indonesia, jasa pembuatan AI, machine vision, fleet management system, CCTV AI, chatbot WhatsApp AI | nama Ali, pelatihan, pembicara |
| alisadikinma.com | pakar AI Indonesia, AI consultant Indonesia, ahli AI, pelatihan AI, workshop AI, seminar AI, pembicara AI | jasa pembuatan AI, nama produk INDUSIA |

Nama selalu "Ali Sadikin Ma" (bukan "Ali Sadikin" — bertabrakan dengan mantan Gubernur DKI).
Buang "AI Generalist" dari title/H1 (volume kecil, kabur). Boleh tetap di teks biasa.
Tanpa harga. CTA = konsultasi via WhatsApp / form kontak.

## 3. Peta halaman dan keyword
| URL | Title | Keyword utama |
|---|---|---|
| /id | Ali Sadikin Ma — Pakar AI Indonesia, Founder INDUSIA.ai | pakar AI Indonesia |
| /en | Ali Sadikin Ma — AI Consultant Indonesia, Founder of INDUSIA.ai | AI consultant Indonesia |
| /id/pelatihan-ai (baru) | Pelatihan AI & Workshop AI untuk Perusahaan — Ali Sadikin Ma | pelatihan AI, workshop AI, training AI |
| /en/ai-training (baru) | AI Training & Workshops for Companies in Indonesia — Ali Sadikin Ma | AI training Indonesia |
| /id/pembicara-ai (baru) | Pembicara AI & Narasumber Seminar AI — Ali Sadikin Ma | pembicara AI, seminar AI |
| /en/ai-speaker (baru) | AI Speaker Indonesia — Ali Sadikin Ma | AI speaker Indonesia |
| /id/about, /en/about | Tentang Ali Sadikin Ma — Ahli AI dari Batam / About … AI Expert from Batam | ahli AI Indonesia |
| /work, /projects, /blog | tetap, title diberi pola "… — Ali Sadikin Ma" | – |

**Pelatihan/workshop belum buka (keputusan Ali 2026-11-08):** halaman /pelatihan-ai dan
/ai-training tetap dibuat (untuk keyword), tetapi CTA-nya **daftar waitlist**, bukan WhatsApp.
- Teks jujur: "Segera dibuka — daftar waitlist, kami kabari saat kelas dibuka." Jangan tulis
  jadwal, kuota, atau kesan kelas sudah berjalan.
- Form: nama, perusahaan (opsional), email, WhatsApp (opsional), minat (pelatihan inhouse /
  workshop / kelas umum), catatan. Simpan ke tabel sendiri (mis. `training_waitlist`) dengan
  `source_page` dan `locale`; jangan dicampur ke newsletter tanpa persetujuan peserta
  (boleh checkbox "juga kirimi saya newsletter").
- Tahan spam: honeypot + rate limit + tombol nonaktif saat kirim + kunci idempotensi
  (aturan vault: coding-rules). Konfirmasi di layar setelah daftar.
- Admin: modul baru **"Waitlist Pelatihan"** di admin CMS yang sudah ada (`/admin/...`), menu di
  sidebar admin. Tiru pola modul yang mirip: `NewsletterSubscribers.vue` (`/admin/newsletter`) dan
  `ContactsList.vue` (`/admin/contacts`) — daftar, cari/filter (minat, locale, status), ubah status
  (baru / sudah dikontak / batal), catatan, ekspor CSV. Endpoint admin di balik auth yang sama
  dengan modul admin lain; endpoint publik hanya `POST` daftar.
- Schema Service tetap boleh; tanpa `offers`/harga/jadwal.
- Pembicara AI: CTA WhatsApp/kontak, bukan waitlist (dikonfirmasi Ali 2026-11-08).

Aturan isi per halaman: H1 memuat keyword; kalimat pertama menjawab "siapa/apa" secara lugas
(enak dikutip LLM); FAQ 4–6 pertanyaan, pertanyaan pertama memuat keyword; ID dan EN setara.
Pelatihan/pembicara: topik, format (inhouse, workshop 1 hari, keynote), bukti (Outskill Demo Day
Champion 2026, panggung internasional, 56+ produk di Satnusa). CTA: waitlist untuk pelatihan/
workshop, WhatsApp untuk pembicara. Tanpa harga.

## 4. Crawler dan LLM harus bisa membaca isi (prioritas 1)
Sekarang SPA: hanya `/`, `/faq`, blog, dan projects/{slug} yang diperkaya server
(`SpaPrerenderController` + `SeoHtmlComposer`). `/en`, `/id`, `/about`, `/work`, `/contact`,
`/projects` dikirim kosong ke crawler.
- Perluas prerender ke semua halaman publik, termasuk halaman baru. Isi body HTML nyata
  (H1, paragraf pembuka, FAQ, link), bukan hanya `<noscript>` ringkas.
- Ubah rute nginx (`scripts/nginx/portfolio-8080.conf`) agar halaman itu lewat PHP-FPM.
  Catatan: config nginx live disinkron manual, `deploy.sh` tidak menyentuhnya.
- Title statis di `frontend/index.html` ikut diganti (sekarang "AI Solopreneur Studio…").
- hreflang id/en/x-default di setiap halaman; sitemap memuat halaman baru.

## 5. Schema (JSON-LD)
- Person "Ali Sadikin Ma" adalah node utama: `jobTitle` "AI Consultant", `knowsAbout`,
  `sameAs` semua akun sosial Ali (sekarang sosial menempel di Organization — pindahkan),
  `worksFor`/`founder` → `{"@id": "https://indusia.ai/#organization"}` (pakai @id yang sama
  dengan indusia.ai, jangan membuat Organization INDUSIA kedua dengan data berbeda).
- `SchemaGraphBuilder.php` sekarang memasang `aggregateRating` + `review` dari testimoni Ali ke
  Organization INDUSIA — salah alamat. Testimoni itu tentang Ali: tempel ke Person atau hapus.
- Halaman pelatihan/pembicara: node `Service` (provider = Person), `areaServed` Indonesia.
- Sinkron tiga sumber: `index.html`, `src/utils/personSchema.js`, `SchemaGraphBuilder.php`.

## 6. Link ke indusia.ai (backlink kontekstual)
- WhoIAm/About: satu kalimat, contoh "Untuk proyek AI perusahaan — machine vision, fleet
  management system, chatbot WhatsApp AI — saya bangun lewat [INDUSIA.ai, perusahaan AI Indonesia]".
- Halaman pelatihan: "Butuh sistemnya dibuatkan setelah pelatihan? [Jasa pembuatan AI INDUSIA]"
  → https://indusia.ai/id/ai-development-services/ (EN → /ai-development-services/).
- Footer: "Founder INDUSIA.ai". Link dofollow, tanpa nofollow.
- Di indusia.ai: halaman /founder/ sudah menautkan ke https://alisadikinma.com (dicek 2026-11-08).

## 7. Gaya visual = sistem desain INDUSIA
Sumber token: `Indusia-ai-web/src/styles/tokens.css` (salin nilainya ke `@theme` Tailwind 4 di
`frontend/src/style.css`).
- Latar: `--bg-deep #0a1220`, `--bg-surface #0e1828`, `--bg-elevated #16243a`, kaca `rgba(10,18,32,.72)`.
- Teks: `#e8eef8` / `#9fb0c7` / `#67788f`.
- Aksen: teal `#3fb8c5` = satu-satunya warna CTA/link/aktif; emas `#d4a24d` = sorotan
  (angka, penghargaan). Sekarang portfolio pakai emas sebagai CTA — dibalik mengikuti INDUSIA.
- Huruf: Space Grotesk (judul), Inter (isi), JetBrains Mono (label/eyebrow). Buang Geist,
  Playfair, dan Font Awesome CDN (pakai Heroicons).
- Radius 10/18px, `--ease-out cubic-bezier(.16,1,.3,1)`, hover .25s.
- Ilustrasi: gaya ilustrasi INDUSIA (lihat komponen `IllusArt`) untuk halaman layanan; foto Ali
  tetap jadi pusat hero (personal brand).
- Pembeda personal: foto Ali + angka/penghargaan emas lebih menonjol; struktur, warna, huruf sama.

Proses (aturan CLAUDE.md user): ui-ux-pro-max `--design-system --persist` dengan token INDUSIA
sebagai batasan merek → frontend-design meninjau dan memutuskan → gsap-core menulis animasi.
Satu momen animasi utama (hero), sisanya tenang. `gsap.matchMedia()` + `prefers-reduced-motion`.
Satu library animasi (GSAP); Three.js globe dipertahankan hanya kalau ringan, kalau tidak dibuang.

## 8. Masalah yang terlihat sekarang
- Menu atas menutupi kartu foto WhoIAm saat scroll (desktop 1312px).
- Situs hanya muncul di /en untuk pengunjung; konten /id perlu setara.
- Video hero besar → cek LCP.

## 9. Urutan kerja (usulan fase)
1. Fondasi SEO: prerender semua halaman publik, title/H1/meta per keyword, schema Person, hreflang, sitemap.
2. Sistem desain: token INDUSIA ke Tailwind, font, komponen dasar (tombol, kartu, eyebrow, nav).
3. Redesign Home + About dengan token baru, link INDUSIA, perbaiki nav overlap.
4. Halaman baru pelatihan AI (form waitlist + tabel + modul admin CMS "Waitlist Pelatihan") dan pembicara AI
   (ID/EN) dengan FAQ dan Service schema.
5. Animasi GSAP hero, uji reduced-motion, uji crawler (curl tanpa JS) per halaman.
6. llms.txt diperbarui; deploy (push ke main = deploy otomatis — minta izin Ali dulu).

## 10a. Keputusan Ali (2026-11-08, sesi perencanaan)
- **Konsultasi AI** → WhatsApp Ali langsung (bukan ke indusia.ai). Link kontekstual INDUSIA tetap ada.
- **Bahasa default** `/` → ikut browser (seperti sekarang); hreflang id/en/x-default.
- **Hero** → foto Ali + satu animasi GSAP. Video hero dan globe 3D (Three.js/TresJS) dibuang
  (hapus dependensinya kalau tidak dipakai di tempat lain).
- **Section home dipertahankan:** Panggung internasional, Testimoni, Tulisan terbaru, Newsletter
  (plus section inti WhoIAm, WhatISolve, Receipts, SelectedWork).
- Urutan home usulan: Hero (pakar AI Indonesia) → Who I Am (+ link INDUSIA) → Receipts/angka →
  **Layanan baru** (Konsultasi AI → WA · Pelatihan & Workshop → waitlist · Pembicara AI → WA) →
  What I Solve → Panggung internasional → Proyek pilihan → Testimoni → Tulisan → Newsletter.
  Section baru "Layanan" wajib didaftarkan di `PageSectionSeeder`, `Home.vue`, `TheNavigation.vue`.

## 10. Aturan repo Portfolio_v2 yang wajib
- Push ke main = deploy produksi. Commit saja; push hanya dengan izin Ali.
- Backend lewat `docker exec portfolio_backend …`; frontend Vite :5173.
- Section home: `PageSectionSeeder`, id di `Home.vue`, `sectionLinks` di `TheNavigation.vue` sinkron;
  `v-if` di wrapper `.snap-section`.
- Perbarui CLAUDE.md repo bila rute/section/env berubah.
- e2e: satu jendela Chrome headed via CDP, satu tab dipakai ulang, tidak buka-tutup.

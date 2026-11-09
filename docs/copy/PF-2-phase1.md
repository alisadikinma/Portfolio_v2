# Copy PF-2 fase 1 — nav, footer, halaman FAQ

**Status: menunggu persetujuan Ali.** (Catat persetujuan di ledger PF-2.)

Sumber teks di kode: `web/src/content/copy/id.ts` dan `en.ts`. Kalau ada yang diubah di sini, kode ikut diubah.

Aturan yang dipakai: nama selalu "Ali Sadikin Ma". Tanpa harga. Tanpa klaim "terbaik/nomor 1". Tanpa nomor WhatsApp di teks (nomor ada di konfigurasi, belum diisi).

## Menu atas (nav)

| Bagian | Indonesia | English |
|---|---|---|
| Tentang | Tentang | About |
| Perjalanan | Perjalanan | Journey |
| Layanan | Layanan | Services |
| Pelatihan | Pelatihan AI | AI Training |
| Pembicara | Pembicara AI | AI Speaker |
| Blog | Blog | Blog |
| Ganti bahasa | EN | ID |
| Tombol | Konsultasi via WhatsApp | Consult via WhatsApp |
| Label aksesibilitas menu | Menu utama | Main menu |
| Label aksesibilitas bahasa | Ganti bahasa | Switch language |

## Footer

| Bagian | Indonesia | English |
|---|---|---|
| Hak cipta | © 2026 Ali Sadikin Ma | © 2026 Ali Sadikin Ma |
| Founder | Founder INDUSIA.ai | Founder of INDUSIA.ai |
| Teks tautan ke INDUSIA | INDUSIA.ai, perusahaan AI Indonesia | INDUSIA.ai, an Indonesian AI company |
| Tautan | FAQ, LinkedIn, Instagram, YouTube, TikTok | FAQ, LinkedIn, Instagram, YouTube, TikTok |
| Label aksesibilitas sosial | Media sosial Ali Sadikin Ma | Ali Sadikin Ma on social media |

## Situs (umum)

| Bagian | Indonesia | English |
|---|---|---|
| Nama | Ali Sadikin Ma | Ali Sadikin Ma |
| Tagline | Pakar AI Indonesia, Founder INDUSIA.ai | AI Consultant Indonesia, Founder of INDUSIA.ai |

## Halaman FAQ (`/id/faq`, `/en/faq`)

| Bagian | Indonesia | English |
|---|---|---|
| Title (tab/Google) | FAQ Pakar AI Indonesia — Ali Sadikin Ma | FAQ AI Consultant Indonesia — Ali Sadikin Ma |
| Deskripsi (Google) | Jawaban singkat tentang Ali Sadikin Ma, pakar AI Indonesia dan founder INDUSIA.ai: siapa dia, apa yang dikerjakan, dan cara bekerja sama. | Short answers about Ali Sadikin Ma, AI consultant in Indonesia and founder of INDUSIA.ai: who he is, what he does, and how to work with him. |
| H1 | Pertanyaan umum tentang pakar AI Indonesia, Ali Sadikin Ma | Common questions about Ali Sadikin Ma, AI consultant Indonesia |
| Paragraf pembuka | Ali Sadikin Ma adalah pakar AI Indonesia dan founder INDUSIA.ai di Batam. Halaman ini menjawab pertanyaan yang paling sering diajukan tentang dirinya, layanannya, dan cara menghubunginya. | Ali Sadikin Ma is an AI consultant in Indonesia and the founder of INDUSIA.ai in Batam. This page answers the questions people ask most about him, his services, and how to reach him. |
| Pesan jika daftar gagal dimuat | Daftar pertanyaan sedang tidak bisa dimuat. Silakan coba lagi sebentar lagi. | The question list cannot be loaded right now. Please try again shortly. |
| Tautan 1 | Ingin belajar langsung? Lihat Pelatihan AI | Want to learn hands-on? See AI Training |
| Tautan 2 | Ingin tahu lebih jauh? Baca tentang Ali Sadikin Ma | Want to know more? Read about Ali Sadikin Ma |

Isi tanya-jawab FAQ sendiri datang dari backend (`config/faq.php`), bukan dari berkas ini.

## Yang perlu Ali putuskan

1. Nomor WhatsApp: belum ada di berkas mana pun. Isi di konfigurasi (env) sebelum tombol WhatsApp aktif.
2. Tautan sosial (LinkedIn, Instagram, YouTube, TikTok): alamat akun belum dicek di fase ini.
3. Mockup halaman pelatihan hanya punya 3 sosial (tanpa TikTok); beranda punya 4. Saya pakai 4.


## FAQ versi Indonesia (BARU, 2026-11-08 — keputusan Ali: terjemahkan)

Sumber: `backend/config/faq.php` `items_id` (dilayani `/api/faq?lang=id`). Terjemahan setia dari set EN, tanpa klaim baru. Jawaban lengkap ada di file config. Catatan: set EN memuat klaim "16 negara"; bio pers 4F tidak memakainya karena tidak ada sumbernya — mohon Ali konfirmasi.

| # | Pertanyaan EN | Pertanyaan ID |
|---|---|---|
| 1 | Who is Ali Sadikin Ma? | Siapa Ali Sadikin Ma? |
| 2 | What services does Ali Sadikin Ma offer? | Layanan apa saja yang ditawarkan Ali Sadikin Ma? |
| 3 | What makes Ali Sadikin Ma's approach different? | Apa yang membedakan pendekatan Ali Sadikin Ma? |
| 4 | Who does Ali Sadikin Ma work with? | Dengan siapa saja Ali Sadikin Ma bekerja? |
| 5 | What is AI visual inspection and does Ali Sadikin Ma build it? | Apa itu inspeksi visual berbasis AI dan apakah Ali Sadikin Ma membangunnya? |
| 6 | What is Vibe Coding and does Ali Sadikin Ma teach it? | Apa itu Vibe Coding dan apakah Ali Sadikin Ma mengajarkannya? |
| 7 | What technology stack does Ali Sadikin Ma use? | Teknologi apa yang dipakai Ali Sadikin Ma? |
| 8 | What results has Ali Sadikin Ma delivered? | Hasil apa yang sudah diberikan Ali Sadikin Ma? |
| 9 | Does Ali Sadikin Ma teach or speak about AI? | Apakah Ali Sadikin Ma mengajar atau menjadi pembicara tentang AI? |
| 10 | How can I contact or work with Ali Sadikin Ma? | Bagaimana cara menghubungi atau bekerja sama dengan Ali Sadikin Ma? |
| 11 | In which languages does Ali Sadikin Ma work? | Dalam bahasa apa saja Ali Sadikin Ma bekerja? |

### Perubahan klaim FAQ — Phase 4G (BARU, menunggu persetujuan Ali)

Klaim "AI Generalist" (sebagai gelar) dan "16 negara" dihapus dari kedua set (`items` EN dan `items_id` ID). Fakta lain (17 tahun, peringkat 1 Global AI Demo Day 2026, berbasis di Indonesia) tetap.

| # | Set | Lama | Baru |
|---|---|---|---|
| 1 | EN | Ali Sadikin Ma is an AI Generalist who turns frontier AI models into shipped products rather than slide decks. He has 17 years of building experience across 16 countries and ranked #1 at the Global AI Demo Day 2026. | Ali Sadikin Ma is an Indonesian AI expert who turns frontier AI models into shipped products rather than slide decks. He has 17 years of building experience and ranked #1 at the Global AI Demo Day 2026. |
| 8 | EN | …and has delivered AI and software projects across 16 countries over 17 years. | …and has delivered AI and software projects over 17 years. |
| 1 | ID | Ali Sadikin Ma adalah AI Generalist yang mengubah model AI terbaru menjadi produk yang benar-benar dipakai, bukan sekadar slide presentasi. Ia punya pengalaman membangun produk selama 17 tahun di 16 negara dan meraih peringkat 1 … | Ali Sadikin Ma adalah pakar AI Indonesia yang mengubah model AI terbaru menjadi produk yang benar-benar dipakai, bukan sekadar slide presentasi. Ia punya pengalaman membangun produk selama 17 tahun dan meraih peringkat 1 … |
| 8 | ID | …dan sudah menyelesaikan proyek AI dan software di 16 negara selama 17 tahun. | …dan sudah menyelesaikan proyek AI dan software selama 17 tahun. |

### llms.txt — Phase 4G (BARU, menunggu persetujuan Ali)

- Judul: "Ali Sadikin Ma — pakar AI Indonesia / AI consultant, founder INDUSIA.ai (https://indusia.ai)" (menggantikan "AI Generalist Expert").
- Dihapus: "AI Generalist", "17 years building across 16 countries", "Google Startup Grind — Silicon Valley 2018", "IDBYTE" (konsisten dengan keputusan 4F).
- Ditambah: "56+ digital products (AI, IoT, mobile) built at Satnusa", daftar halaman berawalan bahasa (pelatihan-ai/ai-training, pembicara-ai/ai-speaker, about, work?tab=awards, faq, projects, blog, contact).


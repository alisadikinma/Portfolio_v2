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

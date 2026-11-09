# Stack lokal PF-2 (untuk review sebelum rilis)

Situs baru lengkap di **http://localhost:8000**, pakai salinan data prod dan semua gambar.

| Alamat | Isi |
|---|---|
| http://localhost:8000/id | situs publik (Astro) |
| http://localhost:8000/admin | admin (Vue), login sama dengan prod |
| http://localhost:8000/api/health | API Laravel |

Nginx memakai template prod yang sama (`scripts/nginx/portfolio-8080.conf`), jadi routing di sini sama dengan yang nanti rilis.

## Perintah

```bash
scripts/pf2-local.sh up       # nyalakan (build admin + Astro, cek semua halaman)
scripts/pf2-local.sh status   # cek kesehatan + smoke test
scripts/pf2-local.sh logs web # log satu service (web, backend, nginx, mysql)
scripts/pf2-local.sh down     # matikan (data tetap)
scripts/pf2-local.sh import   # muat ulang data dari .local-stack/pf2-local.sql.gz (DB lokal dihapus dulu)
```

Setelah mengubah kode `web/`: `docker restart pf2_local_web` (build ulang ±1 menit).

## Aman untuk dicoba

- Tidak ada yang terkirim ke prod. Email = log, Telegram/auto-publish mati, tidak ada queue worker atau scheduler, token/API key di `settings` dikosongkan.
- Formulir (kontak, newsletter, waitlist) masuk ke database **lokal** saja.
- Data `newsletters`, `contacts`, `sessions`, token tidak ikut disalin (tabelnya kosong).

## Data

`.local-stack/` (tidak masuk git): `pf2-local.sql.gz` (dump prod), `storage-public/` + `uploads/` (gambar prod).
URL gambar di dump diubah ke `http://localhost:8000` saat import.

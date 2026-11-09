"""Resume source for the speaker press kit (public). Run: python3 build.py -> resume-en.html, resume-id.html.
Print each to A4 PDF into web/public/press/ali-sadikin-ma-resume-{en,id}.pdf.
Facts come from Ali's CV (2026-11). Public version: no job-application content, no relocation line,
client names withheld except publicly listed companies."""
import html

CSS = """
@page { size: A4; margin: 0; }
* { box-sizing: border-box; }
body { margin: 0; font: 9.1pt/1.42 'Inter', 'Helvetica Neue', Arial, sans-serif; color: #1c2533; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.page { width: 210mm; height: 297mm; padding: 13mm 15mm 16mm; position: relative; page-break-after: always; overflow: hidden; }
.page:last-child { page-break-after: auto; }
.top { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #0d1626; padding-bottom: 5mm; }
h1 { margin: 0; font: 700 25pt/1 'Space Grotesk', 'Inter', sans-serif; letter-spacing: -.02em; color: #0d1626; }
.role { margin-top: 2mm; font-size: 11pt; color: #2a8f9b; font-weight: 600; }
.contact { text-align: right; font-size: 8.6pt; line-height: 1.6; color: #3b4658; }
.stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 3mm; margin: 5mm 0 4mm; }
.stat { background: #f1f6f8; border-radius: 3mm; padding: 3mm; }
.stat b { display: block; font: 700 15pt/1.1 'Space Grotesk', 'Inter', sans-serif; color: #0d1626; }
.stat span { font-size: 7.8pt; color: #4b5668; line-height: 1.3; display: block; margin-top: 1mm; }
h2 { font: 700 10pt/1 'Inter', sans-serif; letter-spacing: .12em; text-transform: uppercase; color: #2a8f9b; margin: 5mm 0 2.5mm; }
p { margin: 0 0 2mm; }
.job { display: grid; grid-template-columns: 30mm 1fr; gap: 4mm; margin-bottom: 3.2mm; }
.when { font-size: 8.4pt; color: #4b5668; }
.when b { display: block; color: #0d1626; font-size: 9pt; }
.job h3 { margin: 0; font-size: 10pt; color: #0d1626; }
.job .org { font-size: 8.6pt; color: #4b5668; margin-bottom: 1mm; }
ul { margin: 0; padding-left: 4mm; }
li { margin-bottom: .8mm; }
.cols { display: grid; grid-template-columns: 1fr 1fr; gap: 6mm; }
.awards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 3mm; }
.aw { border: 1px solid #d8e2e8; border-radius: 3mm; padding: 3mm; font-size: 8.2pt; }
.aw b { display: block; font: 700 13pt/1 'Space Grotesk', 'Inter', sans-serif; color: #2a8f9b; }
.aw strong { display: block; color: #0d1626; margin: 1mm 0 .5mm; }
.chips { display: flex; flex-wrap: wrap; gap: 1.5mm; }
.chips span { background: #f1f6f8; border-radius: 99px; padding: .8mm 2.6mm; font-size: 8pt; }
.foot { position: absolute; left: 15mm; right: 15mm; bottom: 8mm; display: flex; justify-content: space-between; font-size: 7.6pt; color: #7a8496; border-top: 1px solid #e3e9ee; padding-top: 2mm; }
.sub { font-size: 8.6pt; color: #4b5668; }
"""

T = {
 "en": {
  "lang": "en", "title": "Ali Sadikin Ma — Resume",
  "role": "AI Consultant Indonesia · Founder & CEO, INDUSIA.ai",
  "loc": "Batam, Indonesia",
  "stats": [("17+", "years in enterprise IT and digital transformation"), ("3→31", "digital department built and led at an IDX-listed manufacturer"), ("56+", "digital, AI and IoT products for 5,000+ employees"), ("$318K+", "documented impact, verified in the 2024 annual report"), ("#1", "Global AI Demo Day 2026, Bengaluru")],
  "h_profile": "Profile",
  "profile": "I bring AI into company operations. At PT Sat Nusapersada Tbk, an IDX-listed electronics manufacturer for Xiaomi, Lenovo, Asus and Motorola, I built the digital department from 3 to 31 people and shipped 56+ products used by 5,000+ employees. Before that: 7 years of enterprise IT in Singapore (MPA, Thales, DHL) and a sea-transport startup with Indonesia's Ministry of Transportation. Today, as founder of INDUSIA.ai, I build and run AI systems end to end: product, on-premise factory servers, cloud, backups and security. I also train company teams and speak about applied AI.",
  "h_exp": "Experience",
  "jobs": [
   ("2026 – now", "Batam · global", "Founder & CEO", "INDUSIA.ai · PT Indusia Kecerdasan Digital · AI for factories and logistics", [
     "Built and deployed a dual-side AI PCB inspection system that replaces legacy AOI at an electronics manufacturer in Batam: 2 production lines live, ≥95% defect-detection SLA aligned to IPC-A-610.",
     "Delivered Smart Fleet, a logistics platform live at a Batam container-trucking operator: RFID gates, fuel telemetry, container pairing, automatic e-POD.",
     "Run all infrastructure personally: on-premise edge servers on factory lines (offline-first), cloud, CI/CD, backups, access control.",
     "Design AI solutions for manufacturing, logistics, FMCG and retail clients; lead a distributed team and integration partners."]),
   ("2023 – 2025", "Batam", "Head of Digital Transformation & Process Automation", "PT Sat Nusapersada Tbk (IDX-listed EMS)", [
     "Built and led a cross-functional team of 31 engineers, designers and analysts delivering 56+ AI, IoT and digital products for 5,000+ employees.",
     "$318K+ documented impact (2024 annual report) and 25% faster time-to-market.",
     "Shipped MySATNUSA, a super-app for 5,000+ daily users across 8 departments: 30+ workflows, 40% less admin overhead, 99%+ uptime.",
     "AI visual inspection: 98% accuracy, 75% faster processing, $200K+ annual savings; IoT monitoring added 30% efficiency."]),
   ("2021 – 2023", "Batam", "Process Engineering Manager", "PT Sat Nusapersada Tbk", [
     "Raised SMT assembly productivity 20%; pass rate +25% and rejections −20% with SPI and AOI.",
     "Led SMT–MES integration under Agile: 35% operational efficiency gain, 99% line efficiency."]),
   ("2016 – 2019", "Batam", "Co-Founder & CEO", "Marlin Booking · sea-transport technology", [
     "Digitised passenger port operations with Indonesia's Ministry of Transportation at 5 ports.",
     "Mobile ticketing for Singapore–Malaysia ferry routes: +20% customer satisfaction, +15% sales. Raised $250K at a $5M valuation."]),
   ("2008 – 2015", "Singapore · 7 years", "Enterprise IT", "MPA Singapore · Thales (ex Gemalto) · DHL Supply Chain · eXSYS", [
     "Application development, solution support and software engineering for port authority, telco and supply-chain systems in APAC (J2EE, Oracle, Linux).",
     "DHL: −25% downtime, zero security breaches in two years."]),
  ],
  "h_built": "Selected work at INDUSIA.ai",
  "built": [("AI PCB Visual Inspection", "Live. Dual-side defect detection on edge hardware, ≤30 ms per image, fully on-premise."),
            ("Smart Fleet", "Live. Container-trucking platform: RFID gates, fuel telemetry, e-POD, owner voice assistant."),
            ("Sparkfluence", "Live. AI short-form video SaaS; champion of Global AI Demo Day 2026."),
            ("Autonomous content engine", "Live. alisadikinma.com researches, writes and publishes with human approval."),
            ("Indusia Accounting & Finance", "Built. Legacy desktop accounting moved to the web; Indonesian tax app."),
            ("Merchant POS & Attendance SaaS", "Built. QRIS-ready multi-tenant POS; face + GPS attendance.")],
  "h_rec": "Recognition",
  "awards": [("#1", "Global AI Demo Day 2026", "Bengaluru, India. Sparkfluence chosen out of 26 startups."),
             ("1/48", "UN-UNCTAD × Alibaba eFounders", "Hangzhou, China, 2019. One of 48 founders from 16 countries."),
             ("#1", "Telkomsel The NextDev 2018", "Transport category with Marlin Booking."),
             ("WC", "Fenox Startup World Cup 2017", "Wild Card winner; grand finale in Silicon Valley.")],
  "h_skills": "Skills",
  "skills": ["AI agents", "RAG", "Computer vision (YOLO, anomaly detection)", "Edge AI (Jetson)", "Local LLMs", "RPA / n8n", "SMT · MES · AOI/SPI", "IPC-A-610", "PLC (Modbus TCP / OPC-UA)", "IoT monitoring", "Laravel", "Vue / React / Next.js", "NestJS", "FastAPI / Python", "PostgreSQL", "Flutter", "Docker / Linux", "CI/CD", "Agile / Jira", "Digital roadmap & ROI"],
  "h_edu": "Education & certifications",
  "edu": ["BINUS University — Bachelor, Informatics Engineering, cum laude (2019)", "Politeknik Caltex Riau — Diploma, Computer Engineering, cum laude (2007)", "Google Project Management: Professional Certificate", "Outskill AI Generalist Fellowship", "UN-UNCTAD × Alibaba eFounders Fellowship (2019)"],
  "h_lang": "Languages",
  "langs": ["Indonesian — native", "English — professional (7 years in Singapore)", "Mandarin — conversational"],
  "foot": "Ali Sadikin Ma · Resume",
 },
 "id": {
  "lang": "id", "title": "Ali Sadikin Ma — Resume",
  "role": "Pakar AI Indonesia · Founder & CEO, INDUSIA.ai",
  "loc": "Batam, Indonesia",
  "stats": [("17+", "tahun di IT perusahaan dan transformasi digital"), ("3→31", "departemen digital yang dibangun dan dipimpin di perusahaan Tbk"), ("56+", "produk digital, AI, dan IoT untuk 5.000+ karyawan"), ("$318K+", "dampak terdokumentasi, tercantum di laporan tahunan 2024"), ("#1", "Global AI Demo Day 2026, Bengaluru")],
  "h_profile": "Profil",
  "profile": "Saya membawa AI ke operasional perusahaan. Di PT Sat Nusapersada Tbk, manufaktur elektronik tercatat di BEI untuk Xiaomi, Lenovo, Asus, dan Motorola, saya membangun departemen digital dari 3 menjadi 31 orang dan meluncurkan 56+ produk yang dipakai 5.000+ karyawan. Sebelumnya: 7 tahun di IT perusahaan di Singapura (MPA, Thales, DHL) dan startup transportasi laut bersama Kementerian Perhubungan. Sekarang, sebagai founder INDUSIA.ai, saya membangun dan menjalankan sistem AI dari ujung ke ujung: produk, server on-premise di pabrik, cloud, backup, dan keamanan. Saya juga melatih tim perusahaan dan menjadi pembicara AI terapan.",
  "h_exp": "Pengalaman",
  "jobs": [
   ("2026 – sekarang", "Batam · global", "Founder & CEO", "INDUSIA.ai · PT Indusia Kecerdasan Digital · AI untuk pabrik dan logistik", [
     "Membangun dan memasang sistem inspeksi PCB berbasis AI dua sisi pengganti AOI lama di sebuah manufaktur elektronik di Batam: 2 lini produksi berjalan, SLA deteksi cacat ≥95% sesuai IPC-A-610.",
     "Meluncurkan Smart Fleet, platform logistik yang berjalan di operator truk kontainer di Batam: gerbang RFID, telemetri solar, pasangan kontainer, e-POD otomatis.",
     "Menjalankan sendiri seluruh infrastruktur: server edge on-premise di lini pabrik (offline-first), cloud, CI/CD, backup, kontrol akses.",
     "Merancang solusi AI untuk klien manufaktur, logistik, FMCG, dan ritel; memimpin tim terdistribusi dan mitra integrator."]),
   ("2023 – 2025", "Batam", "Head of Digital Transformation & Process Automation", "PT Sat Nusapersada Tbk (EMS, tercatat di BEI)", [
     "Membangun dan memimpin tim lintas fungsi 31 engineer, desainer, dan analis yang menghasilkan 56+ produk AI, IoT, dan digital untuk 5.000+ karyawan.",
     "Dampak terdokumentasi $318K+ (laporan tahunan 2024) dan time-to-market 25% lebih cepat.",
     "Meluncurkan MySATNUSA, super-app untuk 5.000+ pengguna harian di 8 departemen: 30+ alur kerja, beban administrasi turun 40%, uptime 99%+.",
     "Inspeksi visual AI: akurasi 98%, proses 75% lebih cepat, hemat $200K+ per tahun; monitoring IoT menambah efisiensi 30%."]),
   ("2021 – 2023", "Batam", "Process Engineering Manager", "PT Sat Nusapersada Tbk", [
     "Produktivitas perakitan SMT naik 20%; pass rate +25% dan reject −20% dengan SPI dan AOI.",
     "Memimpin integrasi SMT–MES secara Agile: efisiensi operasional naik 35%, efisiensi lini 99%."]),
   ("2016 – 2019", "Batam", "Co-Founder & CEO", "Marlin Booking · teknologi transportasi laut", [
     "Digitalisasi operasional pelabuhan penumpang bersama Kementerian Perhubungan di 5 pelabuhan.",
     "Tiket mobile rute feri Singapura–Malaysia: kepuasan pelanggan +20%, penjualan +15%. Pendanaan $250K pada valuasi $5M."]),
   ("2008 – 2015", "Singapura · 7 tahun", "IT Perusahaan", "MPA Singapore · Thales (eks Gemalto) · DHL Supply Chain · eXSYS", [
     "Pengembangan aplikasi, solution support, dan software engineering untuk sistem otoritas pelabuhan, telko, dan supply chain di Asia Pasifik (J2EE, Oracle, Linux).",
     "DHL: downtime −25%, nol pelanggaran keamanan selama dua tahun."]),
  ],
  "h_built": "Karya pilihan di INDUSIA.ai",
  "built": [("Inspeksi Visual PCB berbasis AI", "Berjalan. Deteksi cacat dua sisi di perangkat edge, ≤30 ms per gambar, sepenuhnya on-premise."),
            ("Smart Fleet", "Berjalan. Platform truk kontainer: gerbang RFID, telemetri solar, e-POD, asisten suara pemilik."),
            ("Sparkfluence", "Berjalan. SaaS video pendek berbasis AI; juara Global AI Demo Day 2026."),
            ("Mesin konten otomatis", "Berjalan. alisadikinma.com meriset, menulis, dan menerbitkan dengan persetujuan manusia."),
            ("Indusia Accounting & Finance", "Selesai dibangun. Akuntansi desktop lama pindah ke web; aplikasi pajak Indonesia."),
            ("Merchant POS & Absensi SaaS", "Selesai dibangun. POS multi-tenant siap QRIS; absensi wajah + GPS.")],
  "h_rec": "Penghargaan",
  "awards": [("#1", "Global AI Demo Day 2026", "Bengaluru, India. Sparkfluence terpilih dari 26 startup."),
             ("1/48", "UN-UNCTAD × Alibaba eFounders", "Hangzhou, Tiongkok, 2019. Satu dari 48 founder asal 16 negara."),
             ("#1", "Telkomsel The NextDev 2018", "Kategori transportasi bersama Marlin Booking."),
             ("WC", "Fenox Startup World Cup 2017", "Pemenang Wild Card; grand final di Silicon Valley.")],
  "h_skills": "Keahlian",
  "skills": ["AI agent", "RAG", "Computer vision (YOLO, deteksi anomali)", "Edge AI (Jetson)", "LLM lokal", "RPA / n8n", "SMT · MES · AOI/SPI", "IPC-A-610", "PLC (Modbus TCP / OPC-UA)", "Monitoring IoT", "Laravel", "Vue / React / Next.js", "NestJS", "FastAPI / Python", "PostgreSQL", "Flutter", "Docker / Linux", "CI/CD", "Agile / Jira", "Roadmap digital & ROI"],
  "h_edu": "Pendidikan & sertifikasi",
  "edu": ["BINUS University — Sarjana Teknik Informatika, cum laude (2019)", "Politeknik Caltex Riau — Diploma Teknik Komputer, cum laude (2007)", "Google Project Management: Professional Certificate", "Outskill AI Generalist Fellowship", "UN-UNCTAD × Alibaba eFounders Fellowship (2019)"],
  "h_lang": "Bahasa",
  "langs": ["Indonesia — bahasa ibu", "Inggris — profesional (7 tahun di Singapura)", "Mandarin — percakapan"],
  "foot": "Ali Sadikin Ma · Resume",
 },
}
CONTACT = ["alisadikinmaai@gmail.com", "+62 813-6811-811 (WhatsApp)", "alisadikinma.com", "linkedin.com/in/alisadikinma"]
e = html.escape

def build(t):
    stats = "".join(f'<div class="stat"><b>{e(a)}</b><span>{e(b)}</span></div>' for a, b in t["stats"])
    jobl = [f'<div class="job"><div class="when"><b>{e(w)}</b>{e(p)}</div><div><h3>{e(r)}</h3><div class="org">{e(o)}</div><ul>{"".join(f"<li>{e(x)}</li>" for x in bs)}</ul></div></div>' for w, p, r, o, bs in t["jobs"]]
    built = "".join(f'<li><b>{e(a)}</b> — {e(b)}</li>' for a, b in t["built"])
    awards = "".join(f'<div class="aw"><b>{e(a)}</b><strong>{e(b)}</strong>{e(c)}</div>' for a, b, c in t["awards"])
    skills = "".join(f"<span>{e(s)}</span>" for s in t["skills"])
    edu = "".join(f"<li>{e(x)}</li>" for x in t["edu"])
    langs = "".join(f"<li>{e(x)}</li>" for x in t["langs"])
    contact = "<br>".join(e(c) for c in CONTACT)
    jobs1 = "".join(jobl[:3])
    jobs2 = "".join(jobl[3:])
    return f"""<!doctype html><html lang="{t['lang']}"><head><meta charset="utf-8"><title>{e(t['title'])}</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
<style>{CSS}</style></head><body>
<section class="page">
 <div class="top"><div><h1>Ali Sadikin Ma</h1><div class="role">{e(t['role'])}</div><div class="sub">{e(t['loc'])}</div></div><div class="contact">{contact}</div></div>
 <div class="stats">{stats}</div>
 <h2>{e(t['h_profile'])}</h2><p>{e(t['profile'])}</p>
 <h2>{e(t['h_exp'])}</h2>{jobs1}
 <div class="foot"><span>{e(t['foot'])}</span><span>1/2</span></div>
</section>
<section class="page">
 <h2 style="margin-top:0">{e(t['h_exp'])} ({'cont.' if t['lang']=='en' else 'lanjutan'})</h2>{jobs2}
 <h2>{e(t['h_built'])}</h2><ul>{built}</ul>
 <h2>{e(t['h_rec'])}</h2><div class="awards">{awards}</div>
 <h2>{e(t['h_skills'])}</h2><div class="chips">{skills}</div>
 <div class="cols"><div><h2>{e(t['h_edu'])}</h2><ul>{edu}</ul></div><div><h2>{e(t['h_lang'])}</h2><ul>{langs}</ul></div></div>
 <div class="foot"><span>{e(t['foot'])}</span><span>2/2</span></div>
</section></body></html>"""

for k, t in T.items():
    open(f"resume-{k}.html", "w").write(build(t))
print("ok")

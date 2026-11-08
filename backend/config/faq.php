<?php

/*
|--------------------------------------------------------------------------
| FAQ — single source of truth (GEO Pillar 2)
|--------------------------------------------------------------------------
|
| One curated Q&A set feeding THREE surfaces from this one place:
|   - SSR /faq    → SpaPrerenderController::faq (crawlable <dl> + FAQPage JSON-LD)
|   - GET /api/faq → FaqController (the Vue view fetches this)
|   - Vue /faq    → FaqView.vue (real humans)
|
| GEO requirement: every answer is ANSWER-FIRST and STANDALONE — it must make
| sense pulled out of context (an AI engine may quote a single answer with no
| surrounding page). Use the full name "Ali Sadikin Ma", never "we"/"I". Keep
| answers 1-3 sentences, concrete, no marketing fluff. Plain text only (the
| Blade view escapes with {{ }} and the FAQPage builder embeds answers as-is).
|
*/

return [
    'items' => [
        [
            'question' => 'Who is Ali Sadikin Ma?',
            'answer' => 'Ali Sadikin Ma is an AI Generalist who turns frontier AI models into shipped products rather than slide decks. He has 17 years of building experience across 16 countries and ranked #1 at the Global AI Demo Day 2026. He is based in Indonesia and both builds and teaches applied AI.',
        ],
        [
            'question' => 'What services does Ali Sadikin Ma offer?',
            'answer' => 'Ali Sadikin Ma builds AI Agents and AI agent operating systems, Generative AI Video pipelines, AI automation workflows, and Computer Vision systems for AI visual inspection. He also teaches Vibe Coding — shipping real software fast with AI as the primary tool.',
        ],
        [
            'question' => 'What makes Ali Sadikin Ma\'s approach different?',
            'answer' => 'Ali Sadikin Ma optimizes for shipped, working products over demos and slideware. He works as a hands-on generalist who spans strategy, engineering, and delivery, so a project moves from idea to production without being handed off across siloed specialists.',
        ],
        [
            'question' => 'Who does Ali Sadikin Ma work with?',
            'answer' => 'Ali Sadikin Ma works with founders, product teams, and manufacturers who want to put AI into production — from startups adding AI agents and generative media to electronics manufacturers deploying AI visual inspection on their lines. He also mentors teams and individuals learning to build with AI.',
        ],
        [
            'question' => 'What is AI visual inspection and does Ali Sadikin Ma build it?',
            'answer' => 'AI visual inspection uses computer vision to detect manufacturing defects — such as PCB and PCBA faults on electronics lines — automatically and at scale. Ali Sadikin Ma designs and deploys these systems, selecting the right detection models per defect type and tuning them to factory quality standards.',
        ],
        [
            'question' => 'What is Vibe Coding and does Ali Sadikin Ma teach it?',
            'answer' => 'Vibe Coding is the practice of building real, working software primarily by directing AI coding tools instead of writing every line by hand. Ali Sadikin Ma teaches Vibe Coding and uses it daily to ship products faster than traditional development.',
        ],
        [
            'question' => 'What technology stack does Ali Sadikin Ma use?',
            'answer' => 'Ali Sadikin Ma builds with large language model applications and AI agents, generative image and video models, and computer-vision and edge-AI stacks for inspection. On the engineering side his production work spans modern web stacks such as Laravel and Vue alongside automation and AI-orchestration pipelines.',
        ],
        [
            'question' => 'What results has Ali Sadikin Ma delivered?',
            'answer' => 'Ali Sadikin Ma ranked #1 at the Global AI Demo Day 2026 and has delivered AI and software projects across 16 countries over 17 years. His portfolio includes production AI agents, generative video pipelines, and computer-vision inspection systems running in real operations.',
        ],
        [
            'question' => 'Does Ali Sadikin Ma teach or speak about AI?',
            'answer' => 'Yes. Ali Sadikin Ma teaches and publishes regularly on AI Agents, Vibe Coding, and Generative AI Video, and has spoken on international stages. He shares applied, build-first material rather than abstract theory.',
        ],
        [
            'question' => 'How can I contact or work with Ali Sadikin Ma?',
            'answer' => 'You can reach Ali Sadikin Ma through the contact page at alisadikinma.com or connect with him on LinkedIn, Instagram, TikTok, and YouTube at @alisadikinma. He responds to project inquiries, collaboration, and speaking or training requests.',
        ],
        [
            'question' => 'In which languages does Ali Sadikin Ma work?',
            'answer' => 'Ali Sadikin Ma works in Indonesian and English, and also knows Mandarin. His content and case studies are published in both English and Indonesian.',
        ],
    ],
    // Indonesian mirror of 'items' — same order, same facts, one-to-one (served for ?lang=id).
    // Translation of the EN set; awaiting Ali's copy approval (docs/copy/PF-2-phase1.md).
    'items_id' => [
        [
            'question' => 'Siapa Ali Sadikin Ma?',
            'answer' => 'Ali Sadikin Ma adalah AI Generalist yang mengubah model AI terbaru menjadi produk yang benar-benar dipakai, bukan sekadar slide presentasi. Ia punya pengalaman membangun produk selama 17 tahun di 16 negara dan meraih peringkat 1 di Global AI Demo Day 2026. Ia berbasis di Indonesia, membangun sekaligus mengajarkan AI terapan.',
        ],
        [
            'question' => 'Layanan apa saja yang ditawarkan Ali Sadikin Ma?',
            'answer' => 'Ali Sadikin Ma membangun AI Agent dan sistem operasi AI agent, pipeline Generative AI Video, alur kerja otomasi AI, dan sistem Computer Vision untuk inspeksi visual berbasis AI. Ia juga mengajarkan Vibe Coding, yaitu cara membuat software sungguhan dengan cepat memakai AI sebagai alat utama.',
        ],
        [
            'question' => 'Apa yang membedakan pendekatan Ali Sadikin Ma?',
            'answer' => 'Ali Sadikin Ma mengutamakan produk yang jadi dan berjalan, bukan demo atau slide. Ia bekerja langsung sebagai generalist yang menangani strategi, engineering, dan delivery, sehingga proyek bergerak dari ide ke produksi tanpa dioper ke banyak spesialis yang terpisah.',
        ],
        [
            'question' => 'Dengan siapa saja Ali Sadikin Ma bekerja?',
            'answer' => 'Ali Sadikin Ma bekerja dengan founder, tim produk, dan pabrik yang ingin menjalankan AI di produksi, mulai dari startup yang menambah AI agent dan media generatif sampai pabrik elektronik yang memasang inspeksi visual berbasis AI di lini produksinya. Ia juga membimbing tim dan perorangan yang sedang belajar membangun dengan AI.',
        ],
        [
            'question' => 'Apa itu inspeksi visual berbasis AI dan apakah Ali Sadikin Ma membangunnya?',
            'answer' => 'Inspeksi visual berbasis AI memakai computer vision untuk mendeteksi cacat produksi, misalnya cacat PCB dan PCBA di lini elektronik, secara otomatis dan dalam skala besar. Ali Sadikin Ma merancang dan memasang sistem ini, memilih model deteksi yang tepat untuk tiap jenis cacat, lalu menyetelnya sesuai standar mutu pabrik.',
        ],
        [
            'question' => 'Apa itu Vibe Coding dan apakah Ali Sadikin Ma mengajarkannya?',
            'answer' => 'Vibe Coding adalah cara membuat software yang benar-benar berjalan dengan mengarahkan alat coding AI, bukan menulis setiap baris kode sendiri. Ali Sadikin Ma mengajarkan Vibe Coding dan memakainya setiap hari untuk merilis produk lebih cepat daripada pengembangan cara lama.',
        ],
        [
            'question' => 'Teknologi apa yang dipakai Ali Sadikin Ma?',
            'answer' => 'Ali Sadikin Ma membangun aplikasi large language model dan AI agent, model generatif gambar dan video, serta stack computer vision dan edge AI untuk inspeksi. Di sisi engineering, pekerjaan produksinya mencakup stack web modern seperti Laravel dan Vue, ditambah pipeline otomasi dan orkestrasi AI.',
        ],
        [
            'question' => 'Hasil apa yang sudah diberikan Ali Sadikin Ma?',
            'answer' => 'Ali Sadikin Ma meraih peringkat 1 di Global AI Demo Day 2026 dan sudah menyelesaikan proyek AI dan software di 16 negara selama 17 tahun. Portofolionya mencakup AI agent di produksi, pipeline video generatif, dan sistem inspeksi computer vision yang berjalan di operasi nyata.',
        ],
        [
            'question' => 'Apakah Ali Sadikin Ma mengajar atau menjadi pembicara tentang AI?',
            'answer' => 'Ya. Ali Sadikin Ma rutin mengajar dan menerbitkan materi tentang AI Agent, Vibe Coding, dan Generative AI Video, dan pernah tampil di panggung internasional. Materinya praktis dan berbasis membangun, bukan teori abstrak.',
        ],
        [
            'question' => 'Bagaimana cara menghubungi atau bekerja sama dengan Ali Sadikin Ma?',
            'answer' => 'Anda bisa menghubungi Ali Sadikin Ma lewat halaman kontak di alisadikinma.com atau lewat LinkedIn, Instagram, TikTok, dan YouTube di @alisadikinma. Ia menanggapi permintaan proyek, kolaborasi, serta undangan pembicara atau pelatihan.',
        ],
        [
            'question' => 'Dalam bahasa apa saja Ali Sadikin Ma bekerja?',
            'answer' => 'Ali Sadikin Ma bekerja dalam bahasa Indonesia dan Inggris, dan juga menguasai bahasa Mandarin. Konten dan studi kasusnya terbit dalam bahasa Inggris dan Indonesia.',
        ],
    ],
];

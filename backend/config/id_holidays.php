<?php

/**
 * Indonesian national holidays + cuti bersama, keyed by year then 'Y-m-d'.
 *
 * Static list (NO API) — consumed by App\Services\IndonesianHolidayService so
 * the LinkedIn fixed-slot scheduler never suggests a posting slot on a public
 * holiday. Weekend-falling dates are kept verbatim from the SKB list (harmless
 * — the scheduler's weekend rule already excludes them).
 *
 * Maintenance: append a new year before it arrives. A year with no entry here
 * degrades to weekend-only + logs a warning (see IndonesianHolidayService).
 */

return [

    // 2026 — official (SKB 3 Menteri).
    2026 => [
        '2026-01-01' => 'Tahun Baru Masehi',
        '2026-01-16' => "Isra Mikraj Nabi Muhammad SAW",
        '2026-02-17' => 'Tahun Baru Imlek 2577 Kongzili',
        '2026-03-19' => 'Hari Suci Nyepi (Tahun Baru Saka 1948)',
        '2026-03-21' => 'Hari Raya Idulfitri 1447 H',
        '2026-03-22' => 'Hari Raya Idulfitri 1447 H',
        '2026-04-03' => 'Wafat Yesus Kristus (Jumat Agung)',
        '2026-04-05' => 'Kebangkitan Yesus Kristus (Paskah)',
        '2026-05-01' => 'Hari Buruh Internasional',
        '2026-05-14' => 'Kenaikan Yesus Kristus',
        '2026-05-27' => 'Hari Raya Iduladha 1447 H',
        '2026-05-31' => 'Hari Raya Waisak 2570 BE',
        '2026-06-01' => 'Hari Lahir Pancasila',
        '2026-06-16' => 'Tahun Baru Islam 1448 H',
        '2026-08-17' => 'Hari Kemerdekaan Republik Indonesia',
        '2026-08-25' => 'Maulid Nabi Muhammad SAW',
        '2026-12-25' => 'Hari Raya Natal',
    ],

    // 2027 — ESTIMATE — verify against the official SKB 3 Menteri when released
    // (lunar dates may shift ±1 day; cuti bersama not yet decreed).
    2027 => [
        '2027-01-01' => 'Tahun Baru Masehi',
        '2027-01-05' => "Isra Mikraj Nabi Muhammad SAW",
        '2027-02-06' => 'Tahun Baru Imlek 2578 Kongzili',
        '2027-03-09' => 'Hari Suci Nyepi (Tahun Baru Saka 1949)',
        '2027-03-10' => 'Hari Raya Idulfitri 1448 H',
        '2027-03-11' => 'Hari Raya Idulfitri 1448 H',
        '2027-03-26' => 'Wafat Yesus Kristus (Jumat Agung)',
        '2027-03-28' => 'Kebangkitan Yesus Kristus (Paskah)',
        '2027-05-01' => 'Hari Buruh Internasional',
        '2027-05-06' => 'Kenaikan Yesus Kristus',
        '2027-05-17' => 'Hari Raya Iduladha 1448 H',
        '2027-05-20' => 'Hari Raya Waisak 2571 BE',
        '2027-06-01' => 'Hari Lahir Pancasila',
        '2027-06-06' => 'Tahun Baru Islam 1449 H',
        '2027-08-15' => 'Maulid Nabi Muhammad SAW',
        '2027-08-17' => 'Hari Kemerdekaan Republik Indonesia',
        '2027-12-25' => 'Hari Raya Natal',
        '2027-12-26' => 'Isra Mikraj Nabi Muhammad SAW (bagian 2)',
    ],

];

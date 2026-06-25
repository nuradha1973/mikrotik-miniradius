# MiniRadius

**MikroTik & FreeRADIUS Web Panel** — Management dashboard untuk RouterOS v7 dan FreeRADIUS 3.x.

A single-file PHP application untuk mengelola user PPPoE/Hotspot, profile kecepatan, memonitor traffic secara real-time, dan melihat log autentikasi RADIUS.

## Fitur

- 📊 **Dashboard** — Monitoring live traffic (Chart.js), resource router, dan sesi user aktif
- 👥 **Manajemen User** — CRUD user PPPoE & Hotspot dengan mapping profile kecepatan
- ⚡ **Profile Editor** — Atur rate limit (MikroTik-Rate-Limit) per group/profile
- 🔒 **User Hotspot / PPPoE** — Filter dan lihat user berdasarkan layanan
- 📋 **Log Sistem** — Viewer FreeRADIUS log dengan warna dan filter
- 🔧 **Connection Setting** — Konfigurasi database FreeRADIUS dan REST API MikroTik
- 🎮 **Demo Mode** — Mode simulasi tanpa koneksi database/router (untuk testing)

## Teknologi

- **Backend:** PHP 7.4+ (single file)
- **Database:** MySQL / MariaDB dengan schema FreeRADIUS 3.x
- **Frontend:** Tailwind CSS 3 (CDN), Chart.js, Plus Jakarta Sans
- **Integrasi:** MikroTik RouterOS v7 REST API, FreeRADIUS 3.2.x

## Struktur Proyek

```
miniradius/
├── index.php                 # Aplikasi utama (single-file PHP)
├── config.php                # Konfigurasi koneksi (DB, MikroTik, dll)
├── setup.sql                 # Schema database FreeRADIUS + MiniRadius
├── README.md
├── setup_guide.md
├── assets/
│   ├── style.css             # Custom styling + notifikasi
│   └── script.js             # Client-side interaktif (chart, toggle, dll)
└── data/
    ├── mock_session_data.php # Data mock untuk demo mode
    ├── mock_radius_logs.php  # Log FreeRADIUS simulasi
    └── mock_radius_logs.txt  # Contoh file log (.txt)
```

## Lisensi

© 2026 Ken Dedes (GC Network Labs) — [gcnetwork.my.id](https://www.gcnetwork.my.id)

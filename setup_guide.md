# Panduan Setup MiniRadius

## Prasyarat

- **Web Server:** Apache / Nginx + PHP 7.4 atau lebih baru
- **PHP Extensions:** `mysqli`, `curl`, `json`, `session`
- **Database:** MySQL 5.7+ / MariaDB 10.3+
- **FreeRADIUS:** 3.2.x (dengan module `rlm_sql_mysql`)
- **MikroTik:** RouterOS v7.x dengan REST API diaktifkan (opsional)

---

## 1. Setup Database

Jalankan file `setup.sql` ke MySQL:

```bash
mysql -u root -p < setup.sql
```

Perintah ini akan membuat:
- Database `radius_db`
- 9 tabel FreeRADIUS + MiniRadius (`radcheck`, `radreply`, `radgroupcheck`, `radgroupreply`, `radusergroup`, `radacct`, `radpostauth`, `nas`, `miniradius_user_status`)

> **Catatan:** Tidak ada data awal yang dimasukkan. Semua data diisi melalui panel MiniRadius.

### Verifikasi

```sql
SHOW TABLES FROM radius_db;
```

Harus muncul 9 tabel.

---

## 2. Konfigurasi FreeRADIUS

Pastikan file `sql.conf` / `mods-config/sql/main/mysql/queries.conf` FreeRADIUS Anda menggunakan schema yang sesuai dengan tabel-tabel di atas.

### Contoh konfigurasi `mods-enabled/sql`

```nginx
sql {
    driver = "rlm_sql_mysql"
    dialect = "mysql"
    server = "localhost"
    port = 3306
    login = "root"
    password = "password_anda"
    radius_db = "radius_db"
}
```

### Konfigurasi NAS Client di FreeRADIUS

Edit `/etc/freeradius/clients.conf` atau gunakan tabel `nas`:

```bash
client 192.168.100.1 {
    secret = radius123
    shortname = MikroTik-L009
    nastype = mikrotik
}
```

Atau isi otomatis melalui panel MiniRadius > Connection Setting.

---

## 3. Deploy Aplikasi

Salin semua file ke document root web server:

```bash
cp -r miniradius/ /var/www/html/
```

Atau di Windows (XAMPP):

```
x:\xampp\htdocs\miniradius\
```

### Struktur yang diperlukan

```
/var/www/html/miniradius/
├── index.php
├── config.php
├── assets/
│   ├── style.css
│   └── script.js
└── data/
    ├── mock_session_data.php
    ├── mock_radius_logs.php
    └── mock_radius_logs.txt
```

Pastikan web server memiliki izin **baca** untuk semua file dan izin **tulis** untuk `config.php`.

---

## 4. Akses Panel

Buka browser:

```
http://localhost/miniradius/
```

Atau jika di server:

```
http://ip-anda/miniradius/
```

---

## 5. Konfigurasi Koneksi

1. Klik menu **Connection Setting** di sidebar
2. Isi parameter koneksi database FreeRADIUS
3. Isi parameter REST API MikroTik (opsional, untuk monitoring live)
4. Klik **Simpan Semua Konfigurasi**

### Parameter Database

| Field | Contoh |
|-------|--------|
| DB Host | `localhost` |
| DB Name | `radius_db` |
| DB User | `root` |
| DB Password | `password_anda` |

### Parameter MikroTik REST API

| Field | Contoh |
|-------|--------|
| Router IP | `192.168.100.1` |
| Protocol | `http` (atau `https`) |
| Port API | `80` (atau `443` untuk https) |
| API Username | `admin` |
| API Password | `password_router` |

> **Aktifkan REST API di MikroTik:**
> ```
> /ip/service set www-ssl disabled=no port=443
> /user set admin password=password_anda
> /user group set full write=yes read=yes
> ```

---

## 6. Demo Mode

Gunakan toggle **Force Mock Mode** di Connection Setting untuk mengaktifkan mode demo.

Saat demo mode aktif:
- ✅ Tidak perlu koneksi database
- ✅ Tidak perlu koneksi MikroTik
- ✅ Data menggunakan simulasi (mock)
- ✅ Semua fitur CRUD bisa dicoba (data tersimpan di session)

---

## 7. Troubleshooting

### Error 500 — Internal Server Error

1. Periksa error log PHP: `tail -f /var/log/apache2/error.log`
2. Pastikan ekstensi PHP `mysqli` dan `curl` sudah diaktifkan
3. Pastikan `config.php` bisa ditulis oleh web server

### Database connection failed

1. Periksa apakah MySQL/MariaDB berjalan
2. Jalankan ulang `setup.sql` untuk memastikan tabel sudah dibuat
3. Periksa kredensial di Connection Setting

### Tabel `miniradius_user_status` tidak ada

Jalankan ulang `setup.sql` yang sudah diperbarui (tanpa `-- ====` dekoratif).

### FreeRADIUS gagal autentikasi

Pastikan tidak ada atribut asing di tabel `radcheck`. Hanya gunakan:
- `Cleartext-Password` — untuk password
- Atribut standar FreeRADIUS lainnya

Status user (`active`/`disabled`) disimpan di `miniradius_user_status`, **bukan** di `radcheck`.

---

## 8. Informasi Tambahan

- **Author:** Ken Dedes (GC Network Labs)
- **Website:** [https://www.gcnetwork.my.id](https://www.gcnetwork.my.id)
- **Router:** L009UiGS-2HaxD-IN — RouterOS v7.23
- **FreeRADIUS:** 3.2.5

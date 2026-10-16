# Struktur project web Smart Waste

Project ini adalah aplikasi web masyarakat, PHP REST API, dan skema MySQL. Android admin/petugas memakai API PHP yang sama.

```text
Waste 2/
├── frontend/                   # Website user: HTML, CSS, JavaScript
│   ├── index.html
│   ├── css/style.css
│   └── js/app.js
├── backend-php/                # API publik; path URL dipertahankan
│   ├── index.php               # Front controller dan route API
│   ├── .env.example            # Templat konfigurasi lokal (tanpa rahasia)
│   ├── config/
│   │   ├── database.php        # PDO, schema bootstrap, migrasi, seed demo
│   │   └── environment.php     # Memuat variabel dari .env lokal
│   ├── auth/google_id_token.php# Verifikasi Google ID token
│   ├── database/schema.sql     # Referensi skema untuk inspeksi/import manual
│   └── uploads/                # Foto laporan; isi nyata tidak masuk Git
├── docs/                       # Arsitektur, API, dan panduan developer
├── tools/validate-web.ps1      # Pemeriksaan sintaks lokal
├── README.md
└── START-Waste2.bat
```

## Kontrak yang jangan diputus

- Alamat entry point tetap `backend-php/index.php`; URL ini dipakai `frontend/js/app.js` dan Android.
- Folder website tetap `frontend/`, karena URL lokalnya `/Waste2/frontend/index.html`.
- Foto laporan tetap disimpan di `backend-php/uploads/` agar URL foto yang dikirim API tetap valid.
- Database adalah MySQL `smart_waste`. Perubahan tabel harus menjaga data instalasi lama; jangan menghapus atau mengganti tabel tanpa migrasi.
- Saat endpoint ditambah, perbarui `docs/api-contract.md` dan client yang menggunakannya.

## Catatan skema

Runtime menjalankan `initialiseSchema()` secara idempoten di `config/database.php` untuk menjaga instalasi demo/lama tetap bisa naik versi. `database/schema.sql` adalah salinan skema untuk dibaca atau diimpor manual; perbarui keduanya bersama-sama. Perubahan struktur baru sebaiknya dibuat sebagai migrasi bernomor sebelum dipakai pada data yang sudah ada.

Konfigurasi lokal disimpan dalam `backend-php/.env` (salin dari `.env.example`). File tersebut diabaikan Git dan akses HTTP-nya ditolak oleh `.htaccess`; environment Windows yang sudah ada tetap menjadi prioritas. Jangan masukkan private key ke APK atau commit file `.env`.

# Struktur project web Smart Waste

Project ini adalah aplikasi web masyarakat, PHP REST API, dan skema MySQL bersama. Android admin/petugas memakai API PHP yang sama.

```text
Safaras Web/
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
│   ├── database/schema.sql     # Skema runtime versi 3, seluruh 19 tabel
│   ├── auth/                   # Aturan akses PHP
│   ├── services/               # Layanan pendukung API
│   └── uploads/                # Foto laporan; isi nyata tidak masuk Git
├── docs/                       # Arsitektur, API, alur kerja, ERD
├── tools/validate-web.ps1      # Pemeriksaan sintaks lokal
├── README.md
└── START-Waste2.bat
```

## Kontrak yang jangan diputus

- Alamat entry point tetap `backend-php/index.php`; URL ini dipakai `frontend/js/app.js` dan Android.
- Folder website tetap `frontend/`, karena URL lokalnya `/Waste2/frontend/index.html`.
- Foto laporan tetap disimpan di `backend-php/uploads/` agar URL foto yang dikirim API tetap valid.
- Database bersama adalah MySQL `smart_waste` pada port lokal `3307`. Data website dan Android ditulis melalui API yang sama.
- Data foto berada di `backend-php/uploads/`; kolom foto di database menyimpan path berkas tersebut.
- Saat endpoint ditambah, perbarui `docs/api-contract.md` dan client yang menggunakannya.

## Catatan skema

Runtime menjalankan `initialiseSchema()` secara idempoten di `config/database.php` untuk menjaga instalasi demo/lama tetap bisa naik versi. Versi aktif adalah 3 dan skema berisi 19 tabel. `database/schema.sql` mengikuti tabel runtime; perbarui keduanya bersama-sama. ERD mendokumentasikan relasi logis tanpa menambahkan constraint yang mengubah aturan hapus/arsip aplikasi.

Konfigurasi lokal disimpan dalam `backend-php/.env` (salin dari `.env.example`). File tersebut diabaikan Git dan akses HTTP-nya ditolak oleh `.htaccess`; environment Windows yang sudah ada tetap menjadi prioritas. Jangan masukkan private key ke APK atau commit file `.env`.

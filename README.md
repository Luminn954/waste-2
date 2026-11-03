# Waste 2 — Smart Waste

Smart Waste memakai satu alur data bersama:

`Website User → PHP REST API → MySQL XAMPP ← PHP REST API ← Android Admin/Petugas`

Website dipakai masyarakat untuk membuat dan memantau laporan. Android dipakai Admin untuk verifikasi dan operasional, serta Petugas untuk menjalankan penugasan.

Kode dipisah berdasarkan peran: `frontend/` untuk website, `backend-php/` untuk API dan database bootstrap, serta `docs/` dan `tools/` untuk kontrak/panduan dan validasi. Lihat [peta struktur](docs/structure.md), [kontrak API](docs/api-contract.md), dan [alur pengembangan](docs/development-workflow.md).

## Menjalankan di Windows

1. Buka XAMPP, nyalakan **Apache** dan **MySQL**.
2. Jalankan `START-Waste2.bat`, atau buka `http://localhost:8080/Waste2/frontend/index.html`.
3. Default XAMPP memakai MySQL `127.0.0.1:3306`, database `smart_waste`, user `root`, password kosong. Database dan schema bootstrap dibuat saat API pertama kali dipanggil.
   Untuk konfigurasi lokal, salin `backend-php/.env.example` menjadi `backend-php/.env`; PHP akan membaca file itu, sementara environment Windows yang sudah ada tetap lebih diprioritaskan. `.env` diabaikan Git dan ditolak oleh Apache.
4. Untuk Android emulator, biarkan Apache/MySQL tetap aktif. Emulator memakai `10.0.2.2` sebagai alamat localhost laptop.
5. Untuk membuka proyek Android dengan workaround Gradle Windows, jalankan `OPEN-AndroidStudio-SmartWaste.bat` dari folder proyek Android. Script hanya mengatur `TEMP`/`TMP` ke `C:\Temp` untuk proses Android Studio itu; pengaturan Windows tidak diubah.

Folder web sudah dipasang sebagai junction:

```text
C:\xampp\htdocs\Waste2  ->  folder Waste 2 ini
```

Endpoint pemeriksaan:

- Web user: `http://localhost:8080/Waste2/frontend/index.html`
- Health API: `http://localhost:8080/Waste2/backend-php/index.php/health`
- phpMyAdmin: `http://localhost:8080/phpmyadmin/`

API entry point sengaja tetap di `backend-php/index.php` supaya website dan Android tidak kehilangan alamat server saat file internal dirapikan. Schema untuk inspeksi/import manual berada di `backend-php/database/schema.sql`; runtime memakai migrasi idempoten di `backend-php/config/database.php` agar data lama tetap dipertahankan.

## Akun dan login

Semua akun masuk memakai username dan kata sandi. Google OAuth tidak digunakan.

- Website menerima role `user` untuk registrasi dan laporan warga.
- Android menerima role `admin` dan `petugas`; login silang ditolak.
- Admin membuat akun Petugas melalui menu Operasional. Pendaftaran publik hanya membuat akun warga.
- Pendaftaran akun baru dan penggantian kata sandi memerlukan minimal 8 karakter. Kata sandi akun lama tidak berubah.
- Login dibatasi setelah 5 kata sandi salah untuk pasangan identitas akun dan alamat IP yang sama; percobaan baru dapat dilakukan kembali setelah 15 menit.
- Untuk database baru, isi `SMARTWASTE_BOOTSTRAP_ADMIN_PASSWORD` di `backend-php/.env` sebelum API pertama kali dijalankan. Seeder membuat akun `admin` hanya jika kata sandi minimal 8 karakter tersedia; akun yang sudah ada tidak diubah.
- Username `admin` dicadangkan dan tidak dapat dipakai saat pendaftaran warga.
- Akun demo warga `user` / `12345` dan laporan contoh hanya dibuat jika `SMARTWASTE_SEED_DEMO_USER=1` disetel untuk demo lokal. Biarkan `0` untuk penggunaan biasa; jangan memakai kredensial demo pada deployment.
- Migrasi tidak mereset kata sandi database lama. Jika Admin lama masih memakai kata sandi demo, ganti lewat aplikasi sebelum membuka server ke jaringan.

Pengaturan koneksi database XAMPP dapat memakai `SMARTWASTE_DB_HOST`, `SMARTWASTE_DB_PORT`, `SMARTWASTE_DB_NAME`, `SMARTWASTE_DB_USER`, dan `SMARTWASTE_DB_PASSWORD` melalui konfigurasi environment lokal. Jangan commit file `.env` atau kredensial.

## Fitur inti

### Website — User

- Register, login, dan logout.
- Kirim laporan foto, jenis sampah, lokasi, berat, deskripsi, dan tanggal.
- Melihat status `Menunggu Verifikasi`, `Diproses`, `Ditugaskan`, `Diangkut`, `Selesai`, atau `Ditolak`.
- Riwayat laporan dan timeline status.
- Profil, edit data kontak, dan ganti password.
- Sinkronisasi status otomatis setiap 15 detik.

### Android — Admin

- Login admin.
- Dashboard total user, laporan, menunggu, diproses, selesai, dan ditolak.
- Lihat detail laporan, lokasi, deskripsi, dan buka foto.
- Pantau laporan warga, tinjau bukti penyelesaian, dan setujui/tolak proses perbaikan armada.
- Kelola akun Petugas, catatan Admin, statistik, dan kinerja operasional.
- Statistik per bulan, jenis sampah, berat, dan laporan selesai.
- Refresh otomatis setiap 15 detik.

### Android — Petugas

- Login dengan akun petugas yang dibuat Admin.
- Menangani antrean laporan: pilih kendaraan dan TPA, atau tolak dengan alasan.
- Memperbarui alur `Ditugaskan → Diangkut → Selesai` dengan bukti foto untuk konfirmasi penyelesaian.
- Mengelola armada/TPA operasional dan melaporkan kerusakan kendaraan untuk persetujuan Admin.
- Melihat kinerja serta mengarsipkan dan memulihkan tugas.
- Tidak dapat membuka menu administrasi atau mengubah penugasan petugas lain.

## Struktur database utama

- `users` — akun user dan role.
- `admin` — profil admin yang tersinkron dengan akun admin.
- `sampah` — laporan sampah user.
- `riwayat_status` — jejak perubahan status oleh admin.
- `auth_tokens` — sesi login API.

Tabel pendukung seperti `petugas`, `kendaraan`, `tpa`, dan `laporan_harian` tetap dipertahankan supaya database XAMPP lama tidak kehilangan data saat migrasi. Setiap perubahan skema baru harus ditambahkan ke bootstrap runtime dan referensi `backend-php/database/schema.sql`; untuk data yang sudah dipakai, gunakan migrasi yang bisa dijalankan berulang tanpa menghapus data.

## Android lewat Wi-Fi yang sama

Aplikasi Android mengakses data melalui REST API PHP di Apache; aplikasi tidak terhubung langsung ke MySQL. Untuk HP fisik, jalankan `scripts/auto-deploy-phone.ps1` dari folder proyek Android. Skrip mendeteksi IP Wi-Fi laptop, membangun APK dengan alamat API LAN, lalu menerbitkan APK dan `updates/latest.json` ke folder web ini. Jika ada satu HP tersambung USB, APK juga dipasang otomatis.

Perangkat perlu berada di jaringan lokal yang sama. Jangan membuka port MySQL `3306` ke Wi-Fi. Firewall laptop saat ini memiliki aturan Apache Public yang terlalu lebar (TCP/UDP, semua port dan alamat); administrator sebaiknya menggantinya dengan aturan khusus TCP `8080` dari `LocalSubnet` saja. Perubahan aturan firewall memerlukan Administrator.

APK yang sudah memiliki pemeriksa update akan mengecek versi saat aplikasi dibuka dan berkala ketika aplikasi aktif. Android tetap meminta pengguna menyetujui pemasangan APK; versi lama perlu dipasang manual sekali agar mendapat fitur pemeriksaan update. Gunakan DHCP reservation di router supaya IP laptop tetap sama.

## Validasi lokal

```powershell
.\tools\validate-web.ps1
.\tools\validate-web.ps1 -CheckApi  # Apache + MySQL harus menyala
```

Tes Android dijalankan dari folder project Android dengan `.\gradlew.bat :app:testDebugUnitTest :app:lintDebug :app:assembleDebug`.

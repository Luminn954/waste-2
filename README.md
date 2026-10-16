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

## Akun dan login staf Google

- Website user: `user` / `12345`
- Admin bootstrap demo lokal: `admin` / `12345`, email dummy `admin@smartwaste.local` (hanya sebelum Google OAuth diwajibkan; email ini bukan akun Google).

Website hanya menerima role `user`. Android menerima role `admin` dan `petugas`; login silang ditolak. Setelah Google OAuth dikonfigurasi, Android hanya menerima token Google dengan email terverifikasi yang sudah terdaftar di tabel `users`. Login tidak membuat akun otomatis. Admin pertama dapat mendaftarkan email Admin/Petugas lewat menu Operasional; akun staf biasa tidak dapat mengubah email izinnya sendiri.

### Menyiapkan Google OAuth

Flow ini menggunakan Google ID token, bukan service-account private key atau client secret. Yang dibutuhkan adalah **OAuth Web Client ID** yang sama untuk aplikasi Android dan verifikasi PHP.

1. Di Google Auth Platform, daftarkan aplikasi untuk demo dan gunakan audience **External / Testing**. Tambahkan Gmail admin pertama sebagai test user.
2. Buat OAuth client tipe **Web application**. Catat **Client ID**-nya; untuk alur ID token ini tidak perlu memasukkan Client Secret ke aplikasi.
3. Buat OAuth client tipe **Android** dengan package `com.uoi.pratikumwithbunafla` dan SHA-1 debug certificate Android Studio: `38:8D:98:93:6C:92:D1:6A:46:32:F7:A0:5C:6C:6B:8B:3E:C6:5E:94`.
4. Atur `SMARTWASTE_GOOGLE_CLIENT_ID` ke Web Client ID dan `SMARTWASTE_FIRST_ADMIN_EMAIL` ke Gmail admin pertama yang sungguhan di `backend-php/.env` atau environment Windows. Jika memakai environment Windows, restart XAMPP Control Panel lalu Apache. Untuk database non-default, konfigurasi juga bisa diatur lewat `SMARTWASTE_DB_HOST`, `SMARTWASTE_DB_PORT`, `SMARTWASTE_DB_NAME`, `SMARTWASTE_DB_USER`, dan `SMARTWASTE_DB_PASSWORD`.
5. Tutup Android Studio, buka ulang melalui `OPEN-AndroidStudio-SmartWaste.bat`, lalu build ulang agar Gradle memasukkan Web Client ID ke APK.
6. Buka aplikasi, login sebagai admin pertama melalui Google, lalu daftarkan email akun Admin/Petugas. Hanya email yang sudah didaftarkan admin yang bisa masuk melalui Google.

Sebelum Client ID server dipasang, login password lama tetap tersedia agar admin awal tidak terkunci. Setelah Client ID dipasang, endpoint PHP menolak login password Android dan UI perlu dibuild ulang dengan Client ID yang sama. Client ID bukan rahasia; jangan menaruh private key/service-account key di aplikasi Android.

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
- Tolak laporan atau tetapkan petugas, kendaraan, dan TPA pada laporan yang lolos verifikasi.
- Lihat dan nonaktifkan akun user bermasalah.
- Statistik per bulan, jenis sampah, berat, dan laporan selesai.
- Refresh otomatis setiap 15 detik.

### Android — Petugas

- Login dengan akun petugas yang dibuat Admin.
- Melihat penugasan milik akun sendiri beserta lokasi, kendaraan, TPA, dan foto laporan.
- Memperbarui alur `Ditugaskan → Diangkut → Selesai`; status dan kapasitas disimpan ke database bersama.
- Tidak dapat membuka menu administrasi atau mengubah penugasan petugas lain.

## Struktur database utama

- `users` — akun user dan role.
- `admin` — profil admin yang tersinkron dengan akun admin.
- `sampah` — laporan sampah user.
- `riwayat_status` — jejak perubahan status oleh admin.
- `auth_tokens` — sesi login API.

Tabel pendukung seperti `petugas`, `kendaraan`, `tpa`, dan `laporan_harian` tetap dipertahankan supaya database XAMPP lama tidak kehilangan data saat migrasi. Setiap perubahan skema baru harus ditambahkan ke bootstrap runtime dan referensi `backend-php/database/schema.sql`; untuk data yang sudah dipakai, gunakan migrasi yang bisa dijalankan berulang tanpa menghapus data.

## Catatan Android

File `app/src/main/java/com/uoi/pratikumwithbunafla/data/remote/SmartWasteApi.kt` memakai:

```text
http://10.0.2.2:8080/Waste2/backend-php/index.php
```

Jika memakai HP fisik, ganti alamat tersebut dengan IP Wi-Fi laptop, misalnya `http://192.168.0.111:8080/Waste2/backend-php/index.php`, dan pastikan firewall mengizinkan Apache.

## Validasi lokal

```powershell
.\tools\validate-web.ps1
.\tools\validate-web.ps1 -CheckApi  # Apache + MySQL harus menyala
```

Tes Android dijalankan dari folder project Android dengan `.\gradlew.bat :app:testDebugUnitTest :app:lintDebug :app:assembleDebug`.

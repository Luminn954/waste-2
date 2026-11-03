# Kontrak REST API Smart Waste

Base URL XAMPP lokal:

```text
http://localhost:8080/Waste2/backend-php/index.php
```

Emulator Android menggunakan `http://10.0.2.2:8080` sebagai alamat host laptop. Untuk request JSON gunakan `Content-Type: application/json`; endpoint terlindungi menerima `Authorization: Bearer <token>`. Foto laporan dikirim sebagai `multipart/form-data` pada field `foto` (maksimal 5 MB; JPG, PNG, WEBP, GIF).

Semua respons error berbentuk JSON dengan `message` dan HTTP status yang sesuai. Role dicek server, bukan hanya disembunyikan dari UI.

| Method dan path | Akses | Tujuan |
|---|---|---|
| `GET /health` | Publik | Cek API dan koneksi database |
| `POST /auth/login` | Publik | Login web `user`; login admin/petugas hanya untuk demo ketika OAuth belum diwajibkan |
| `POST /auth/register` | Publik | Registrasi akun user website (`client: web`) |
| `GET /auth/google/challenge` | Publik | Membuat nonce login Google staf |
| `POST /auth/google` | Publik | Login Google admin/petugas yang emailnya sudah didaftarkan admin |
| `POST /auth/logout` | Login | Menghapus sesi token aktif |
| `GET /me` | Login | Profil akun aktif |
| `PUT /me` | Login | Memperbarui profil |
| `PATCH /me/password` | Login | Mengganti password |
| `GET /sampah` atau `/laporan` | Login | Daftar laporan; user dibatasi ke miliknya |
| `GET /sampah/mine` atau `/sampah/saya` | User | Riwayat laporan sendiri |
| `POST /sampah` | User | Membuat laporan beserta foto opsional |
| `GET /sampah/{id}` | Login | Detail laporan; user hanya boleh melihat miliknya |
| `GET /sampah/{id}/riwayat` | Login | Riwayat status laporan |
| `GET /admin/dashboard` atau `/dashboard` | Admin | Ringkasan operasional |
| `GET /admin/laporan` | Admin | Antrean dan daftar laporan; mendukung `status` dan `q` |
| `GET /admin/laporan/{id}` | Admin | Detail laporan |
| `PATCH /admin/laporan/{id}/status` | Admin | Mengubah status yang diizinkan |
| `PATCH /admin/laporan/{id}/tugaskan` | Admin | Menetapkan petugas, kendaraan, dan TPA |
| `GET /admin/petugas` | Admin | Daftar petugas |
| `POST /admin/akun` | Admin | Menambahkan akun staf yang diizinkan login Google |
| `PATCH /admin/petugas/{id}` | Admin | Memperbarui status/profil petugas |
| `GET /admin/kendaraan`, `POST /admin/kendaraan`, `PATCH /admin/kendaraan/{id}` | Admin | Kelola armada |
| `GET /admin/tpa`, `POST /admin/tpa`, `PATCH /admin/tpa/{id}` | Admin | Kelola fasilitas TPA |
| `GET /admin/perbaikan` | Admin | Memantau laporan kerusakan dan proses perbaikan armada |
| `PATCH /admin/perbaikan/{id}/setujui` | Admin | Menyetujui kendaraan masuk tahap perbaikan |
| `PATCH /admin/perbaikan/{id}/tolak` | Admin | Menolak laporan kerusakan dengan alasan; kendaraan tetap dikunci |
| `PATCH /admin/perbaikan/{id}/kembalikan` | Admin | Meminta perbaikan ulang setelah pemeriksaan |
| `PATCH /admin/perbaikan/{id}/selesai` | Admin | Menyetujui hasil pemeriksaan dan mengaktifkan kendaraan |
| `GET /petugas/perbaikan` | Petugas | Melihat status laporan dan riwayat proses perbaikan |
| `POST /petugas/kendaraan/{id}/kerusakan` | Petugas | Melaporkan penyebab dan kronologi; kendaraan langsung tidak tersedia |
| `PATCH /petugas/perbaikan/{id}/mulai` | Petugas pelapor | Memulai perbaikan setelah disetujui Admin |
| `PATCH /petugas/perbaikan/{id}/progres` | Petugas pelapor | Mencatat perkembangan dan tindakan perbaikan |
| `PATCH /petugas/perbaikan/{id}/pemeriksaan` | Petugas pelapor | Mengirim hasil perbaikan untuk pemeriksaan Admin |
| `GET /admin/users` | Admin | Daftar akun user |
| `PATCH /admin/users/{id}/toggle` | Admin | Menonaktifkan/mengaktifkan akun user |
| `GET /admin/laporan-harian?from=YYYY-MM-DD&to=YYYY-MM-DD` | Admin | Ringkasan laporan per hari |
| `GET /admin/statistik` | Admin | Statistik laporan dan jenis sampah |
| `GET /petugas/tugas` | Petugas | Tugas milik petugas yang sedang login |
| `PATCH /petugas/tugas/{id}/diangkut` | Petugas | Menandai tugas sudah diangkut |
| `PATCH /petugas/tugas/{id}/selesai` | Petugas | Menyelesaikan tugas |

Status domain: `menunggu_verifikasi`, `diproses`, `ditugaskan`, `diangkut`, `selesai`, `ditolak`. Setiap perubahan status yang sah harus dicatat di `riwayat_status`.

Saat mengubah endpoint atau payload, perbarui client Android `data/remote/SmartWasteApi.kt`, frontend `frontend/js/app.js`, dan tabel ini. Hindari mengganti path publik tanpa kompatibilitas lama atau pembaruan kedua client dalam satu perubahan.


Alur perbaikan armada: Petugas mencatat penyebab dan kronologi kerusakan; kendaraan langsung ditandai `Rusak` agar tidak menerima penugasan baru. Admin menyetujui atau menolak laporan. Setelah disetujui, Petugas memulai dan mencatat progres, lalu meminta pemeriksaan. Admin dapat menyetujui hasil sehingga kendaraan kembali `Baik`, atau meminta perbaikan ulang. Laporan dan jejak tindakan disimpan pada `kendaraan_perbaikan` serta `kendaraan_perbaikan_riwayat`.

Status perbaikan: `menunggu_persetujuan` → `disetujui` → `dalam_perbaikan` → `menunggu_pemeriksaan` → `selesai`; Admin juga dapat menolak laporan (`ditolak`) atau meminta pengerjaan ulang (`perlu_perbaikan`). Hanya status `selesai` yang mengembalikan kondisi kendaraan menjadi `Baik`.

# ERD database Smart Waste

Database MySQL `smart_waste` adalah sumber data bersama untuk web warga dan aplikasi Android. Keduanya menggunakan REST API PHP yang sama.

```mermaid
erDiagram
    USERS {
        bigint id PK
        varchar username
        varchar email
        varchar role
        boolean aktif
    }
    ADMIN {
        bigint id_admin PK
        bigint user_id FK
        varchar nama_admin
    }
    PETUGAS {
        bigint id PK
        bigint user_id FK
        varchar nama
        varchar zona_tugas
        boolean status_aktif
    }
    AUTH_TOKENS {
        bigint id PK
        bigint user_id FK
        char token_hash
        datetime expires_at
    }
    AUTH_LOGIN_ATTEMPTS {
        bigint id PK
        char identity_hash
        datetime attempted_at
    }
    GOOGLE_LOGIN_CHALLENGES {
        char nonce_hash PK
        datetime expires_at
    }
    SAMPAH {
        bigint id PK
        varchar kode_laporan
        bigint id_user FK
        bigint petugas_id FK
        bigint kendaraan_id FK
        bigint tpa_id FK
        varchar jenis
        decimal berat_kg
        varchar status
        varchar foto
        varchar foto_bukti_lokasi
    }
    RIWAYAT_STATUS {
        bigint id PK
        bigint sampah_id FK
        bigint diubah_oleh FK
        varchar status
        datetime dibuat_pada
    }
    KONFIRMASI_PENYELESAIAN {
        bigint id PK
        bigint sampah_id FK
        bigint petugas_id FK
        bigint direview_oleh FK
        varchar foto_bukti
        varchar status
    }
    LAPORAN_ARSIP {
        bigint sampah_id PK, FK
        bigint diarsipkan_oleh FK
        datetime diarsipkan_pada
    }
    CATATAN_ADMIN_ARSIP {
        bigint sampah_id PK, FK
        bigint diarsipkan_oleh FK
        datetime diarsipkan_pada
    }
    PETUGAS_LAPORAN_ARSIP {
        bigint sampah_id PK, FK
        bigint user_id PK, FK
        datetime diarsipkan_pada
    }
    KENDARAAN {
        bigint id PK
        varchar plat_nomor
        decimal kapasitas_kg
        decimal muatan_kg
        varchar kondisi
    }
    KENDARAAN_PERBAIKAN {
        bigint id PK
        bigint kendaraan_id FK
        bigint petugas_user_id FK
        bigint disetujui_oleh FK
        varchar status
    }
    KENDARAAN_PERBAIKAN_RIWAYAT {
        bigint id PK
        bigint perbaikan_id FK
        bigint actor_user_id FK
        varchar aksi
        datetime dibuat_pada
    }
    TPA {
        bigint id PK
        varchar nama
        decimal kapasitas_ton
        decimal terisi_ton
    }
    PERMINTAAN_PENGOSONGAN {
        bigint id PK
        bigint diminta_oleh FK
        bigint ditinjau_oleh FK
        varchar jenis_target
        bigint target_id
        varchar status
        varchar foto_bukti
    }
    LAPORAN_HARIAN {
        bigint id PK
        date tanggal
        int total_sampah
        decimal total_berat_kg
    }
    SMARTWASTE_SCHEMA_VERSION {
        int version PK
        datetime applied_at
    }

    USERS ||--o| ADMIN : profil
    USERS ||--o| PETUGAS : akun
    USERS ||--o{ AUTH_TOKENS : sesi
    USERS ||--o{ SAMPAH : pelapor
    USERS ||--o{ RIWAYAT_STATUS : mengubah
    USERS ||--o{ LAPORAN_ARSIP : mengarsipkan
    USERS ||--o{ CATATAN_ADMIN_ARSIP : mengarsipkan
    USERS ||--o{ PETUGAS_LAPORAN_ARSIP : arsip_petugas
    USERS ||--o{ KENDARAAN_PERBAIKAN : melaporkan
    USERS ||--o{ KENDARAAN_PERBAIKAN_RIWAYAT : bertindak
    USERS ||--o{ PERMINTAAN_PENGOSONGAN : mengajukan
    PETUGAS ||--o{ SAMPAH : menangani
    PETUGAS ||--o{ KONFIRMASI_PENYELESAIAN : mengirim_bukti
    KENDARAAN ||--o{ SAMPAH : mengangkut
    KENDARAAN ||--o{ KENDARAAN_PERBAIKAN : diperbaiki
    TPA ||--o{ SAMPAH : tujuan
    SAMPAH ||--o{ RIWAYAT_STATUS : riwayat
    SAMPAH ||--o| KONFIRMASI_PENYELESAIAN : bukti_akhir
    SAMPAH ||--o| LAPORAN_ARSIP : arsip_admin
    SAMPAH ||--o| CATATAN_ADMIN_ARSIP : arsip_catatan
    SAMPAH ||--o{ PETUGAS_LAPORAN_ARSIP : arsip_petugas
    KENDARAAN_PERBAIKAN ||--o{ KENDARAAN_PERBAIKAN_RIWAYAT : riwayat
```

## Kelompok tabel

| Kelompok | Tabel | Kegunaan |
|---|---|---|
| Akun dan sesi | `users`, `admin`, `petugas`, `auth_tokens` | Identitas, peran, profil, dan sesi aplikasi |
| Keamanan login | `auth_login_attempts`, `google_login_challenges` | Pembatasan percobaan masuk dan challenge autentikasi |
| Laporan warga | `sampah`, `riwayat_status`, `konfirmasi_penyelesaian` | Laporan awal, perubahan status, serta bukti akhir petugas |
| Arsip laporan | `laporan_arsip`, `catatan_admin_arsip`, `petugas_laporan_arsip` | Arsip admin, catatan, dan tampilan arsip petugas |
| Armada | `kendaraan`, `kendaraan_perbaikan`, `kendaraan_perbaikan_riwayat` | Kapasitas kendaraan dan alur perbaikan |
| TPA | `tpa`, `permintaan_pengosongan` | Kapasitas TPA dan persetujuan pengosongan |
| Rekap dan versi | `laporan_harian`, `smartwaste_schema_version` | Rekap harian dan versi migrasi skema |

Relasi pada diagram dan phpMyAdmin Designer adalah referensi logis aplikasi. Tabel saat ini belum memakai constraint foreign key fisik; ini menjaga alur arsip dan penghapusan permanen yang sudah diatur API. `permintaan_pengosongan.target_id` bersifat polimorfik: pasangan `jenis_target` menentukan apakah ID mengarah ke `kendaraan.id` atau `tpa.id`.

Foto tidak disimpan sebagai BLOB di MySQL. Berkas berada di `backend-php/uploads/`, sedangkan path-nya disimpan di kolom `foto`/ `foto_bukti`; folder unggahan harus ikut disalin saat memindahkan website.

# Asisten AI Petugas

Asisten membaca antrean laporan dan tugas milik Petugas yang sedang login. Jawaban AI bersifat baca-saja; tombol tugas dibuat oleh server dari kode laporan yang sudah divalidasi.
Untuk memberi saran yang relevan, server mengirim kode laporan, jenis/berat/lokasi, status, informasi kendaraan/TPA, dan catatan pemeriksaan admin ke OpenAI bersama pertanyaan Petugas.

## Konfigurasi lokal XAMPP

1. Buka `backend-php/.env` (buat dari `.env.example` bila belum ada).
2. Isi `SMARTWASTE_OPENAI_API_KEY` dengan API key OpenAI.
3. Opsional: atur `SMARTWASTE_OPENAI_MODEL`; bawaan adalah `gpt-4o-mini`.
4. Restart Apache agar environment dibaca ulang.

Simpan key hanya di environment PHP/server. Jangan pernah memasukkannya ke Kotlin, `BuildConfig`, APK, atau Git. File `.env` sudah dikecualikan dari commit.

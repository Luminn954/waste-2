# Alur perubahan dan validasi

1. Ubah API dan database terlebih dahulu jika ada perubahan alur data; pertahankan kompatibilitas instalasi MySQL yang sudah berisi data.
2. Perbarui client website dan/atau Android terhadap kontrak yang sama.
3. Periksa perubahan database dengan backup lokal sebelum migrasi data yang sudah dipakai.
4. Jalankan pemeriksaan web:

   ```powershell
   .\tools\validate-web.ps1
   ```

5. Jalankan pemeriksaan integrasi API saat Apache dan MySQL aktif:

   ```powershell
   .\tools\validate-web.ps1 -CheckApi
   ```

6. Dari project Android jalankan:

   ```powershell
   .\gradlew.bat :app:testDebugUnitTest :app:lintDebug :app:assembleDebug
   ```

7. Uji login silang role, pembuatan laporan user, verifikasi/penugasan admin, serta status petugas di emulator dan browser. Keduanya harus terlihat pada database yang sama.

Untuk keamanan, jangan commit password MySQL produksi, OAuth secret, token, file `.env`, atau foto laporan warga. Google ID token diverifikasi di PHP; client ID bukan private key.

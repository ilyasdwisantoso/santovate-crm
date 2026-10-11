# Santovate CRM V9.3.11 — Workspace Registration UI Revamp

Baseline wajib: `062dc9a97f4d0147a05152fb66a81c83214be690` (V9.3.10).

## Perubahan
- Hero registrasi lebih pendek, modern dan responsif.
- Form dibagi menjadi informasi bisnis, layanan dan keamanan akun; setiap field berisi contoh/petunjuk singkat.
- Pemilihan billing bulanan atau tahunan lebih jelas.
- Ringkasan paket, konfigurasi dan estimasi biaya berdasarkan katalog aktif Laravel; nilainya terhitung ulang otomatis saat pilihan berubah.
- Pilihan paket/konfigurasi dari URL tetap digunakan apabila tersedia di katalog; nilai tidak valid menggunakan fallback item katalog yang benar.
- Satu kolom pada layar ponsel, tombol submit setelah ringkasan, validasi konfirmasi password V9.3.10 tetap berlaku.
- Harga final tetap ditentukan backend pada proses checkout; tidak ada perubahan logic payment, callback, database ataupun entitlement.

## Dua perintah terpisah
1. Jalankan `RUN_APPLY_TEST_PUSH_V9311.ps1 -ProjectPath "C:\Projects\santovate-crm-final"`. Pastikan muncul `V9311_PUSH_OK`.
2. WAJIB sesudah push berhasil: `DEPLOY_AFTER_PUSH_V9311.ps1 -ProjectPath "C:\Projects\santovate-crm-final"`. Pastikan `DEPLOY_V9311_DONE`.

Sebelum deploy pastikan backup database tersedia. Existing Hostinger pipeline menjalankan `artisan migrate --force` incremental, tetapi versi ini tidak menambah migration. Jangan lakukan `git reset --hard`.

## Manual QA di https://crm.santovate.com/register?plan=scale&config=software-agency
- URL otomatis memilih Scale dan Software & Agency.
- Ubah jenis bisnis & paket => harga ringkasan harus mengikuti data katalog.
- Ubah billing bulanan ↔ tahunan => breakdown dan total harus berubah.
- Uji desktop, 768 px, 390 px dan 320 px: tidak ada horizontal overflow.
- Uji mismatch password => tidak membuat workspace.
- Buat satu workspace khusus QA saja bila diperlukan; jangan membuat transaksi nyata/produksi saat memeriksa UI.

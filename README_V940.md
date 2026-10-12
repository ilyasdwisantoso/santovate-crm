# Santovate CRM V9.4.0 — UI/UX Navigation Foundation

Baseline `88b3b93a22a76b0854a2a88237cef73187e16610` (V9.3.12).

## Scope
- Menyatukan menu desktop dan mobile melalui `workspaceNavigation.js`, supaya hak akses visual dan fitur langganan konsisten.
- Menambahkan `aria-current` untuk halaman aktif, label navigasi, dialog semantik mobile, `aria-expanded`, dan tombol Escape untuk menutup menu/notifikasi.
- CSS fokus keyboard, ukuran target sentuh, dan dukungan reduced-motion di `workspace-navigation.css`.
- Tujuh tes JS untuk kebijakan menu role, tenant internal, entitlement, platform owner, dan breadcrumb.
- `VERSION` menjadi `9.4.0`.

## Cara menerapkan
Lihat `README_PAKET.md` di ZIP. Jalankan `RUN_ALL_V9_4_0_AND_PUSH.ps1 -ProjectPath "C:\Projects\santovate-crm-final" -CheckOnly`, lalu jalankan kembali tanpa `-CheckOnly` jika PASS.

## Production
- Runner tidak men-deploy ke Hostinger, tidak menjalankan migration/seeder, dan tidak mengubah konfigurasi environment.
- PHP tests, JS tests, dan Vite build berjalan di Windows lokal sebelum commit/push.
- Pengujian visual desktop/mobile tetap perlu dilakukan sesudah build/deploy terpisah.
- GitHub `master` sebagai audit/baseline; patch tetap di-APPLY lokal.

## Selanjutnya
V9.4.1 dashboard & KPI UX, V9.4.2 prospek & filter UX, V9.4.3 shared forms & CSS consolidation.

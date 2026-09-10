# Santovate CRM 2.1 Final

CRM internal Santovate berbasis **Laravel 12 + React 19 + Inertia + MySQL/MariaDB**. Paket ini dibuat untuk **fresh install**, bukan upgrade dari v1/v2 lama.

## Fitur utama

- Login modern React untuk Admin dan Sales.
- Dashboard KPI, follow-up, pipeline value, top priority prospect, dan performance bulanan.
- Database Prospek dengan scoring sederhana 0–9 dan prioritas Tinggi/Sedang/Rendah.
- Tampilan tabel modern desktop dan card-based mobile.
- Pipeline Kanban desktop; selector stage yang lebih nyaman untuk mobile.
- Bottom navbar mobile: Dashboard, Prospek, Pipeline, Target, Lainnya.
- Timeline aktivitas: WhatsApp, telepon, email, meeting, demo, proposal, follow-up, catatan.
- Target sales berbasis outcome: Contacted 10%, Meeting 20%, Proposal 20%, Deal 20%, Revenue 30%.
- Active company hanya sebagai indikator workload, bukan KPI utama.
- Import `.xlsx`, `.xls`, `.csv`: preview, validasi, deteksi duplikat, skip/update, riwayat import.
- Template import dan sample 30 prospek tersedia dari menu **Import Data** dan di root project.
- Admin dapat mengelola user Sales dan assignment prospect.
- Sales hanya melihat prospect yang menjadi tanggung jawab/dibuat olehnya.

## Fresh install yang direkomendasikan

Jangan timpa folder project lama. Extract ZIP ke folder baru, misalnya:

```powershell
C:\Projects\santovate-crm-final
```

Buka PowerShell:

```powershell
cd C:\Projects\santovate-crm-final
Set-ExecutionPolicy -Scope Process Bypass
.\INSTALL_FINAL.ps1
```

Installer akan:

1. Memeriksa PHP >= 8.2 dan `pdo_mysql`.
2. Memeriksa Node yang kompatibel dengan Vite 8.
3. Memastikan folder runtime Laravel tersedia.
4. Menormalisasi `package.json`/`.env` ke UTF-8 **tanpa BOM**.
5. Meminta koneksi MySQL/MariaDB.
6. Membuat database jika belum ada.
7. Menjalankan `composer install`.
8. Menjalankan `npm install`.
9. Menjalankan production build React/Vite dan memverifikasi `public/build/manifest.json`.
10. Generate APP_KEY, migration + seeder, route check, Laravel check, dan unit test inti.

### Input database

Saat installer bertanya, untuk XAMPP biasanya:

```text
DB Host: 127.0.0.1
DB Port: 3306
Nama database: santovate_crm_final
DB Username: root
DB Password: [kosong]
```

Jika MariaDB/XAMPP Anda menggunakan port `3307`, isi `3307`.

**Saran:** gunakan nama database default `santovate_crm_final` agar tidak berbenturan dengan database v1/v2 lama.

## Menjalankan CRM

Setelah installer menyatakan sukses:

```powershell
.\START_CRM.ps1
```

Buka:

```text
http://127.0.0.1:8000
```

### Akun default

Admin:

```text
admin@santovate.local
Santovate123!
```

Sales:

```text
sales@santovate.local
Sales123!
```

Ganti password default sebelum penggunaan production.

## Verifikasi instalasi

Kapan pun dibutuhkan:

```powershell
.\VERIFY_INSTALL.ps1
```

Script akan mengecek `.env`, `vendor`, `node_modules`, Vite manifest, Laravel boot, migration status, dan routes.

## Import prospek

Login sebagai Admin, lalu:

```text
Sidebar Desktop → Data & Team → Import Data
```

atau pada mobile:

```text
Bottom Navbar → Lainnya → Import Data
```

Alur:

```text
Upload XLSX/CSV
→ Preview
→ Valid / Duplicate / Error
→ Pilih Skip atau Update duplicate
→ Import
→ Riwayat import tercatat
```

Header template final:

```text
Nama Perusahaan
Website
Kota
Layanan
Rute
Ukuran Perusahaan
Nama Kontak
Jabatan Kontak
Telepon / WhatsApp
Email
Sistem Saat Ini
Punya Tracking Portal
Dugaan Masalah
Score Kecocokan
Score Masalah
Score Kemudahan Kontak
Status
Tanggal Follow Up
Sumber Nama
Sumber URL
Catatan
Email Sales
Potensi Deal (Rp)
```

File yang sudah disertakan:

- `IMPORT_TEMPLATE_SANTOVATE_CRM.xlsx`
- `IMPORT_SAMPLE_30_PROSPEK.xlsx`

## Scoring prospek

Tiga indikator, masing-masing 0–3:

- **Kecocokan**: apakah perusahaan sesuai target Santovate.
- **Masalah Terlihat**: seberapa kuat bukti masalah yang bisa diselesaikan Santovate.
- **Kemudahan Kontak**: apakah PIC/decision maker mudah dijangkau.

Total:

```text
7–9 = Tinggi
4–6 = Sedang
0–3 = Rendah
```

## Target sales

Jumlah company yang di-handle dipakai sebagai **workload**. Performance utama dihitung dari:

```text
Contacted  10%
Meeting    20%
Proposal   20%
Deal       20%
Revenue    30%
```

Ini mencegah Sales terlihat produktif hanya karena memiliki banyak lead tanpa progres komersial.

## Struktur utama

```text
app/
  Http/Controllers
  Http/Requests
  Models
  Services
resources/
  js/Pages
  js/Layouts
  js/Components
  css/app.css
database/
  migrations
  seeders
storage/app/templates/
```

Business logic utama dipisah ke service seperti `ProspectStageService`, `SalesPerformanceService`, `ProspectScoringService`, dan `ProspectImportService` agar Controller tetap tipis dan lebih mudah dirawat.

## Catatan production

Sebelum deploy publik:

- set `APP_ENV=production`
- set `APP_DEBUG=false`
- ganti password default
- gunakan HTTPS
- backup database
- konfigurasi web server ke folder `public/`
- gunakan kredensial database non-root


## Fix 2.1.1 — Windows PowerShell path

Installer 2.1.1 mengunci seluruh operasi file ke folder tempat `INSTALL_FINAL.ps1` berada (`$PSScriptRoot`). Ini mencegah PowerShell/.NET mencari `package.json` atau `.env` di `C:\Users\User` ketika project sebenarnya berada di `C:\Projects\santovate-crm-final`.

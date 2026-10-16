# Santovate CRM V9.5.2 — Native B2C Customer & Lead Conversion

Status: implementasi lokal untuk review, belum dirilis atau dimigrasikan ke database development/production.
Repository: C:\Projects\santovate-crm-final. Baseline Git: e83248731f2f73998c9603487a4819d391b614ba (master).
Konteks: SANTOVATE_CRM_CODEX_HANDOFF_2026-10-16.md dan persetujuan Opsi A. File handoff tidak diubah.

## Audit aktual dan batas tindakan

- Stack terpasang: Laravel 12.69.2, PHP 8.5.10, React 19.2, Inertia Laravel 3.3.3 / React 3.7, Vite 8.2.2.
- Database development: crm_santovate, loopback port 3307, MariaDB 10.4.32; 50 tabel InnoDB, 24 migration terpasang, 100 Prospect B2B.
- company_name dan company_key sebelumnya NOT NULL. Index company_key sudah nonunique pada baseline; tidak dibuat unique kembali.
- Sembilan foreign key menuju prospects dipertahankan. Database aktif belum mempunyai kolom identitas V9.5.2.
- Koneksi baca lokal tersedia tanpa perubahan .env atau restart server. Saat membuat akun tes, GRANT gagal dengan kode 1034 karena index tabel sistem mysql.db rusak.
- Skema/akun percobaan yang gagal sudah dihapus. Tabel sistem server aktif tidak diperbaiki. Pemeliharaan server tersebut perlu pekerjaan terpisah.
- Verifikasi MariaDB memakai instance TEMP tersendiri pada port 3317, bukan server development. Semua skema dan akun tes dibuang setelah suite selesai.
- Fingerprint seluruh nilai Prospect, metadata kolom Prospect, dan history migration development sebelum/sesudah suite sama.
- Tidak ada migration, perubahan data existing massal, commit, push, atau deployment pada database/aplikasi aktif.

## Keputusan arsitektur dan alternatif

Opsi A memperluas prospects. Semua prospect_id tetap menjadi kontrak relasi CRM dan komersial; belum ada master Customer terpisah.

Alternatif master customers dengan pemetaan ulang relasi ditunda: memerlukan dua identitas, sinkronisasi siklus hidup, quota mapping dan migrasi banyak relasi.
Alternatif tabel turunan individu/perusahaan menambah join dan risiko pasangan subtype tidak lengkap; belum diperlukan untuk dua nama identitas.
Tidak ada nama perusahaan buatan untuk individu, unique nomor/email, atau penggabungan otomatis.

Kontrak data:

| Field | Business / B2B | Individual / B2C |
| --- | --- | --- |
| entity_type | business, default untuk create lama | individual |
| company_name | Nama perusahaan sebenarnya, wajib | NULL |
| company_key | Algoritme B2B existing, nonunique | NULL |
| individual_name | NULL | Nama individu yang dikonfirmasi, wajib |
| display_name | company_name | individual_name |

display_name adalah accessor/read-only, bukan kolom database atau input terpercaya. Payload identitas memuat id dan lima field di atas.
Jenis identitas tidak bisa diubah melalui edit biasa. company_key selalu dihitung server.
Field kontak, assignment, scoring, qualification dan provenance tetap memakai model existing.
Schema lama tetap melayani B2B; permintaan individu ditolak sampai schema dan flag siap.
SANTOVATE_B2C_ENABLED default false. Flag OFF menutup pembuatan individu baru; baca, cari, filter dan edit individu existing tetap bekerja.

## Migration dan dependensi

1. 2026_10_16_090000_add_native_prospect_identity.php menambah entity_type VARCHAR(20) DEFAULT business dan individual_name nullable; company_name/company_key menjadi nullable.
2. 2026_10_16_090100_add_prospect_identity_lookup_indexes.php menambah index tenant/nomor dan tenant/jenis/updated_at/id.

Tidak ada UPDATE/backfill eksplisit, penomoran ulang ID, perubahan foreign key, atau penghapusan tabel/kolom. Nilai B2B existing tetap persis pada tes migrasi nyata.
DEFAULT business mengklasifikasikan baris lama. ALTER dapat mengambil metadata lock atau rebuild storage tergantung engine; durasinya harus diuji pada salinan berukuran realistis sebelum rilis.
DDL MySQL/MariaDB tidak diasumsikan transactional. Jika migration gagal sebagian, hentikan aktivasi, periksa schema dan migration ledger, lalu susun forward repair; jangan mengulang secara buta.
Kedua down() menolak rollback destructive dengan exception.
Dependensi: migration baseline lengkap, tabel organizations/subscriptions/plans/grants, assignment, products, activity, WhatsApp staging/messages/reviews, InnoDB dan transaksi.

## Service, kuota dan review identitas

- ProspectIdentityService menormalisasi identitas, memeriksa flag, menolak perubahan jenis dan mencari kandidat tenant yang sama: company_key/nama perusahaan+kota, nama individu, nomor ternormalisasi atau email.
- Pemeriksaan kandidat tidak menggabungkan record. Override force_duplicate harus berupa konfirmasi review manual; nama/nomor/email bukan primary identity.
- Endpoint kandidat menyaring visibilitas AE. Konflik yang tidak terlihat menghasilkan pesan generik tanpa membuka nama/kontak AE lain.
- ProspectCreationService memiliki transaksi sendiri. Urutan: lock organization dahulu, baca kandidat dan usage segar, cek kuota, insert, relasi/activity/assignment/stage, commit. Retry transaksi sampai tiga kali untuk deadlock.
- Service menolak outer transaction agar snapshot REPEATABLE READ lama tidak dipakai untuk kuota. Semua jalur runtime yang ditemukan (form, import, konversi WhatsApp) memakai service/lock yang sama.
- Satu baris individual memakai satu kuota Prospect. Staging WhatsApp tetap tersimpan saat kapasitas penuh tanpa memakan kuota.
- Import tetap B2B. Preview dihitung ulang setelah lock, kapasitas seluruh batch diperiksa dahulu. Duplicate key satu file memakai slot sekali. Mode skip/update existing tetap ada; key ambigu atau konflik nomor/email dengan identitas lain memerlukan review di form.
- Raw SQL eksternal/fitur baru yang melewati service tidak mendapat jaminan kuota ini; setiap jalur baru wajib memakai kontrak lock yang sama.

WhatsAppLeadConversionService mengunci organization lalu contact. Konversi ulang identitas sama mengembalikan ID yang sama; target berbeda ditolak.
Link manual memeriksa tenant dan kecocokan nomor, tanpa membuat atau menggabungkan Prospect. Satu staging contact/nomor per tenant tetap terhubung ke satu Prospect; percakapan multi pihak/shared phone belum dimodelkan.
Pesan pending ditautkan saat review. Webhook berikutnya mempertahankan link yang sudah dipilih; replay pesan tidak menambah activity.
Routing B2B existing dipertahankan hanya untuk satu kandidat unik. Individu membutuhkan link/review manual.
Inbound, link dan konversi tidak memberikan marketing opt-in. Opt-in eksplisit pada form/import memakai mekanisme existing.

## UI dan kompatibilitas komersial

Form Prospect memiliki jenis pelanggan dan nama kondisional; jenis terkunci saat edit. Ada tombol kandidat duplikat dan konfirmasi simpan sebagai identitas berbeda.
Daftar Prospect mendukung pencarian nama individu dan filter jenis. Detail memperlihatkan jenis identitas.
WhatsApp Review menyediakan konversi individu ketika flag/schema aktif, konfirmasi nama, kuota dan review duplikasi.
Dashboard, Pipeline, Follow-up, Approval, Opportunity, Quotation termasuk print, Deal, Invoice, Finance dan Commission memakai display_name untuk tampilan.
company_name dalam payload tetap bernilai perusahaan asli atau NULL. Rumus harga, KPI dan komisi tidak diubah; istilah workload UI memakai jumlah prospek.

## Tahapan dan acceptance criteria

| Tahap | Acceptance criteria | Bukti lokal |
| --- | --- | --- |
| 1. Isolasi tes | Suite default tidak bisa memakai dev DB, SQLite file atau konfigurasi cached | Guard unit dan feature test lulus |
| 2. Identitas | B2B/FK utuh; individu tanpa company/key; jenis immutable; flag OFF aman | SQLite dan migrasi MariaDB lulus |
| 3. Creation dan conversion | Semua creation memiliki lock kuota; kegagalan atomik; replay satu ID; staging tetap ada | Race form/import/conversion dan replay MariaDB lulus |
| 4. UI dan komersial | Nama B2C tampil tanpa mengubah semantik B2B; angka komisi tetap | HTTP flow B2B/B2C, pencarian, print dan finance lulus; build lulus |
| 5. Release readiness | Dokumentasi/rollback, pemeriksaan source, tidak menyentuh DB aktif | Dokumen ini; fingerprint DB tetap sama |

Hasil verifikasi 16 Oktober 2026:
- PHPUnit utama: 124 tes, 507 assertion lulus (SQLite :memory: untuk tes database).
- MariaDB 10.4.32 terisolasi: 6 tes, 75 assertion lulus. Termasuk seluruh migration historis nyata, sembilan FK, nullable/index, perebutan slot kuota lintas jalur, replay conversion, deduplikasi konkuren, dan query/pilihan identitas.
- JavaScript: 48 tes lulus.
- Vite build lulus; warning ukuran chunk >500 kB masih ada. public/build bersifat ignored, hanya build lokal.
- Alur HTTP B2B dan B2C diuji sampai Opportunity, Quotation accepted/print, Deal, invoice, verifikasi pembayaran manual dan komisi 25% dari basis existing.
- Belum diuji pada server Oracle MySQL atau versi server production; MariaDB bukan bukti pengujian Oracle MySQL.
- Browser UAT visual, webhook Meta sungguhan, payment gateway sungguhan, deployment dan pengujian beban data production belum dilakukan.
- Dua migration tanggal subscription lama memakai ALTER khusus MySQL. Fixture SQLite menyesuaikan dua migration tersebut hanya dalam harness; file migration historis tidak diubah.

## Aktivasi dan rollback aman

Status sekarang: flag default OFF dan migration baru belum dijalankan pada DB aktif. Jangan memakai runner push versi lama untuk merilis perubahan ini.
Rilis adalah keputusan terpisah: review diff, pastikan versi DB target, backup konsisten dan uji restore di lingkungan terisolasi, ukur ALTER pada salinan data, jalankan smoke/UAT, lalu jadwalkan migrasi dengan kode yang memahami kedua tipe identitas.
Sebelum perubahan target, periksa schema/ledger/engine/index/FK dan pastikan dua migration V9.5.2 belum terpasang sebagian.
Aktifkan flag hanya setelah kedua migration berhasil dan B2B smoke test lulus. Terapkan mekanisme config/cache yang sesuai lingkungan saat rilis yang disetujui.

Rollback operasional: set flag OFF pada build yang tetap memahami identitas native. Pertahankan semua kolom, nilai individu, prospect_id dan relasi.
Jangan menjalankan migrate:rollback, migrate:refresh, migrate:fresh atau menurunkan company_name/key kembali menjadi NOT NULL pada data aktif.
Jangan mengganti build dengan kode lama yang menganggap semua Prospect perusahaan setelah individu tersimpan.
Perbaikan selanjutnya menggunakan forward migration yang direview. Pemulihan backup harus dianalisis terpisah agar data baru tidak hilang.

Isu baseline di luar cakupan: channel ownership, approval diskon item, respons direct invoice iPaymu, reject quotation accepted, race campaign/deal, dan konversi datetime-local/UTC. Tidak dinyatakan selesai oleh V9.5.2.

## File yang berubah

- .env.example
- app/Http/Controllers/ApprovalRequestController.php
- app/Http/Controllers/DealController.php
- app/Http/Controllers/Finance/CommissionController.php
- app/Http/Controllers/Finance/FinanceController.php
- app/Http/Controllers/Finance/InvoiceController.php
- app/Http/Controllers/ImportController.php
- app/Http/Controllers/OpportunityController.php
- app/Http/Controllers/ProspectController.php
- app/Http/Controllers/QuotationController.php
- app/Http/Controllers/WhatsAppLeadReviewController.php
- app/Http/Middleware/HandleInertiaRequests.php
- app/Http/Requests/ProspectRequest.php
- app/Http/Resources/ProspectResource.php
- app/Models/Prospect.php
- app/Services/ApprovalService.php
- app/Services/FollowUpService.php
- app/Services/ProspectCreationService.php
- app/Services/ProspectIdentityService.php
- app/Services/ProspectImportService.php
- app/Services/WhatsAppInboundCaptureService.php
- app/Services/WhatsAppLeadConversionService.php
- app/Services/WhatsAppService.php
- app/Support/ProspectIndexFilters.php
- config/database.php
- config/santovate.php
- database/migrations/2026_10_16_090000_add_native_prospect_identity.php
- database/migrations/2026_10_16_090100_add_prospect_identity_lookup_indexes.php
- docs/TEST_DATABASE_SAFETY.md
- phpunit.mysql.xml
- phpunit.xml
- README_V952.md
- resources/js/Pages/Approvals/Index.jsx
- resources/js/Pages/Dashboard/Index.jsx
- resources/js/Pages/Deals/Form.jsx
- resources/js/Pages/Deals/Index.jsx
- resources/js/Pages/Deals/Show.jsx
- resources/js/Pages/FollowUps/Index.jsx
- resources/js/Pages/Opportunities/Form.jsx
- resources/js/Pages/Opportunities/Index.jsx
- resources/js/Pages/Opportunities/Show.jsx
- resources/js/Pages/Pipeline/Index.jsx
- resources/js/Pages/Prospects/Form.jsx
- resources/js/Pages/Prospects/Index.jsx
- resources/js/Pages/Prospects/Show.jsx
- resources/js/Pages/Quotations/Form.jsx
- resources/js/Pages/Quotations/Index.jsx
- resources/js/Pages/Quotations/Print.jsx
- resources/js/Pages/Quotations/Show.jsx
- resources/js/Pages/Targets/Index.jsx
- resources/js/Pages/WhatsApp/Leads.jsx
- resources/js/Utils/prospectFilters.js
- resources/js/Utils/prospectIdentity.js
- tests/Feature/DatabaseIsolationTest.php
- tests/Feature/NativeCustomerFlowTest.php
- tests/Feature/NativeProspectRegressionTest.php
- tests/Feature/ProspectCreationTest.php
- tests/Feature/ProspectIdentityTest.php
- tests/Feature/WhatsAppLeadReviewTest.php
- tests/Integration/MySql/IsolatedConnectionTest.php
- tests/Integration/MySql/NativeProspectTest.php
- tests/js/prospectIdentity.test.mjs
- tests/Support/CrmTestCase.php
- tests/Support/IsolatedDatabaseGuard.php
- tests/Support/NativeCrmFixtures.php
- tests/Support/prospect-create-worker.php
- tests/Support/run-isolated-mysql.php
- tests/TestCase.php
- tests/Unit/IsolatedDatabaseGuardTest.php
- VERSION

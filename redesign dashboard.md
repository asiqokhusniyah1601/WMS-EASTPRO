# Strategi UI/UX Dashboard & Alert Center WMS EASTPRO 2026

Dokumen ini berisi panduan dan konsep desain untuk menyederhanakan antarmuka (UI) serta meningkatkan pengalaman pengguna (UX) pada sistem **WMS EASTPRO**, dengan fokus pada tren **Context-Aware Dashboard 2026** yang menggabungkan halaman utama dengan *Alert Center* secara terpadu.

---

## 1. Filosofi Desain 2026: *Context-Aware & Actionable UI*
Di tahun 2026, dashboard terbaik tidak hanya berfungsi sebagai penampil data (*passive display*), melainkan sebagai pusat komando yang memberikan wawasan instan yang bisa langsung ditindaklanjuti (*actionable insights*). Target utamanya adalah **mengurangi beban kognitif (*cognitive load*)** operator gudang yang bekerja dalam durasi lama.

### Prinsip Utama:
* **Progressive Disclosure:** Tampilkan informasi hanya saat dibutuhkan. Sembunyikan opsi sekunder (seperti data aksesoris atau SIM card yang bersifat opsional) hingga operator memilih atau memicu aksi terkait.
* **Split Layout & Sticky Elements:** Pisahkan area kerja utama (Input/Scan) dengan ringkasan data atau aksi final menggunakan panel yang menempel (*sticky*), sehingga operator tidak perlu melakukan *scroll* panjang.
* **Semantic Colors & Visual Feedback:** Warna digunakan sebagai indikator status otomatis, bukan dekorasi. 
    * *Hijau:* Berhasil / Sehat
    * *Amber/Kuning:* Peringatan / Perlu Dicek
    * *Merah:* Kritis / Duplikat / Error

---

## 2. Struktur Layout & Penyederhanaan Menu

### A. Halaman Web Receiving (Penerimaan Barang)
* **Masalah Saat Ini:** Form input atas-bawah dan tabel di bagian paling bawah memaksa pengguna melakukan *scroll* berulang kali.
* **Rekomendasi UI/UX Baru:**
    * Gunakan **Split Layout (60:40)**.
    * **Kolom Kiri (60%):** Area Scan Barcode dibuat sangat besar dan dominan dengan fitur *auto-focus*. Dropdown Merk/Tipe/Model disembunyikan dan baru muncul (atau terisi otomatis via *AI Suggestion*) setelah serial number berhasil di-scan.
    * **Kolom Kanan (40%):** *Sticky Side Panel* yang menampilkan *Live Counter* (Total barang yang sudah masuk) dan daftar 5 item terakhir yang berhasil dipindai.

### B. Halaman Warehouse Transfer (Mutasi Barang)
* **Masalah Saat Ini:** Form Perangkat, Aksesoris, dan Kartu GSM tampil sekaligus dalam satu layar penuh, membuat antarmuka menjadi sangat padat.
* **Rekomendasi UI/UX Baru:**
    * Terapkan sistem **Stepper / Wizard 2 Tahap**:
        * *Tahap 1:* Fokus hanya pada pemindaian barcode perangkat utama.
        * *Tahap 2:* Opsi tambahan (Aksesoris & SIM Card) disembunyikan dalam tombol *Collapsible* (`+ Tambah Aksesoris`). Operator hanya membukanya jika dibutuhkan.
    * Panel "Rute Pengiriman" dan tombol "Release Shipment" dijadikan *sticky sidebar* di kanan layar agar selalu terlihat tanpa terpengaruh panjangnya daftar barang.

---

## 3. Integrasi Dashboard & Alert Center 2026

Menggabungkan Dashboard dengan *Alert Center* ke dalam satu ekosistem layar terpadu (*Unified Command Center*).

### Fitur Utama Hubungan Dashboard & Alert:
1.  **Priority Stream (Top Banner):** Baris notifikasi tipis di bawah header utama untuk *alert* kritis (contoh: *"2 barang belum diterima di WH-Malang"*). Operator bisa langsung klik banner ini untuk memicu tindakan perbaikan.
2.  **Drill-down Widgets:** Semua angka infografis (seperti "Total Unit Bekas" atau "Perangkat Rusak") dapat diklik dan akan langsung membuka *modal pop-up* berisi daftar detailnya, tanpa memindahkan pengguna ke halaman baru.
3.  **Smart Action Button:** Di dalam deretan *live alert feed*, sistem menyediakan tombol aksi instan seperti "Hubungi PIC Gudang" atau "Approve Transfer" langsung di samping teks peringatan.

---

## 4. Repositori & Akses Sistem lokal
Untuk keperluan pengembangan, pengujian *wireframe*, dan integrasi API antarmuka baru ini, silakan akses server lokal pada alamat berikut:

* **URL Development Environment:** `http://127.0.0.1:8001/`
* **Mode Tampilan Tersedia:**
    * *Comfortable Mode:* Untuk operator baru (jarak antar elemen lebih luas).
    * *Compact Mode:* Untuk operator mahir (kepadatan data tinggi untuk efisiensi kecepatan).
    * *Dark Mode:* Otomatis aktif menyesuaikan pencahayaan area gudang guna mengurangi kelelahan mata.

---

## 5. Implementasi Dashboard "Unified Command Center" (Sudah Diterapkan)

Konsep pada dokumen ini telah diimplementasikan langsung pada halaman Dashboard (`resources/views/dashboard.blade.php`) dengan pola yang **konsisten** terhadap halaman operasional (Receiving, Transfer, Issue, Return).

### A. Priority Stream (Banner Tipis di Bawah Header)
* Baris ringkas, *actionable*, dengan **semantic color** otomatis:
    * Merah (`is-critical`) bila ada stok **kritis/habis**.
    * Amber (`is-warning`) bila ada stok **menipis** atau transfer menunggu diterima.
    * Hijau (`is-healthy`) bila semuanya sehat.
* Setiap segmen berupa *pill* yang bisa diklik → langsung menuju Alert Center (`route('alerts')`) atau halaman Transfer (`route('transfer')`).
* Data dihitung di controller: `getStockAlerts()` (kritis/warning) + jumlah `DeliveryOrder` berstatus `IN_TRANSIT` (transfer tertunda), keduanya mengikuti filter gudang aktif (`?view=`).

### B. Split Layout + Sticky Alert Center
* Layout dua kolom `dash-split` (`minmax(0,1fr)` + `360px`), runtuh ke 1 kolom di layar < 1200px.
* **Kolom kiri:** ringkasan stok (Device/Aksesoris/GSM), grafik tren, dan *Live Transaction Stream*.
* **Kolom kanan (sticky):** *Pusat Peringatan* + *AI Insights* — selalu terlihat tanpa *scroll*. Inilah penggabungan Dashboard ↔ Alert Center.

### C. Smart Action Buttons
* Tiap item peringatan punya tombol aksi langsung sesuai jenisnya:
    * `DEVICE` / `ACCESSORY` → **Restock** (menuju Receiving tab terkait).
    * `SIMCARD`/lainnya → **Tindak** (menuju Alert Center).
    * Transfer tertunda → **Terima Transfer** (menuju halaman Transfer).
* *AI Insights* tetap diperbarui **real-time** via Laravel Echo (`#ai-insights-container`).

### D. Drill-down Widgets
* Seluruh kartu angka (In Stock, Issued, Installed, Bekas, SIM, dll.) tetap *clickable* dan langsung membuka halaman detail terfilter (Search / Reports / Master Data).

---

## 6. Checklist Pola Desain Seragam (Terapkan ke Semua Halaman)

Agar seluruh aplikasi terasa satu kesatuan, gunakan token & pola berikut di setiap halaman baru:

| Elemen | Standar | Class / Token |
| --- | --- | --- |
| Header halaman | Selalu pakai komponen header konsisten | `<x-page-header>` |
| Layout kerja | Dua kolom: area kerja (kiri) + ringkasan/aksi (kanan) | `*-split` (`1.5fr / 1fr` atau `1fr / 360px`) |
| Panel ringkasan/aksi | Menempel saat scroll | `*-sticky` (`position: sticky; top: 16px`) |
| Warna status | Hanya untuk makna, bukan dekorasi | Hijau=sukses, Amber=peringatan, Merah=kritis |
| Disclosure | Sembunyikan opsi sekunder (Aksesoris/SIM) | tombol *collapsible* `opt-add-btn` |
| Umpan balik scan | Status bar + baris ber-highlight + ikon centang | `scan-status-bar`, `row-added` |
| Empty state | Pesan ramah + ikon, bukan tabel kosong | `<x-empty-state>` / `alert-feed-empty` |
| Shortcut | `Ctrl/Cmd + Enter` untuk submit utama | `shortcut-hint` |

**Rekomendasi lanjutan:**
1. ✅ **Modal drill-down sejati (SUDAH DIKERJAKAN):** setiap kartu angka di Dashboard kini membuka *modal* berisi daftar detail melalui endpoint JSON ringan `GET /dashboard/drilldown?metric=&view=` (`PageController@dashboardDrilldown`). Mendukung: In Stock, Issued, Installed, Total Devices, Aksesoris, SIM (stok & terpasang). Tetap menyediakan tautan "Buka halaman lengkap" sebagai *fallback*.
2. ✅ **Density toggle global (SUDAH DIKERJAKAN):** tombol di header menyimpan preferensi *Comfortable / Compact* di `localStorage` (`uiDensity`) dan menerapkan atribut `data-density` pada `<html>` (di-set lebih awal di `<head>` untuk mencegah *flash*). Override padding/ukuran global ada di `layouts/app.blade.php`.
3. ✅ **Komponen kartu metrik `<x-stat-card>` (SUDAH DIKERJAKAN):** pola kartu angka diekstrak ke `resources/views/components/stat-card.blade.php` (props: `color, icon, title, value, valueId, href, drill, hint`). Dipakai di Dashboard & Alert Center. Mendukung *slot* (mis. badge Baru/Bekas) dan `data-drill` untuk modal.
4. ✅ **Komponen feed peringatan `<x-alert-item>` (SUDAH DIKERJAKAN):** `resources/views/components/alert-item.blade.php` dengan 2 varian — `variant="feed"` (Dashboard, lengkap dengan *smart action button*) dan `variant="bell"` (ikon lonceng di header). Logika ikon, warna level, dan tombol aksi kini terpusat di satu tempat.

---

## 7. Area Teknisi & Monitoring Stok per Area

Karena tidak semua area memiliki kantor cabang/gudang, perangkat, GSM, dan aksesoris bisa langsung dikirim & dipegang teknisi. Untuk itu:

* **Master Teknisi** kini punya kolom **Area** (migration `add_area_to_technicians_table`, kolom `technicians.area`). Tersedia di form tambah/ubah (dengan *datalist* saran area), kolom tabel, import CSV (`code,name,area`), dan contoh CSV.
* **Dropdown teknisi** di menu *Issue* dan *Return* menampilkan area (mis. `Budi Santoso — Malang`) untuk konfirmasi cepat.
* **Dashboard** menampilkan widget **"Stok di Lapangan per Area (Teknisi)"** — agregasi Device & GSM (perangkat berstatus `ISSUED` yang dipegang teknisi) + Aksesoris (`HolderAccessory` tipe TECHNICIAN), dikelompokkan per area teknisi. Teknisi tanpa area dikelompokkan sebagai *"Tanpa Area"*.
* **Drill-down per area:** klik baris area di widget → modal menampilkan daftar perangkat di area tsb (metric `area_field` pada endpoint drilldown), lengkap dengan tautan "Buka halaman lengkap" ke Laporan (tab *Stok Teknisi*, ter-filter area).
* **Laporan → Stok Teknisi:** ditambah kolom **Area** + dropdown **filter Area** (client-side) dan kolom Area pada ekspor CSV/Excel. Tab dapat di-*deep-link* via `?tab=tech&area=...`.

---
*Dokumen ini merupakan aset internal IT & UX Team WMS EASTPRO 2026.*
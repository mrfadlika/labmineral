# Modul Data XRF Explorer 7000 (Versi PHP)

Folder ini berisi implementasi **Data XRF Explorer versi PHP** untuk sistem informasi laboratorium **AISPEKTRA LABORATORY (LabMineral)**.

---

## Daftar File
1. **`index.php`**
   - Halaman utama penjelajah data scan spektrometri XRF Explorer 7000.
   - Terintegrasi dengan session role admin LabMineral, layout header & sidebar.
   - Fitur: KPI card statistik scan, filter tanggal & mode (`mineral.db`, `alloy.db`, `metal.db`, kurva kalibrasi), pencarian, tabel elemental analysis, pagination, dan modal pop-up detail unsur.

2. **`dashboard.php`**
   - Halaman pemantauan (real-time monitor) log koneksi sinyal HTTP POST dan status transmisi data dari instrumen XRF Explorer 7000.

---

## Hubungan dengan Versi Standalone JavaScript (`xrf/`)
- Folder **`xrf/`**: Berisi aplikasi *Single-Page Application* berbasis Pure JavaScript / HTML / CSS dan Node/Express backend dengan live auto-sync 4 detik, grafik spektrum interaktif, dan charting canggih.
- Folder **`xrf_php/`**: Berisi modul terintegrasi berbasis PHP/MySQL yang menyatu langsung dengan dashboard sistem LabMineral.

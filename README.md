# 🧺 Backend Laundry Modern - PHP Native MVC

Sistem Backend dan Manajemen Laundry berbasis **PHP Native** menggunakan arsitektur **MVC (Model-View-Controller)**, database MySQL (`db_laundry`), dan antarmuka (UI) modern yang sederhana, bersih, dan responsif menggunakan Bootstrap 5.

---

## 📁 Struktur Direktori

Sesuai dengan struktur yang telah Anda tentukan:

```text
d:\xampp\htdocs\laundry\
├── app/
│   ├── controllers/
│   │   ├── AuthController.php       # Login, Register, Logout
│   │   ├── DashboardController.php  # Statistik & Ringkasan Pesanan
│   │   ├── LayananController.php    # CRUD Layanan/Paket Laundry
│   │   └── PesananController.php    # Transaksi, Detail, Status & Pembayaran
│   ├── middleware/
│   │   ├── AuthMiddleware.php       # Proteksi halaman harus login
│   │   └── GuestMiddleware.php      # Redirect user yang sudah login
│   ├── models/
│   │   ├── Model.php                # Base Model PDO
│   │   ├── User.php                 # Model tabel users
│   │   ├── Layanan.php              # Model tabel layanan
│   │   ├── Pesanan.php              # Model tabel pesanan
│   │   ├── DetailPesanan.php        # Model tabel detail_pesanan
│   │   ├── Pembayaran.php           # Model tabel pembayaran
│   │   ├── SlotJadwal.php           # Model tabel slot_jadwal
│   │   ├── StatusPesanan.php        # Model tabel status_pesanan (riwayat)
│   │   └── Nota.php                 # Model tabel nota
│   ├── services/
│   │   ├── AuthService.php          # Logika login & registrasi
│   │   └── PesananService.php       # Logika pesanan, kalkulasi berat/harga, & nota
│   └── validator/
│       └── Validator.php            # Validasi input form (required, numeric, email, dll.)
├── config/
│   ├── app.php                      # Konfigurasi aplikasi, autoloader SPL, & helper functions
│   └── database.php                 # Koneksi database PDO Singleton
├── database/
│   └── migrate.php                  # Script inisialisasi database & seeder data awal
├── public/
│   ├── .htaccess                    # Rewrite rule clean URL
│   ├── index.php                    # Front Controller (Entry Point)
│   ├── css/
│   │   └── custom.css               # Styling UI tambahan
│   └── js/
│       └── main.js                  # Script interaktif UI
├── resources/
│   └── view/
│       ├── auth/
│       │   ├── login.php            # Tampilan form login + tombol akun demo
│       │   └── register.php         # Tampilan form daftar akun pelanggan
│       ├── components/
│       │   ├── alerts.php           # Notifikasi flash message (sukses/gagal)
│       │   ├── navbar.php           # Top bar navigasi & profil user
│       │   └── sidebar.php          # Menu samping
│       ├── dashboard/
│       │   └── index.php            # Tampilan dashboard & statistik
│       ├── layouts/
│       │   ├── header.php
│       │   ├── footer.php
│       │   └── main.php             # Layout utama pembungkus halaman
│       ├── produk/                  # Manajemen Layanan Laundry
│       │   ├── index.php            # Daftar layanan (tarif / kg, estimasi waktu)
│       │   ├── create.php           # Tambah layanan baru
│       │   └── edit.php             # Edit layanan
│       └── transaksi/               # Manajemen Transaksi & Pesanan
│           ├── index.php            # Daftar pesanan pelanggan
│           ├── create.php           # Buat pesanan baru (multi-item & kalkulasi otomatis)
│           ├── detail.php           # Detail cucian, riwayat status, & pelunasan
│           └── nota.php             # Nota invoice siap cetak (print view)
├── routes/
│   ├── Router.php                   # Engine router lightweight PHP Native
│   ├── web.php                      # Rute halaman web
│   └── api.php                      # Rute API JSON
├── .htaccess                        # Redirect / rewrite root ke folder public
├── index.php                        # Forwarder ke folder public
└── db_laundry.sql                   # Skema database MySQL
```

---

## 🚀 Cara Menjalankan Aplikasi

### 1. Nyalakan Apache & MySQL di XAMPP Control Panel
Pastikan module **Apache** dan **MySQL** berstatus **Running** (berwarna hijau).

### 2. Jalankan Migrasi Database & Seeder Data Awal
Anda bisa menjalankan migrasi dengan salah satu cara berikut:

* **Cara A (Melalui Terminal / CMD):**
  ```bash
  cd d:\xampp\htdocs\laundry
  php database/migrate.php
  ```

* **Cara B (Melalui Web Browser):**
  Buka link berikut di browser Anda:
  ```text
  http://localhost/laundry/migrate
  ```

Script migrasi akan otomatis:
1. Membuat database `db_laundry` jika belum ada.
2. Mengimpor seluruh tabel dari `db_laundry.sql`.
3. Mengisi data default user (Admin, Kasir, Pelanggan).
4. Mengisi paket layanan laundry awal (Cuci Komplit, Cuci Kering, Setrika, Bedcover).
5. Mengisi slot jadwal dan transaksi contoh untuk pengujian.

---

## 👤 Akun Uji Coba Default

Pada halaman login, sudah disediakan **tombol 1-klik** untuk langsung mengisi akun demo:

| Role | Email | Password | Hak Akses |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin@laundry.com` | `admin123` | Akses penuh (Dashboard, Pesanan, Tambah/Hapus Layanan, Pelunasan) |
| **Kasir** | `kasir@laundry.com` | `kasir123` | Buat Pesanan, Update Status Cucian, Catat Pelunasan |
| **Pelanggan** | `pelanggan@laundry.com` | `pelanggan123` | Melihat status cucian miliknya, membuat pesanan mandiri |

---

## 🌐 Akses Aplikasi

Buka browser dan kunjungi:
* **`http://localhost/laundry/`** atau **`http://localhost/laundry/public/`**

---

## ✨ Fitur-Fitur yang Tersedia

1. **Autentikasi & Otorisasi:**
   - Login dengan enkripsi `password_hash()` yang aman.
   - Pendaftaran akun baru untuk pelanggan.
   - Middleware pengaman sesi login dan pencegahan akses tanpa hak.
2. **Dashboard Statistik:**
   - Ringkasan total pesanan, cucian sedang diproses, cucian selesai, dan total pendapatan/omset.
   - Tabel pesanan terbaru dengan status visual (badge).
3. **Manajemen Pelanggan (Baru):**
   - Menu khusus **Data Pelanggan** untuk Admin & Kasir.
   - Pendaftaran pelanggan baru secara langsung oleh admin/kasir (Nama, No WA/HP, Alamat, Email opsional, Password default `123456`).
   - Tombol langsung chat WhatsApp pelanggan (`wa.me`).
   - Tombol cepat **+ Tambah Pelanggan Baru** saat membuat pesanan baru tanpa keluar dari formulir order.
   - Edit data pelanggan & hapus pelanggan.
4. **Manajemen Layanan (Produk):**
   - Daftar layanan reguler & express beserta harga per kg dan estimasi jam pengerjaan.
   - Tambah, edit, dan hapus layanan.
5. **Manajemen Transaksi / Pesanan:**
   - Pembuatan pesanan baru dengan pilihan multi-item layanan dan kalkulasi otomatis.
   - Pelacakan alur cucian: *Menunggu &rarr; Diproses &rarr; Dicuci &rarr; Dikeringkan &rarr; Disetrika &rarr; Siap Diambil &rarr; Selesai*.
   - Riwayat log perubahan status oleh petugas.
   - Catatan pembayaran (Uang Muka/DP maupun Pelunasan) dengan metode Tunai, Transfer, atau QRIS.
   - Fitur **Cetak Nota Pesanan** yang rapi dan siap print.
6. **API Tracking:**
   - Endpoint JSON di `/api/tracking/{kode}` dan `/api/layanan`.


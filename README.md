# laundry
# Aplikasi Laundry Sederhana (PHP Native)

Contoh aplikasi belajar PHP prosedural, PDO/MySQL, session, dan Bootstrap 5. Tidak menggunakan framework.

## Menjalankan di XAMPP

1. Simpan folder proyek di `C:\xampp\htdocs\laundry`.
2. Jalankan Apache dan MySQL dari XAMPP.
3. Buka phpMyAdmin dan impor file `database.sql`. File ini membuat database, tabel, dan data demo.
4. Jika username atau kata sandi MySQL berbeda, ubah pengaturan di `config/database.php`.
5. Buka `http://localhost/laundry/`.

## Akun demo

| Peran | Username | Kata sandi |
| --- | --- | --- |
| Admin | `admin` | `password` |
| Kasir | `kasir` | `password` |
| Pelanggan | `pelanggan` | `password` |

Kode QR simulasi pelanggan demo adalah `QR-LAUNDRY-001`. Ketik kode ini pada formulir pesanan. Contoh ini tidak menggunakan kamera.

## Struktur folder

```text
laundry/
├── admin/
│   ├── index.php           # Ringkasan transaksi dan pendapatan
│   ├── layanan.php         # Tambah, edit, dan hapus layanan
│   └── laporan.php         # Laporan transaksi berdasarkan tanggal
├── assets/
│   ├── css/style.css       # Gaya tambahan untuk Bootstrap
│   └── js/app.js           # Hitung harga dan simulasi scan QR
├── config/
│   ├── database.php        # Koneksi PDO ke MySQL
│   └── helpers.php         # Fungsi bersama dan pemeriksaan peran
├── kasir/
│   ├── index.php           # Ringkasan pekerjaan kasir
│   ├── buat_pesanan.php    # Membuat pesanan dan menghitung harga
│   ├── pembayaran.php      # Mencatat DP atau pelunasan
│   └── update_status.php   # Memperbarui tahap cucian
├── pelanggan/
│   ├── index.php           # Melihat status pesanan terbaru
│   ├── riwayat.php         # Melihat transaksi pelanggan
│   └── nota.php            # Melihat atau mencetak nota digital
├── database.sql            # Struktur tabel dan data awal
├── index.php               # Form login
└── logout.php              # Mengakhiri sesi login
```

## Cara membaca alur kode

1. `index.php` menampilkan form login dan menerima username serta kata sandi.
2. `config/database.php` menyediakan koneksi database. `config/helpers.php` berisi fungsi bersama dan pemeriksaan hak akses.
3. Halaman admin, kasir, dan pelanggan mengambil data sesuai peran pengguna.
4. Saat form dikirim, PHP memeriksa input, menjalankan query database, lalu menampilkan hasilnya.
5. `config/helpers.php` berisi fungsi yang dipakai berulang, seperti menampilkan teks dengan aman dan memformat Rupiah.

Mulai belajar dari `index.php`, lalu ikuti alur membuat pesanan di `kasir/buat_pesanan.php`. Status pada dashboard pelanggan diperbarui dengan memuat ulang halaman setiap 15 detik. Komentar di dalam file menerangkan langkah-langkah penting.

Di halaman Admin > Layanan, masukkan harga sebagai angka tanpa titik atau tulisan "Rp". Contoh: ketik `7000` untuk harga Rp 7.000 per kg.

## Alur demo

1. Masuk sebagai kasir dan buat pesanan untuk pelanggan demo.
2. Catat pembayaran DP atau pelunasan.
3. Perbarui status cucian.
4. Keluar, lalu masuk sebagai pelanggan untuk melihat status dan nota.
5. Keluar, lalu masuk sebagai admin untuk mengatur layanan atau melihat laporan.

Contoh ini ditujukan untuk belajar di komputer lokal. Ganti akun demo dan atur keamanan server sebelum aplikasi digunakan sungguhan.

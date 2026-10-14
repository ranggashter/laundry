-- Buat database dahulu, lalu impor file ini melalui phpMyAdmin atau MySQL.
CREATE DATABASE IF NOT EXISTS laundry_app
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE laundry_app;

-- Akun untuk admin, kasir, dan pelanggan. Pelanggan memiliki kode untuk simulasi QR.
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'kasir', 'pelanggan') NOT NULL,
    kode_pelanggan VARCHAR(40) NULL UNIQUE,
    dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Daftar layanan serta harga dan durasi setiap kilogram.
CREATE TABLE layanan (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(80) NOT NULL,
    jenis ENUM('Reguler', 'Express') NOT NULL,
    harga_per_kg DECIMAL(12,2) NOT NULL,
    durasi_jam SMALLINT UNSIGNED NOT NULL,
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Satu baris mewakili satu pesanan laundry.
CREATE TABLE transaksi (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode_nota VARCHAR(40) NOT NULL UNIQUE,
    pelanggan_id INT UNSIGNED NOT NULL,
    kasir_id INT UNSIGNED NOT NULL,
    berat DECIMAL(8,2) NOT NULL,
    total DECIMAL(12,2) NOT NULL,
    dibayar DECIMAL(12,2) NOT NULL DEFAULT 0,
    status_pembayaran ENUM('Belum Lunas', 'Lunas') NOT NULL DEFAULT 'Belum Lunas',
    status_cucian ENUM('Menunggu', 'Diproses', 'Selesai', 'Diambil') NOT NULL DEFAULT 'Menunggu',
    dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pelanggan_id) REFERENCES users(id),
    FOREIGN KEY (kasir_id) REFERENCES users(id),
    INDEX idx_transaksi_tanggal (dibuat_pada),
    INDEX idx_transaksi_pelanggan (pelanggan_id)
) ENGINE=InnoDB;

-- Rincian layanan pada pesanan. Harga disimpan agar nota lama tidak berubah
-- jika admin mengubah harga layanan di kemudian hari.
CREATE TABLE detail_transaksi (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaksi_id BIGINT UNSIGNED NOT NULL,
    layanan_id INT UNSIGNED NOT NULL,
    nama_layanan VARCHAR(80) NOT NULL,
    harga_per_kg DECIMAL(12,2) NOT NULL,
    berat DECIMAL(8,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (transaksi_id) REFERENCES transaksi(id) ON DELETE CASCADE,
    FOREIGN KEY (layanan_id) REFERENCES layanan(id)
) ENGINE=InnoDB;

-- Setiap DP atau pelunasan memiliki catatan pembayaran tersendiri.
CREATE TABLE pembayaran (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaksi_id BIGINT UNSIGNED NOT NULL,
    kasir_id INT UNSIGNED NOT NULL,
    jumlah DECIMAL(12,2) NOT NULL,
    jenis ENUM('DP', 'Pelunasan') NOT NULL,
    dibayar_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaksi_id) REFERENCES transaksi(id) ON DELETE CASCADE,
    FOREIGN KEY (kasir_id) REFERENCES users(id),
    INDEX idx_pembayaran_tanggal (dibayar_pada)
) ENGINE=InnoDB;

-- Akun demo menggunakan kata sandi "password". Ganti setelah instalasi.
INSERT INTO users (nama, username, password, role, kode_pelanggan) VALUES
('Administrator', 'admin', '$2y$10$iJhWy4jH9sQCAZgX.GsQH.xT3aK/asNpSW/rF.iKNXemu1/QvdVtW', 'admin', NULL),
('Kasir Utama', 'kasir', '$2y$10$iJhWy4jH9sQCAZgX.GsQH.xT3aK/asNpSW/rF.iKNXemu1/QvdVtW', 'kasir', NULL),
('Pelanggan Demo', 'pelanggan', '$2y$10$iJhWy4jH9sQCAZgX.GsQH.xT3aK/asNpSW/rF.iKNXemu1/QvdVtW', 'pelanggan', 'QR-LAUNDRY-001');

INSERT INTO layanan (nama, jenis, harga_per_kg, durasi_jam) VALUES
('Cuci Reguler', 'Reguler', 7000, 48),
('Cuci Express', 'Express', 12000, 8);

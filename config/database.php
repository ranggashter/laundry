<?php
// Pengaturan koneksi database. Sesuaikan dengan MySQL di komputer Anda.
const DB_HOST = 'localhost';
const DB_NAME = 'laundry_app';
const DB_USER = 'root';
const DB_PASS = '';

// Membuka satu koneksi PDO dan menggunakannya kembali di halaman yang sama.
function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        // Opsi ini membuat PDO menampilkan error dan memakai prepared statement asli.
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }   

    return $pdo;
}

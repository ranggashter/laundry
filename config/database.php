<?php
$envPath = dirname(__DIR__) . '/.env';
$environment = is_file($envPath) ? parse_ini_file($envPath, false, INI_SCANNER_RAW) : [];
if ($environment === false) {
    throw new RuntimeException('File .env tidak dapat dibaca. Periksa format konfigurasi database.');
}

$readDatabaseSetting = static function (string $name, ?string $default = null) use ($environment): ?string {
    $value = getenv($name);
    if ($value !== false) {
        return $value;
    }

    return array_key_exists($name, $environment) ? (string) $environment[$name] : $default;
};

$databaseHost = $readDatabaseSetting('DB_HOST', '');
$databasePort = $readDatabaseSetting('DB_PORT', '3306');
$databaseName = $readDatabaseSetting('DB_NAME', '');
$databaseUser = $readDatabaseSetting('DB_USER', '');
$databasePassword = $readDatabaseSetting('DB_PASS', '');

if ($databaseHost === '' || $databaseName === '' || $databaseUser === '') {
    throw new RuntimeException('Konfigurasi DB_HOST, DB_NAME, dan DB_USER wajib diisi pada .env.');
}
if (!ctype_digit($databasePort) || (int) $databasePort < 1 || (int) $databasePort > 65535) {
    throw new RuntimeException('DB_PORT harus berupa angka antara 1 dan 65535.');
}

define('DB_HOST', $databaseHost);
define('DB_PORT', (int) $databasePort);
define('DB_NAME', $databaseName);
define('DB_USER', $databaseUser);
define('DB_PASS', $databasePassword);

// Membuka satu koneksi PDO dan menggunakannya kembali di halaman yang sama.
function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
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
 
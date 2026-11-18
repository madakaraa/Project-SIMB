<?php
// Pengaturan rahasia boleh ditaruh di api/koneksi.local.php (jangan di-push ke GitHub), isinya:
//   <?php define('DB_PASS','...'); define('ADMIN_PASS','...');
if (is_file(__DIR__.'/koneksi.local.php')) require __DIR__.'/koneksi.local.php';
defined('DB_HOST')    || define('DB_HOST', 'localhost');
defined('DB_NAME')    || define('DB_NAME', 'pyrowatch');
defined('DB_USER')    || define('DB_USER', 'root');   // default XAMPP
defined('DB_PASS')    || define('DB_PASS', '');       // default XAMPP: kosong
defined('ADMIN_USER') || define('ADMIN_USER', 'admin');
defined('ADMIN_PASS') || define('ADMIN_PASS', 'admin');  // WAJIB diganti

function db(): PDO {
    static $p = null;
    if (!$p) {
        $p = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $p->exec("SET time_zone='+00:00'");
    }
    return $p;
}
function out($d, int $c = 200): void {
    http_response_code($c);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}
function body(): array {
    $j = json_decode(file_get_contents('php://input'), true);
    return is_array($j) ? $j : [];
}
function gagal(Throwable $e): void {
    error_log($e->getMessage());
    out(['ok' => false, 'error' => 'Database tidak dapat diakses'], 500);
}

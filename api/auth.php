<?php
// Login admin berbasis sesi PHP
require __DIR__.'/koneksi.php';

function mulai_sesi(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'path' => '/']);
        session_start();
    }
}
function wajib_login(): void {
    mulai_sesi();
    if (empty($_SESSION['admin'])) out(['ok' => false, 'error' => 'Belum login'], 401);
}

if (basename($_SERVER['SCRIPT_NAME']) === 'auth.php') {
    mulai_sesi();
    $a = $_GET['aksi'] ?? 'status';
    if ($a === 'login') {
        $b = body();
        usleep(400000); // memperlambat tebak-tebakan password
        if (hash_equals(ADMIN_USER, (string)($b['u'] ?? '')) && hash_equals(ADMIN_PASS, (string)($b['p'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['admin'] = 1;
            out(['ok' => true]);
        }
        out(['ok' => false, 'error' => 'Username atau password salah'], 401);
    }
    if ($a === 'logout') { session_destroy(); out(['ok' => true]); }
    out(['ok' => !empty($_SESSION['admin'])]);
}

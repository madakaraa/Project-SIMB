<?php
// Upload gambar studi kasus (wajib login admin).
// Menyimpan ke uploads/studi/ lalu mengembalikan path relatif untuk kolom "gambar".
require __DIR__.'/auth.php';
wajib_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok'=>false,'error'=>'Gunakan POST'], 405);
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') out(['ok'=>false,'error'=>'Permintaan ditolak'], 403);

if (empty($_FILES['gambar'])) {
    out(['ok'=>false,'error'=>'Tidak ada file diterima (cek upload_max_filesize / post_max_size di php.ini).'], 400);
}
$f = $_FILES['gambar'];
if ($f['error'] !== UPLOAD_ERR_OK) {
    $msg = [
        UPLOAD_ERR_INI_SIZE  => 'Ukuran file melebihi batas server (upload_max_filesize di php.ini).',
        UPLOAD_ERR_FORM_SIZE => 'Ukuran file terlalu besar.',
        UPLOAD_ERR_PARTIAL   => 'Upload tidak lengkap, coba lagi.',
        UPLOAD_ERR_NO_FILE   => 'Pilih file gambar terlebih dahulu.',
    ][$f['error']] ?? 'Upload gagal (kode '.$f['error'].').';
    out(['ok'=>false,'error'=>$msg], 400);
}
if ($f['size'] > 5 * 1024 * 1024) out(['ok'=>false,'error'=>'Ukuran gambar maksimal 5 MB.'], 400);

$allowed = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
$info = @getimagesize($f['tmp_name']);
$mime = $info['mime'] ?? '';
if (function_exists('finfo_open')) {
    $fi = finfo_open(FILEINFO_MIME_TYPE); $mime = (string)finfo_file($fi, $f['tmp_name']); finfo_close($fi);
}
if (!$info || !isset($allowed[$mime])) out(['ok'=>false,'error'=>'Format harus JPG, PNG, atau WebP.'], 400);

$dir = dirname(__DIR__).'/uploads/studi';
if (!is_dir($dir) && !@mkdir($dir, 0755, true)) out(['ok'=>false,'error'=>'Folder uploads/studi tidak bisa dibuat.'], 500);
$name = 'studi-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.'.$allowed[$mime];
if (!move_uploaded_file($f['tmp_name'], $dir.'/'.$name)) out(['ok'=>false,'error'=>'Gagal menyimpan file ke server.'], 500);

out(['ok'=>true,'path'=>'uploads/studi/'.$name]);

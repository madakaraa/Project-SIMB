<?php
// Konten edukasi untuk index.html
require __DIR__.'/koneksi.php';
try {
    $d = db();
    $q = fn($sql) => $d->query($sql)->fetchAll();
    out([
        'ok'       => true,
        'jenis'    => $q('SELECT kelompok,nama,tingkat,deskripsi FROM jenis_kebakaran ORDER BY id'),
        'penyebab' => $q('SELECT no_urut,judul,deskripsi FROM penyebab_karhutla ORDER BY no_urut'),
        'dampak'   => $q('SELECT jenis,judul,deskripsi FROM dampak_karhutla ORDER BY id'),
        'mitigasi' => $q("SELECT fase,urutan,judul,deskripsi FROM mitigasi ORDER BY FIELD(fase,'pra','tanggap','pasca'),urutan"),
        'studi'    => $q('SELECT * FROM studi_kasus ORDER BY id'),
        'tim'      => $q('SELECT nama,nim,kelompok FROM tim_pengembang ORDER BY id'),
    ]);
} catch (Throwable $e) { gagal($e); }

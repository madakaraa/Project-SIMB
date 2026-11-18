<?php
// Menerima laporan warga dari form "Lapor Bencana" (POST JSON)
require __DIR__.'/koneksi.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok'=>false,'error'=>'Gunakan POST'], 405);
try {
    $b = body();
    $la = $b['lat'] ?? null; $lo = $b['lon'] ?? null;
    if (!is_numeric($la) || !is_numeric($lo) || abs($la) > 90 || abs($lo) > 180)
        out(['ok'=>false,'error'=>'Koordinat tidak valid'], 400);
    $d = db();
    $s = $d->prepare('SELECT id FROM jenis_laporan WHERE nama=?');
    $s->execute([(string)($b['j'] ?? '')]);
    $id = $s->fetchColumn() ?: $d->query("SELECT id FROM jenis_laporan WHERE nama='Lainnya'")->fetchColumn();
    if (!$id) out(['ok'=>false,'error'=>'Jenis laporan tidak dikenal'], 400);
    $d->prepare('INSERT INTO laporan_warga (jenis_id,latitude,longitude,keterangan) VALUES (?,?,?,?)')
      ->execute([$id, (float)$la, (float)$lo, mb_substr(trim((string)($b['k'] ?? '')), 0, 1000)]);
    out(['ok'=>true,'id'=>(int)$d->lastInsertId()]);
} catch (Throwable $e) { gagal($e); }

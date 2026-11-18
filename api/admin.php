<?php
// CRUD generik untuk tabel yang ada di daftar putih $T (wajib login)
require __DIR__.'/auth.php';
wajib_login();

// grup, label, ikon, kolom. Tipe: text, area, num, enum:a|b, fk:tabel ; akhiran ? = boleh kosong
$T = [
 'penyebab_karhutla' => ['Konten Edukasi','Penyebab Karhutla','fa-bolt',['no_urut'=>'num','judul'=>'text','deskripsi'=>'area']],
 'dampak_karhutla'   => ['Konten Edukasi','Dampak Karhutla','fa-chart-line',['jenis'=>'enum:negatif|positif','judul'=>'text','deskripsi'=>'area']],
 'mitigasi'          => ['Konten Edukasi','Mitigasi','fa-shield-halved',['fase'=>'enum:pra|tanggap|pasca','urutan'=>'num','judul'=>'text','deskripsi'=>'area']],
 'jenis_kebakaran'   => ['Konten Edukasi','Jenis Kebakaran','fa-fire',['kelompok'=>'enum:umum|kawasan|vertikal','nama'=>'text','tingkat'=>'text?','deskripsi'=>'area']],
 'studi_kasus'       => ['Konten Edukasi','Studi Kasus','fa-book-open',['judul'=>'text','lokasi'=>'text','tahun'=>'num','sumber'=>'text?','deskripsi'=>'area']],
 'tim_pengembang'    => ['Konten Edukasi','Tim Pengembang','fa-users',['nama'=>'text','nim'=>'text','kelompok'=>'text']],
 'khg_zona'          => ['Data Peta','Zona KHG Gambut','fa-layer-group',['nama'=>'text','kedalaman_cm'=>'num','provinsi'=>'text']],
 'khg_koordinat'     => ['Data Peta','Titik Poligon KHG','fa-draw-polygon',['khg_id'=>'fk:khg_zona','urutan'=>'num','longitude'=>'num','latitude'=>'num']],
 'wilayah'           => ['Data Peta','Wilayah Cepat','fa-location-dot',['nama'=>'text','longitude'=>'num','latitude'=>'num','zoom'=>'num','pitch'=>'num','bearing'=>'num']],
 'kota_aqi'          => ['Data Peta','Kota Pemantau AQI','fa-smog',['nama'=>'text','latitude'=>'num','longitude'=>'num']],
 'jenis_laporan'     => ['Laporan Warga','Jenis Laporan','fa-list',['nama'=>'text']],
 'laporan_warga'     => ['Laporan Warga','Laporan Warga','fa-bullhorn',['jenis_id'=>'fk:jenis_laporan','latitude'=>'num','longitude'=>'num','keterangan'=>'area?','status'=>'enum:belum_diverifikasi|terverifikasi|ditolak|selesai']],
];
$ORD = ['laporan_warga'=>'id DESC','khg_koordinat'=>'khg_id,urutan','mitigasi'=>'fase,urutan','penyebab_karhutla'=>'no_urut'];

function kolom(array $cols): array {
    $o = [];
    foreach ($cols as $n => $s) {
        $null = substr($s, -1) === '?'; $s = rtrim($s, '?'); $p = explode(':', $s, 2);
        $c = ['n'=>$n, 'type'=>$p[0], 'null'=>$null];
        if ($p[0] === 'enum') $c['opts'] = explode('|', $p[1]);
        if ($p[0] === 'fk')   $c['fk']   = $p[1];
        $o[] = $c;
    }
    return $o;
}
function cek(array $c, $v) {
    $lb = str_replace('_', ' ', $c['n']);
    if ($v === null || trim((string)$v) === '') {
        if ($c['null']) return null;
        throw new InvalidArgumentException("Kolom \"$lb\" wajib diisi");
    }
    $v = trim((string)$v);
    switch ($c['type']) {
        case 'num':  if (!is_numeric($v)) throw new InvalidArgumentException("Kolom \"$lb\" harus berupa angka"); return $v + 0;
        case 'enum': if (!in_array($v, $c['opts'], true)) throw new InvalidArgumentException("Pilihan \"$lb\" tidak valid"); return $v;
        case 'fk':   if ((int)$v < 1) throw new InvalidArgumentException("Pilih \"$lb\""); return (int)$v;
        default:     return mb_substr($v, 0, 5000);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch')
    out(['ok'=>false,'error'=>'Permintaan ditolak'], 403);

try {
    $d = db(); $a = $_GET['aksi'] ?? 'daftar';
    if ($a === 'semua') {
        $l = [];
        foreach ($T as $t => $v) $l[] = ['t'=>$t,'grup'=>$v[0],'label'=>$v[1],'ic'=>$v[2]];
        out(['ok'=>true,'tabel'=>$l]);
    }
    $b = $_SERVER['REQUEST_METHOD'] === 'POST' ? body() : [];
    $t = $_GET['t'] ?? ($b['t'] ?? '');
    if (!isset($T[$t])) out(['ok'=>false,'error'=>'Tabel tidak dikenal'], 400);
    [$grup, $label, $ic, $raw] = $T[$t];
    $cols = kolom($raw);

    if ($a === 'daftar') {
        $q = trim((string)($_GET['q'] ?? '')); $w = ''; $p = [];
        $tx = array_values(array_filter($cols, fn($c) => in_array($c['type'], ['text','area'])));
        if ($q !== '' && $tx) {
            $w = ' WHERE '.implode(' OR ', array_map(fn($c) => "`{$c['n']}` LIKE ?", $tx));
            $p = array_fill(0, count($tx), '%'.$q.'%');
        }
        $n = $d->prepare("SELECT COUNT(*) FROM `$t`$w"); $n->execute($p);
        $s = $d->prepare("SELECT * FROM `$t`$w ORDER BY ".($ORD[$t] ?? 'id')." LIMIT 300"); $s->execute($p);
        $fk = [];
        foreach ($cols as $c) if ($c['type'] === 'fk') $fk[$c['n']] = $d->query("SELECT id,nama FROM `{$c['fk']}` ORDER BY id")->fetchAll();
        out(['ok'=>true,'label'=>$label,'cols'=>$cols,'rows'=>$s->fetchAll(),'total'=>(int)$n->fetchColumn(),'fk'=>(object)$fk]);
    }

    $id = (int)($b['id'] ?? 0);
    if ($a === 'hapus') {
        if ($id < 1) out(['ok'=>false,'error'=>'ID tidak valid'], 400);
        $d->prepare("DELETE FROM `$t` WHERE id=?")->execute([$id]);
        out(['ok'=>true]);
    }
    if ($a === 'tambah' || $a === 'ubah') {
        $v = [];
        foreach ($cols as $c) $v[$c['n']] = cek($c, $b['data'][$c['n']] ?? null);
        $k = array_keys($v);
        if ($a === 'tambah') {
            $d->prepare('INSERT INTO `'.$t.'` (`'.implode('`,`', $k).'`) VALUES ('.implode(',', array_fill(0, count($k), '?')).')')->execute(array_values($v));
        } else {
            if ($id < 1) out(['ok'=>false,'error'=>'ID tidak valid'], 400);
            $d->prepare('UPDATE `'.$t.'` SET '.implode(',', array_map(fn($x) => "`$x`=?", $k)).' WHERE id=?')->execute([...array_values($v), $id]);
        }
        out(['ok'=>true]);
    }
    out(['ok'=>false,'error'=>'Aksi tidak dikenal'], 400);
} catch (InvalidArgumentException $e) {
    out(['ok'=>false,'error'=>$e->getMessage()], 400);
} catch (PDOException $e) {
    $c = (string)$e->getCode();
    if ($c === '23000') out(['ok'=>false,'error'=>'Gagal: data duplikat atau masih dipakai data lain.'], 409);
    if ($c === '22001') out(['ok'=>false,'error'=>'Teks terlalu panjang untuk kolom ini.'], 400);
    if ($c === '22003') out(['ok'=>false,'error'=>'Angka di luar batas yang diizinkan.'], 400);
    gagal($e);
} catch (Throwable $e) { gagal($e); }

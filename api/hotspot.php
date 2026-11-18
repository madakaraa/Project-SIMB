<?php
// GET  : ambil hotspot tersimpan (cadangan bila NASA FIRMS tidak terjangkau)
// POST : simpan hotspot hasil tarikan FIRMS; POST ?aksi=penyebab : ubah penyebab satu titik
require __DIR__.'/koneksi.php';
try {
    $d = db();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $n = max(1, min(10, (int)($_GET['days'] ?? 5))) - 1;
        $q = $d->query("SELECT h.longitude,h.latitude,h.frp,h.confidence,h.tgl_akuisisi,h.jam_akuisisi,h.satelit,h.bright_ti4,h.lapisan,
                               z.nama AS khg,z.kedalaman_cm
                        FROM titik_panas h LEFT JOIN khg_zona z ON z.id=h.khg_id
                        WHERE h.tgl_akuisisi >= DATE_SUB((SELECT MAX(tgl_akuisisi) FROM titik_panas), INTERVAL $n DAY)");
        $r = [];
        foreach ($q as $x) {
            $lo = (float)$x['longitude']; $la = (float)$x['latitude'];
            $r[] = ['lon'=>$lo,'lat'=>$la,'frp'=>(float)$x['frp'],'conf'=>$x['confidence'],'d'=>$x['tgl_akuisisi'],'t'=>$x['jam_akuisisi'],
                    'sat'=>$x['satelit'] ?? '','tb'=>(float)$x['bright_ti4'],'khg'=>$x['khg'] ?? '','dp'=>(int)$x['kedalaman_cm'],
                    'ly'=>$x['lapisan'] === 'gambut' ? 'p' : 's',
                    'key'=>number_format($lo,3,'.','').','.number_format($la,3,'.','')];
        }
        out(['ok'=>true,'data'=>$r]);
    }
    $b = body();
    if (($_GET['aksi'] ?? '') === 'penyebab') {
        if (!in_array($b['penyebab'] ?? '', ['alam','manusia','investigasi'], true)) out(['ok'=>false], 400);
        [$lo, $la] = array_map('floatval', explode(',', (string)($b['key'] ?? '0,0')));
        $d->prepare('UPDATE titik_panas SET penyebab=? WHERE ROUND(longitude,3)=ROUND(?,3) AND ROUND(latitude,3)=ROUND(?,3)')
          ->execute([$b['penyebab'], $lo, $la]);
        out(['ok'=>true]);
    }
    $ids = [];
    foreach ($d->query('SELECT id,nama FROM khg_zona') as $z) $ids[$z['nama']] = (int)$z['id'];
    $s = $d->prepare('INSERT IGNORE INTO titik_panas (longitude,latitude,frp,confidence,tgl_akuisisi,jam_akuisisi,satelit,bright_ti4,khg_id,lapisan)
                      VALUES (?,?,?,?,?,?,?,?,?,?)');
    $d->beginTransaction(); $baru = 0;
    foreach (array_slice($b['rows'] ?? [], 0, 20000) as $r) {
        if (!isset($r['lon'], $r['lat'], $r['d']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $r['d'])) continue;
        $c = in_array($r['conf'] ?? 'n', ['l','n','h'], true) ? $r['conf'] : 'n';
        $jam = str_pad(substr(preg_replace('/\D/', '', (string)($r['t'] ?? '0')), 0, 4), 4, '0', STR_PAD_LEFT);
        $s->execute([(float)$r['lon'], (float)$r['lat'], (float)($r['frp'] ?? 0), $c, $r['d'], $jam,
                     substr((string)($r['sat'] ?? ''), 0, 10), is_numeric($r['tb'] ?? null) ? (float)$r['tb'] : null,
                     $ids[$r['khg'] ?? ''] ?? null, ($r['ly'] ?? 's') === 'p' ? 'gambut' : 'permukaan']);
        $baru += $s->rowCount();
    }
    $d->commit();
    out(['ok'=>true,'baru'=>$baru]);
} catch (Throwable $e) {
    if (isset($d) && $d->inTransaction()) $d->rollBack();
    gagal($e);
}

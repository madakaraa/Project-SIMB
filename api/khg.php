<?php
// Mengubah tabel khg_zona + khg_koordinat menjadi GeoJSON untuk peta
require __DIR__.'/koneksi.php';
try {
    $d = db();
    $ring = [];
    foreach ($d->query('SELECT khg_id,longitude,latitude FROM khg_koordinat ORDER BY khg_id,urutan') as $r)
        $ring[$r['khg_id']][] = [(float)$r['longitude'], (float)$r['latitude']];
    $f = [];
    foreach ($d->query('SELECT id,nama,kedalaman_cm,provinsi FROM khg_zona ORDER BY id') as $z) {
        $c = $ring[$z['id']] ?? [];
        if (count($c) < 3) continue;
        $c[] = $c[0];
        $f[] = ['type'=>'Feature',
                'properties'=>['nama'=>$z['nama'],'kedalaman_cm'=>(int)$z['kedalaman_cm'],'prov'=>$z['provinsi']],
                'geometry'=>['type'=>'Polygon','coordinates'=>[$c]]];
    }
    out(['type'=>'FeatureCollection','features'=>$f]);
} catch (Throwable $e) { gagal($e); }

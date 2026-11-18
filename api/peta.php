<?php
// Data pendukung dashboard: wilayah, kategori AQI, kota, jenis laporan, laporan warga, penyebab hotspot
require __DIR__.'/koneksi.php';
try {
    $d = db();
    $w = [];
    foreach ($d->query('SELECT nama,longitude,latitude,zoom,pitch,bearing FROM wilayah ORDER BY id') as $r)
        $w[$r['nama']] = ['c'=>[(float)$r['longitude'],(float)$r['latitude']],'z'=>(float)$r['zoom'],'p'=>(int)$r['pitch'],'b'=>(int)$r['bearing']];
    $a = [];
    foreach ($d->query('SELECT batas_atas,label,warna_hex FROM kategori_aqi ORDER BY id') as $r)
        $a[] = [$r['batas_atas'] === null ? 1000000000 : (int)$r['batas_atas'], $r['label'], $r['warna_hex']];
    $k = [];
    foreach ($d->query('SELECT nama,latitude,longitude FROM kota_aqi ORDER BY id') as $r)
        $k[] = [$r['nama'], (float)$r['latitude'], (float)$r['longitude']];
    $j = $d->query('SELECT nama FROM jenis_laporan ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $st = ['belum_diverifikasi'=>'Belum Diverifikasi','terverifikasi'=>'Terverifikasi','ditolak'=>'Ditolak','selesai'=>'Selesai'];
    $l = [];
    foreach ($d->query('SELECT jenis,latitude,longitude,keterangan,status,waktu_lapor FROM v_laporan_warga LIMIT 200') as $r)
        $l[] = ['lat'=>(float)$r['latitude'],'lon'=>(float)$r['longitude'],'j'=>$r['jenis'],'k'=>$r['keterangan'] ?? '',
                't'=>strtotime($r['waktu_lapor'].' UTC')*1000,'s'=>$st[$r['status']] ?? $r['status']];
    $p = [];
    foreach ($d->query("SELECT longitude,latitude,penyebab FROM titik_panas WHERE penyebab<>'investigasi'") as $r)
        $p[number_format((float)$r['longitude'],3,'.','').','.number_format((float)$r['latitude'],3,'.','')] = $r['penyebab'];
    out(['ok'=>true,'wilayah'=>$w,'aqi'=>$a,'kota'=>$k,'jenis'=>$j,'laporan'=>$l,'penyebab'=>(object)$p]);
} catch (Throwable $e) { gagal($e); }

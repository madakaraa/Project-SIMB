<?php
// =========================================================================
// BAGIAN BACKEND PHP: PENGAMBILAN & PENGOLAHAN DATA DARI DATABASE VIA API
// =========================================================================

// Polyfill untuk PHP < 8: membuat fungsi str_contains manual jika versi PHP server masih 7.x
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle === '' || strpos((string)$haystack, (string)$needle) !== false;
    }
}

// Ambil data dari api/edukasi.php lewat request HTTP internal (loopback).
// Tujuannya agar perintah exit()/die() di dalam file API tidak ikut menghentikan proses render HTML di index.php.
$dbData = null;
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$apiUrl   = $scheme . '://' . $host . $basePath . '/api/edukasi.php';

// Eksekusi request ke endpoint API menggunakan cURL sebagai metode utama
$rawJson = false;
if (function_exists('curl_init')) {
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => ['X-Requested-With: fetch'],
    ]);
    $rawJson = curl_exec($ch);
    curl_close($ch);
}

// Fallback (cadangan): jika ekstensi cURL tidak aktif di server, gunakan file_get_contents
if (!is_string($rawJson) || $rawJson === '') {
    $ctx = stream_context_create([
        'http' => ['timeout' => 5, 'header' => "X-Requested-With: fetch\r\n"],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $rawJson = @file_get_contents($apiUrl, false, $ctx);
}

// Bersihkan karakter sebelum kurung kurawal '{' pertama (jika ada spasi/warning), lalu ubah JSON menjadi array PHP
if (is_string($rawJson) && $rawJson !== '') {
    $jsonStart = strpos($rawJson, '{');
    if ($jsonStart !== false) {
        $decoded = json_decode(substr($rawJson, $jsonStart), true);
        if (is_array($decoded) && !empty($decoded['ok'])) {
            $dbData = $decoded;
        }
    }
}

// Pastikan browser merender halaman ini sebagai dokumen HTML UTF-8 (bukan JSON)
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8', true);
}

// Fungsi helper untuk mencegah serangan XSS (Cross-Site Scripting) saat mencetak teks dari database ke HTML.
// Dibungkus function_exists agar tidak terjadi error "Cannot redeclare h()" jika fungsi serupa sudah ada.
if (!function_exists('h')) {
    function h($str) {
        return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// -------------------------------------------------------------------------
// 1. PENGOLAHAN DATA EDUKASI & KLASIFIKASI JENIS KARHUTLA
// -------------------------------------------------------------------------
$jenisAll = $dbData['jenis'] ?? [];

// Teks default pengertian umum jika di database belum diisi
$pengertianUmum = 'Peristiwa terbakarnya vegetasi di suatu wilayah yang meluas secara tidak terkendali dan menimbulkan dampak kerugian ekologi, sosial, maupun ekonomi secara masif.';
foreach ($jenisAll as $j) {
    if (($j['kelompok'] ?? '') === 'umum' && !empty($j['deskripsi'])) {
        $pengertianUmum = $j['deskripsi'];
        break;
    }
}

// Filter data khusus kelompok 'kawasan' (Kebakaran Hutan vs Kebakaran Lahan)
$kawasanList = array_values(array_filter($jenisAll, fn($j) => ($j['kelompok'] ?? '') === 'kawasan'));
if (empty($kawasanList)) {
    // Data cadangan jika tabel kosong
    $kawasanList = [
        [
            'nama' => 'Kebakaran Hutan',
            'deskripsi' => 'Terjadi di dalam kawasan hutan yang didominasi oleh tegakan pepohonan alami, keanekaragaman flora liar, dan ekosistem primer/sekunder. Berdampak pada kerusakan habitat satwa.'
        ],
        [
            'nama' => 'Kebakaran Lahan',
            'deskripsi' => 'Terjadi di luar kawasan hutan, seperti area perkebunan (sawit/karet), lahan pertanian, rawa belukar, atau area kosong milik warga. Sering terkait pembukaan lahan.'
        ]
    ];
}

// Filter data khusus kelompok 'vertikal' (3 Lapisan/Strata Api) + konfigurasi badge/ikon default-nya
$vertikalList = array_values(array_filter($jenisAll, fn($j) => ($j['kelompok'] ?? '') === 'vertikal'));
$strataDefaults = [
    'tajuk' => [
        'badge' => 'Tingkat Atas • Crown Fire',
        'tag' => 'Tingkat Atas',
        'icon' => 'fa-wind',
        'iconColor' => 'text-red-600',
        'tagClass' => 'text-red-700 bg-red-50',
        'badgeClass' => 'inline-block font-mono text-[11px] font-bold uppercase tracking-wider bg-red-500/20 text-red-300 border border-red-400/30 px-3 py-1 rounded-full mb-3',
        'title' => 'Api Tajuk (Crown Fire)',
        'desc' => 'Membakar pucuk pohon tinggi dan melompat antar-pohon dengan bantuan angin. Paling spektakuler & sulit dipadamkan dari darat.',
        'speed' => 'Sangat Cepat (Didorong Angin)',
        'action' => 'Water Bombing & TMC',
        'cardBorder' => 'border-2 border-red-500'
    ],
    'permukaan' => [
        'badge' => 'Permukaan • Surface Fire',
        'tag' => 'Permukaan',
        'icon' => 'fa-fire',
        'iconColor' => 'text-ember',
        'tagClass' => 'text-ember bg-ember-soft',
        'badgeClass' => 'inline-block font-mono text-[11px] font-bold uppercase tracking-wider bg-orange-500/20 text-orange-300 border border-orange-400/30 px-3 py-1 rounded-full mb-3',
        'title' => 'Api Permukaan (Surface Fire)',
        'desc' => 'Membakar serasah dedaunan kering dan semak belukar di atas tanah. Umumnya pemicu awal, dipadamkan dengan sekat bakar.',
        'speed' => 'Cepat di Serasah Kering',
        'action' => 'Sekat Bakar & Regu Darat',
        'cardBorder' => 'border-2 border-ember'
    ],
    'bawah' => [
        'badge' => 'Bawah Tanah • Ground Fire',
        'tag' => 'Bawah Tanah',
        'icon' => 'fa-smog',
        'iconColor' => 'text-amber-700',
        'tagClass' => 'text-amber-800 bg-amberbrand-soft',
        'badgeClass' => 'inline-block font-mono text-[11px] font-bold uppercase tracking-wider bg-amber-500/20 text-amber-300 border border-amber-400/30 px-3 py-1 rounded-full mb-3',
        'title' => 'Api Bawah (Ground Fire / Gambut)',
        'desc' => 'Merambat perlahan di bawah tanah (lahan gambut tebal) tanpa nyala lidah api. Asap pekat, sangat sulit dideteksi & dipadamkan.',
        'speed' => 'Lambat Namun Persisten',
        'action' => 'Injeksi Air & Sekat Kanal',
        'cardBorder' => 'border-2 border-amber-500'
    ]
];

// Mencocokkan judul & deskripsi dari database ke masing-masing kunci strata (tajuk, permukaan, bawah)
$strataKeys = ['tajuk', 'permukaan', 'bawah'];
$matchedStrata = [];
foreach ($vertikalList as $vItem) {
    $haystack = strtolower(($vItem['tingkat'] ?? '') . ' ' . ($vItem['nama'] ?? ''));
    $targetKey = null;
    if (str_contains($haystack, 'tajuk') || str_contains($haystack, 'atas') || str_contains($haystack, 'crown')) {
        $targetKey = 'tajuk';
    } elseif (str_contains($haystack, 'permukaan') || str_contains($haystack, 'surface')) {
        $targetKey = 'permukaan';
    } elseif (str_contains($haystack, 'bawah') || str_contains($haystack, 'gambut') || str_contains($haystack, 'ground')) {
        $targetKey = 'bawah';
    }
    if ($targetKey !== null && !isset($matchedStrata[$targetKey])) {
        $matchedStrata[$targetKey] = true;
        if (!empty($vItem['nama'])) $strataDefaults[$targetKey]['title'] = $vItem['nama'];
        if (!empty($vItem['deskripsi'])) $strataDefaults[$targetKey]['desc'] = $vItem['deskripsi'];
    }
}
// Jika pencocokan kata kunci tidak ketemu, isi berurutan berdasarkan indeks array
if (empty($matchedStrata)) {
    foreach ($strataKeys as $idx => $sKey) {
        if (isset($vertikalList[$idx])) {
            if (!empty($vertikalList[$idx]['nama'])) $strataDefaults[$sKey]['title'] = $vertikalList[$idx]['nama'];
            if (!empty($vertikalList[$idx]['deskripsi'])) $strataDefaults[$sKey]['desc'] = $vertikalList[$idx]['deskripsi'];
        }
    }
}

// -------------------------------------------------------------------------
// 2. PENGOLAHAN DATA PENYEBAB KARHUTLA
// -------------------------------------------------------------------------
$penyebabList = $dbData['penyebab'] ?? [];
if (empty($penyebabList)) {
    // Data cadangan jika tabel penyebab di database kosong
    $penyebabList = [
        ['judul' => 'Pembukaan Lahan dengan Membakar', 'deskripsi' => 'Metode tebas-bakar dipilih oknum karena dianggap cara tercepat & termurah membersihkan lahan, meski berisiko tinggi lepas kendali dan merembet ke kawasan sekitarnya.'],
        ['judul' => 'Kelalaian Manusia', 'deskripsi' => 'Membuang puntung rokok sembarangan, meninggalkan api unggun yang tak padam tuntas, atau membakar sampah di dekat semak saat angin bertiup kencang.'],
        ['judul' => 'Kondisi Cuaca Panas & Musim Kemarau', 'deskripsi' => 'Suhu tinggi dan minim curah hujan (anomali El Niño) menyusutkan kadar air vegetasi secara drastis, menjadikannya bahan bakar kering yang sangat mudah terpercik.'],
        ['judul' => 'Angin Kencang', 'deskripsi' => 'Menyuplai oksigen berlimpah ke pusat pembakaran serta menerbangkan loncatan bara (firebrands), mempercepat penyebaran dan lompatan api melewati sekat bakar.'],
        ['judul' => 'Lahan Gambut yang Kering', 'deskripsi' => 'Drainase kanal berlebihan mengeringkan kubah gambut. Material organik tebal ini berubah menjadi bahan bakar padat yang membara di bawah tanah dalam waktu lama.'],
        ['judul' => 'Sambaran Petir', 'deskripsi' => 'Faktor murni alam berupa petir kering (dry lightning) tanpa disertai hujan lebat dapat menyambar tajuk pohon tua dan memicu api langsung di hutan terpencil.']
    ];
}

// Metadata label teknis untuk melengkapi tampilan tiap slide penyebab
$penyebabMeta = [
    ['cat' => 'Antropogenik', 'catColor' => 'text-ember', 'm1Label' => 'KATEGORI PEMICU', 'm1Val' => 'Aktivitas Manusia', 'm2Label' => 'TINGKAT ESKALASI', 'm2Val' => 'Sangat Tinggi', 'm2Color' => 'text-amberbrand'],
    ['cat' => 'Kelalaian Publik', 'catColor' => 'text-rose-400', 'm1Label' => 'TITIK AWAL API', 'm1Val' => 'Puntung & Api Unggun', 'm2Label' => 'MITIGASI KUNCI', 'm2Val' => 'Disiplin & Patroli', 'm2Color' => 'text-amberbrand'],
    ['cat' => 'Anomali Iklim', 'catColor' => 'text-amberbrand', 'm1Label' => 'FENOMENA GLOBAL', 'm1Val' => 'El Niño & Heatwave', 'm2Label' => 'KONDISI VEGETASI', 'm2Val' => 'Kadar Air Kritis', 'm2Color' => 'text-amberbrand'],
    ['cat' => 'Dinamika Atmosfer', 'catColor' => 'text-sky-400', 'm1Label' => 'MEKANISME SEBAR', 'm1Val' => 'Loncatan Firebrands', 'm2Label' => 'SUPLAI PEMBAKARAN', 'm2Val' => 'Oksigen Tinggi', 'm2Color' => 'text-sky-400'],
    ['cat' => 'Degradasi Hidrologi', 'catColor' => 'text-emerald-400', 'm1Label' => 'ZONA RENTAN', 'm1Val' => 'KHG Terdrainase', 'm2Label' => 'KARAKTER API', 'm2Val' => 'Bara Bawah Tanah', 'm2Color' => 'text-amberbrand'],
    ['cat' => 'Fenomena Alam', 'catColor' => 'text-indigo-400', 'm1Label' => 'KLASIFIKASI', 'm1Val' => 'Pemicu Alami Murni', 'm2Label' => 'FENOMENA', 'm2Val' => 'Dry Lightning', 'm2Color' => 'text-indigo-300'],
];

// -------------------------------------------------------------------------
// 3. PENGOLAHAN DATA DAMPAK KARHUTLA (NEGATIF VS POSITIF ALAMI)
// -------------------------------------------------------------------------
$dampakAll = $dbData['dampak'] ?? [];
$dampakNegatif = array_values(array_filter($dampakAll, fn($d) => ($d['jenis'] ?? '') === 'negatif'));
$dampakPositif = array_values(array_filter($dampakAll, fn($d) => ($d['jenis'] ?? '') === 'positif'));

// Data cadangan dampak negatif
if (empty($dampakNegatif)) {
    $dampakNegatif = [
        ['judul' => 'Kerusakan Ekosistem', 'deskripsi' => 'Musnahnya flora langka serta hilangnya habitat alami satwa liar endemik seperti orangutan dan harimau.'],
        ['judul' => 'Perubahan Iklim', 'deskripsi' => 'Pelepasan emisi karbon dan gas rumah kaca secara masif—terutama dari lahan gambut—mempercepat laju pemanasan global.'],
        ['judul' => 'Dampak Sosial', 'deskripsi' => 'Masyarakat kehilangan tempat tinggal, sekolah dan fasilitas publik terpaksa tutup, serta aktivitas sosial warga lumpuh.'],
        ['judul' => 'Polusi Udara', 'deskripsi' => 'Kabut asap pekat bermuatan partikulat halus (PM2.5) memicu lonjakan kasus ISPA, asma akut, dan gangguan iritasi mata.'],
        ['judul' => 'Kerugian Ekonomi', 'deskripsi' => 'Terganggunya sektor pertanian, pembatalan jalur penerbangan, lesunya pariwisata, serta beban biaya pemadaman triliunan rupiah.'],
        ['judul' => 'Bencana Lanjutan', 'deskripsi' => 'Lahan gundul bekas terbakar kehilangan daya serap air sehingga memicu banjir bandang dan tanah longsor saat musim hujan tiba.']
    ];
}

// Data cadangan dampak positif alami (kebakaran terkendali)
if (empty($dampakPositif)) {
    $dampakPositif = [
        ['judul' => 'Menjaga Keanekaragaman Hayati', 'deskripsi' => 'Membersihkan gulma invasif yang mendominasi lantai hutan, memberi ruang dan cahaya bagi spesies tanaman asli untuk tumbuh seimbang.'],
        ['judul' => 'Menciptakan Habitat Baru', 'deskripsi' => 'Batang kayu mati hasil kebakaran alami skala kecil menjadi ruang bersarang dan sumber nutrisi bagi serangga pengurai serta burung tertentu.'],
        ['judul' => 'Memperkaya Nutrisi Tanah', 'deskripsi' => 'Abu organik sisa pembakaran mengembalikan unsur hara penting seperti Kalium dan Fosfor ke tanah untuk mempercepat suksesi tumbuhan baru.'],
        ['judul' => 'Mengontrol Pertumbuhan Berlebih', 'deskripsi' => 'Pembakaran terkendali (prescribed burning) mengurangi akumulasi serasah kering berlebih yang berpotensi memicu kebakaran raksasa.']
    ];
}

// Daftar ikon FontAwesome dan label kategori untuk kartu dampak
$negIcons = ['fa-skull-crossbones', 'fa-earth-americas', 'fa-house-chimney-crack', 'fa-lungs-virus', 'fa-chart-line', 'fa-water', 'fa-triangle-exclamation'];
$negTags  = ['EKOLOGI', 'ATMOSFER', 'SOSIAL', 'KESEHATAN', 'EKONOMI', 'HIDROLOGI', 'DAMPAK KARHUTLA'];
$posIcons = ['fa-leaf', 'fa-bugs', 'fa-flask', 'fa-fire-burner', 'fa-seedling'];
$posTags  = ['REGENERASI', 'MIKROHABITAT', 'MINERAL TANAH', 'REDUKSI BEBAN', 'EKOLOGI ALAMI'];

// -------------------------------------------------------------------------
// 4. PENGOLAHAN DATA PROTOKOL MITIGASI (3 FASE MANAJEMEN BENCANA)
// -------------------------------------------------------------------------
$mitigasiAll = $dbData['mitigasi'] ?? [];
$mitigasiPra = array_values(array_filter($mitigasiAll, fn($m) => ($m['fase'] ?? '') === 'pra'));
$mitigasiTanggap = array_values(array_filter($mitigasiAll, fn($m) => ($m['fase'] ?? '') === 'tanggap'));
$mitigasiPasca = array_values(array_filter($mitigasiAll, fn($m) => ($m['fase'] ?? '') === 'pasca'));

// Data cadangan masing-masing fase mitigasi jika tabel kosong
if (empty($mitigasiPra)) {
    $mitigasiPra = [
        ['judul' => 'Pencegahan', 'deskripsi' => 'Penegakan hukum tegas larangan bakar lahan dan sosialisasi metode pertanian tanpa bakar (zero burning).'],
        ['judul' => 'Deteksi Dini Hotspot', 'deskripsi' => 'Memantau sebaran titik panas satelit BMKG & NASA VIIRS untuk deteksi awal sebelum api membesar.'],
        ['judul' => 'Patroli Rawan', 'deskripsi' => 'Pengerahan tim terpadu (Manggala Agni, TNI/Polri, MPA) di kawasan risiko tinggi pada puncak musim kemarau.'],
        ['judul' => 'Edukasi Masyarakat', 'deskripsi' => 'Penyuluhan tata kelola sekat bakar desa serta edukasi bahaya karhutla bagi kesehatan dan ekonomi warga.']
    ];
}
if (empty($mitigasiTanggap)) {
    $mitigasiTanggap = [
        ['judul' => 'Pemadaman Cepat', 'deskripsi' => 'Operasi pemadaman darat, water bombing helikopter, dan Teknologi Modifikasi Cuaca (TMC / Hujan Buatan).'],
        ['judul' => 'Evakuasi', 'deskripsi' => 'Memindahkan kelompok rentan (balita, ibu hamil, lansia) dari ancaman api langsung dan zona asap berbahaya.'],
        ['judul' => 'Perlindungan Asap', 'deskripsi' => 'Distribusi masker N95, penyesuaian kegiatan sekolah, dan penyediaan safe room berfiltrasi udara bersih.'],
        ['judul' => 'Penanganan Medis', 'deskripsi' => 'Membuka posko darurat ISPA, suplai oksigen, obat-obatan pernapasan, serta distribusi logistik air bersih.']
    ];
}
if (empty($mitigasiPasca)) {
    $mitigasiPasca = [
        ['judul' => 'Rehabilitasi Lahan', 'deskripsi' => 'Menstabilkan struktur tanah bekas terbakar untuk mencegah erosi permukaan dan sedimentasi sungai saat hujan.'],
        ['judul' => 'Reboisasi', 'deskripsi' => 'Penanaman kembali bibit pohon endemik adaptif untuk memulihkan fungsi tutupan kanopi dan keanekaragaman hayati.'],
        ['judul' => 'Pemulihan Gambut', 'deskripsi' => 'Restorasi hidrologis (rewetting) melalui pembangunan sekat kanal (canal blocking) agar gambut kembali basah.'],
        ['judul' => 'Pemantauan & Hukum', 'deskripsi' => 'Verifikasi sisa bara gambut tersembunyi (mopping up) dan proses audit serta penegakan hukum bagi pelaku pembakaran.']
    ];
}

// Daftar ikon FontAwesome untuk tiap kartu fase mitigasi
$praIcons = ['fa-ban', 'fa-satellite-dish', 'fa-binoculars', 'fa-users', 'fa-shield-halved'];
$tanggapIcons = ['fa-fire-extinguisher', 'fa-truck-medical', 'fa-mask-face', 'fa-hand-holding-medical', 'fa-helicopter'];
$pascaIcons = ['fa-mountain', 'fa-seedling', 'fa-droplet', 'fa-shield-halved', 'fa-clipboard-check'];

// -------------------------------------------------------------------------
// 5. PENGOLAHAN DATA STUDI KASUS INTERNASIONAL
// -------------------------------------------------------------------------
$studiList = array_values(array_filter($dbData['studi'] ?? [], 'is_array'));

// Helper studi kasus: ambil nilai pertama yang terisi dari beberapa kemungkinan nama kolom
if (!function_exists('studi_pick')) {
    function studi_pick($row, array $keys) {
        foreach ($keys as $k) {
            if (isset($row[$k]) && trim((string)$row[$k]) !== '') return trim((string)$row[$k]);
        }
        return '';
    }
}
// Validasi path/URL gambar: hanya http(s) atau path relatif aman (tanpa "..")
if (!function_exists('studi_media_url')) {
    function studi_media_url($v) {
        $v = str_replace('\\', '/', trim((string)$v));
        if ($v === '' || strpos($v, '..') !== false) return '';
        if (preg_match('#^https?://#i', $v)) return $v;
        return preg_match('#^[A-Za-z0-9_\-./%]+$#', $v) ? $v : '';
    }
}
// Validasi link berita: hanya http/https (mencegah javascript: dll)
if (!function_exists('studi_link_url')) {
    function studi_link_url($v) {
        $v = trim((string)$v);
        return preg_match('#^https?://#i', $v) ? $v : '';
    }
}
if (!function_exists('studi_domain')) {
    function studi_domain($url) {
        $host = parse_url((string)$url, PHP_URL_HOST);
        return $host ? preg_replace('/^www\./i', '', $host) : '';
    }
}

// Data bawaan jika tabel studi kasus di database masih kosong
if (empty($studiList)) {
    $studiList = [[
        'judul' => 'White Rock, British Columbia, Kanada (2021)',
        'lokasi' => 'British Columbia, Kanada',
        'tahun' => '2021',
        'sumber' => 'CTV News, 13 Juli 2021',
        'deskripsi' => 'Dokumentasi berita pada 13 Juli 2021 memperlihatkan kebakaran hutan dahsyat melanda wilayah British Columbia. Dipicu gelombang panas ekstrem (heat dome), api membakar vegetasi konifer kering hingga memicu Api Tajuk (Crown Fire). Ratusan titik kebakaran terjadi serentak, membuktikan bahwa karhutla mampu merusak ekosistem luas dan menurunkan kualitas udara lintas batas negara secara global.',
        'gambar' => 'uploads/studi/karhutla-bc-2021.jpg',
        'link_berita' => '',
        'tipe_dominan' => 'Api Tajuk (Crown Fire)',
        'faktor_pemicu' => 'Gelombang Panas (Heat Dome)'
    ]];
}
$studiTotal = count($studiList);
$studiPad = max(2, strlen((string)$studiTotal));

// Ilustrasi vektor cadangan: tampil jika kasus belum punya gambar atau file gambarnya gagal dimuat
$studiFallbackSvg = <<<'SVG'
<svg class="w-full h-full" viewBox="0 0 500 310" preserveAspectRatio="xMidYMid slice" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Ilustrasi studi kasus kebakaran hutan">
    <rect width="500" height="310" fill="#14191E"/>
    <path d="M55 210C55 102 142 28 250 28C358 28 445 102 445 210" stroke="#EA580C" stroke-opacity="0.35" stroke-width="2" stroke-dasharray="6 5"/>
    <polygon points="20,235 155,95 285,235" fill="#1E293B"/>
    <polygon points="195,235 345,82 485,235" fill="#273444"/>
    <circle cx="332" cy="112" r="34" fill="#94A3B8" fill-opacity="0.35"/>
    <circle cx="368" cy="86" r="42" fill="#CBD5E1" fill-opacity="0.28"/>
    <circle cx="412" cy="64" r="38" fill="#E2E8F0" fill-opacity="0.2"/>
    <path d="M292 156C292 156 318 178 318 202C318 218 306 230 292 230C278 230 266 218 266 202C266 187 279 170 292 156Z" fill="#EA580C"/>
    <path d="M342 146C342 146 366 168 366 194C366 210 355 222 342 222C329 222 318 210 318 194C318 178 330 160 342 146Z" fill="#FBBF24"/>
    <polygon points="75,180 50,235 100,235" fill="#15803D"/>
    <polygon points="122,168 94,235 150,235" fill="#166534"/>
    <polygon points="172,184 148,235 196,235" fill="#15803D"/>
    <rect x="0" y="235" width="500" height="75" fill="#0F172A"/>
</svg>
SVG;

// -------------------------------------------------------------------------
// 6. PENGOLAHAN DATA TIM KELOMPOK PENGEMBANG
// -------------------------------------------------------------------------
$timList = $dbData['tim'] ?? [];
if (empty($timList)) {
    $timList = [
        ['nama' => 'Yunita Rachmawati', 'nim' => '124250151'],
        ['nama' => 'Sayyira Ulhaq Rukmana', 'nim' => '124250156'],
        ['nama' => 'Amalia Aryanti', 'nim' => '124250158'],
        ['nama' => 'Satria Arya Mahendra', 'nim' => '124250160'],
        ['nama' => 'Farel Zepika Maulana', 'nim' => '124250163']
    ];
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PyroWatch ID | Sistem Informasi Manajemen Bencana Karhutla</title>

    <!-- Import Tailwind CSS via CDN untuk styling utility-first -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Import Font Awesome untuk ikon antarmuka -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Import Google Fonts: Space Grotesk (Judul), Plus Jakarta Sans (Teks), & JetBrains Mono (Label Teknis) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">

    <!-- Konfigurasi Palet Warna & Font Kustom Tailwind -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        charcoal: {
                            950: '#14191E',
                            900: '#1E252B',
                            800: '#273038',
                            700: '#333F49'
                        },
                        surface: {
                            white: '#FFFFFF',
                            slate: '#F8FAFC',
                            muted: '#F1F5F9'
                        },
                        amberbrand: {
                            DEFAULT: '#FBBF24',
                            hover: '#F59E0B',
                            soft: '#FEF3C7',
                            deep: '#B45309'
                        },
                        ember: {
                            DEFAULT: '#EA580C',
                            dark: '#C2410C',
                            soft: '#FFF7ED'
                        },
                        forest: {
                            DEFAULT: '#15803D',
                            dark: '#166534',
                            soft: '#F0FDF4'
                        },
                        ink: {
                            DEFAULT: '#0F172A',
                            body: '#475569',
                            muted: '#64748B'
                        },
                        line: '#E2E8F0'
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        display: ['"Space Grotesk"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace']
                    }
                }
            }
        }
    </script>

    <!-- Styling CSS Tambahan untuk Efek Kaca Navbar, Keyframes Animasi, & Scrollbar -->
    <style>
        .top-nav {
            background-color: rgba(30, 37, 43, 0.94);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        /* Animasi sinar pemindai termal satelit di Hero Section */
        @keyframes scanPulse {
            0%, 100% { opacity: 0.35; transform: translateY(0px); }
            50% { opacity: 0.85; transform: translateY(4px); }
        }
        .animate-scan {
            animation: scanPulse 3.6s ease-in-out infinite;
        }

        /* Animasi melayang lembut untuk ikon satelit */
        @keyframes floatSlow {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-6px); }
        }
        .animate-satellite {
            animation: floatSlow 5s ease-in-out infinite;
        }

        /* Animasi garis progress bar pada carousel penyebab */
        @keyframes progressFill {
            0% { width: 0%; }
            100% { width: 100%; }
        }
        .carousel-progress-active {
            animation: progressFill 4.5s linear forwards;
        }
        .carousel-progress-paused {
            animation-play-state: paused !important;
        }

        /* Animasi transisi muncul saat filter dampak atau tab mitigasi diganti */
        @keyframes panelFade {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .panel-reveal {
            animation: panelFade 0.32s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }

        /* Kustomisasi tampilan scrollbar browser */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #1E252B; }
        ::-webkit-scrollbar-thumb { background: #475569; border-radius: 999px; }
        ::-webkit-scrollbar-thumb:hover { background: #FBBF24; }
    </style>
</head>
<body class="bg-surface-white text-ink font-sans antialiased selection:bg-amberbrand selection:text-charcoal-900 overflow-x-hidden">

    <!-- ===================================================================== -->
    <!-- 1. TOP EMERGENCY BAR (PITA PERINGATAN DARURAT & CALL CENTER 112)      -->
    <!-- ===================================================================== -->
    <div class="bg-charcoal-950 text-slate-200 border-b border-white/10 text-xs py-2 px-4 relative z-50">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2">
            <div class="flex items-center gap-2.5 text-center sm:text-left">
                <span class="relative flex h-2 w-2 shrink-0">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amberbrand opacity-80"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amberbrand"></span>
                </span>
                <p class="leading-snug">
                    <span class="font-mono font-bold uppercase tracking-wider text-amberbrand">SIAGA DARURAT KARHUTLA:</span>
                    <span class="text-slate-300">Pemantauan aktif wilayah rawan Kalimantan & Sumatera.</span>
                </p>
            </div>
            <a href="tel:112" class="inline-flex items-center gap-2 font-mono text-[11px] font-semibold text-white bg-white/10 hover:bg-amberbrand hover:text-charcoal-900 px-3 py-1 rounded-full transition-colors shrink-0">
                <i class="fa-solid fa-phone-volume text-[10px]"></i> Call Center 112
            </a>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- 2. STICKY NAVBAR (NAVIGASI UTAMA & MENU RESPONSIF MOBILE)             -->
    <!-- ===================================================================== -->
    <nav class="sticky top-0 z-50 top-nav border-b border-white/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <!-- Logo Brand PyroWatch ID -->
            <a href="index.php" class="flex items-center gap-3 group">
                <svg class="w-9 h-9 shrink-0 transition-transform duration-300 group-hover:scale-105" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <rect width="40" height="40" rx="10" fill="#FBBF24"/>
                    <path d="M21.8 9.5C21.8 9.5 28 14.8 28 21.2C28 25.8 24.4 29.5 20 29.5C15.6 29.5 12 25.8 12 21.2C12 18.4 13.4 15.8 15.2 14.2C15.1 16.2 16.3 17.6 17.6 17.8C16.9 14.6 18.8 11.4 21.8 9.5Z" fill="#1E252B"/>
                    <circle cx="20" cy="23.2" r="2.8" fill="#EA580C"/>
                </svg>
                <div>
                    <span class="font-display font-bold text-lg tracking-tight text-white block leading-none">
                        PYROWATCH<span class="text-amberbrand">ID</span>
                    </span>
                    <span class="font-mono text-[10px] tracking-[0.16em] text-slate-400 uppercase block mt-1">
                        Karhutla Spatial System
                    </span>
                </div>
            </a>

            <!-- Tautan Navigasi Desktop -->
            <div class="hidden lg:flex items-center gap-7 text-[13px] font-semibold text-slate-300">
                <a href="pyrowatch-v3-siap.html" class="text-white hover:text-amberbrand transition-colors">Dashboard Peta</a>
                <a href="#edukasi" class="hover:text-white transition-colors">Definisi & Tipe</a>
                <a href="#penyebab" class="hover:text-white transition-colors">Penyebab & Dampak</a>
                <a href="#mitigasi" class="hover:text-white transition-colors">Mitigasi</a>
                <a href="#studi-kasus" class="hover:text-white transition-colors">Studi Kasus</a>
                <a href="#tim" class="hover:text-white transition-colors">Tim Kami</a>
            </div>

            <!-- Tombol Aksi Kanan: Login Admin, Buka Peta Live, & Tombol Hamburger Mobile -->
            <div class="flex items-center gap-3">
                <a href="admin.php" class="adm-link hidden sm:inline-flex items-center gap-2 border border-white/25 hover:border-amberbrand text-slate-200 hover:text-amberbrand font-semibold text-xs px-4 py-2.5 rounded-full transition-all">
                    <i class="fa-solid fa-user-shield text-[11px]"></i>
                    <span>Login Admin</span>
                </a>
                <a href="pyrowatch-v3-siap.html" class="hidden sm:inline-flex items-center gap-2 bg-amberbrand hover:bg-amberbrand-hover text-charcoal-900 font-bold text-xs px-4 py-2.5 rounded-full transition-all shadow-sm">
                    <span class="w-2 h-2 rounded-full bg-ember animate-pulse"></span>
                    Buka Peta Live
                </a>
                <button id="mobileMenuBtn" type="button" aria-label="Buka menu navigasi" aria-expanded="false" class="lg:hidden w-10 h-10 rounded-lg border border-white/15 text-white hover:bg-white/10 flex items-center justify-center transition-colors">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
        </div>

        <!-- Drawer Menu Mobile (Tersembunyi secara default, muncul saat mobileMenuBtn diklik) -->
        <div id="mobileMenu" class="hidden lg:hidden bg-charcoal-950 border-t border-white/10 px-5 py-5 space-y-3 text-sm font-semibold">
            <a href="pyrowatch-v3-siap.html" class="flex items-center justify-between py-2 text-amberbrand">
                <span>Dashboard Peta Interaktif</span>
                <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i>
            </a>
            <a href="#edukasi" class="block py-2 text-slate-300 hover:text-white border-t border-white/5">Definisi & Tipe</a>
            <a href="#penyebab" class="block py-2 text-slate-300 hover:text-white border-t border-white/5">Penyebab & Dampak</a>
            <a href="#mitigasi" class="block py-2 text-slate-300 hover:text-white border-t border-white/5">Mitigasi</a>
            <a href="#studi-kasus" class="block py-2 text-slate-300 hover:text-white border-t border-white/5">Studi Kasus</a>
            <a href="#tim" class="block py-2 text-slate-300 hover:text-white border-t border-white/5">Tim Kami</a>
            <a href="admin.php" class="adm-link flex items-center justify-between py-2 text-slate-300 hover:text-white border-t border-white/5">
                <span><i class="fa-solid fa-user-shield mr-2 text-amberbrand"></i><span>Login Admin</span></span>
                <i class="fa-solid fa-arrow-right text-xs"></i>
            </a>
            <div class="pt-3">
                <a href="pyrowatch-v3-siap.html" class="w-full inline-flex items-center justify-center gap-2 bg-amberbrand text-charcoal-900 font-bold text-xs uppercase tracking-wider py-3 rounded-full">
                    <i class="fa-solid fa-map-location-dot"></i> Buka Peta Live
                </a>
            </div>
        </div>
    </nav>

    <!-- ===================================================================== -->
    <!-- 3. ASYMMETRIC SPLIT HERO SECTION (BANNER UTAMA & VISUAL SATELIT)      -->
    <!-- ===================================================================== -->
    <header class="relative bg-charcoal-900 text-white overflow-hidden">
        <!-- Latar Belakang Siluet Kontur Bukit & Pepohonan SVG -->
        <div class="absolute inset-0 pointer-events-none select-none overflow-hidden" aria-hidden="true">
            <svg class="absolute bottom-0 left-0 w-full h-[260px] sm:h-[340px]" viewBox="0 0 1440 340" fill="none" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0 150C90 120 175 135 250 170C330 110 455 105 550 165C640 95 790 95 885 160C980 110 1105 115 1190 170C1275 130 1365 135 1440 165V340H0V150Z" fill="#252E36"/>
                <path d="M0 215C120 185 240 195 360 225C490 180 640 185 770 225C910 175 1070 180 1210 225C1300 200 1375 205 1440 220V340H0V215Z" fill="#181E24"/>
                <g opacity="0.45" fill="#2B3640">
                    <rect x="74" y="210" width="6" height="65" rx="3"/>
                    <circle cx="77" cy="192" r="24"/>
                    <rect x="128" y="228" width="5" height="50" rx="2.5"/>
                    <circle cx="130.5" cy="214" r="18"/>
                    <rect x="1340" y="205" width="6" height="70" rx="3"/>
                    <circle cx="1343" cy="186" r="26"/>
                    <rect x="1290" y="225" width="5" height="52" rx="2.5"/>
                    <circle cx="1292.5" cy="210" r="18"/>
                </g>
            </svg>
        </div>

        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-14 pb-20 lg:py-24">
            <div class="grid lg:grid-cols-12 gap-12 lg:gap-10 items-center">
                <!-- Kolom Kiri (6 Grid): Headline, Deskripsi Sistem, Tombol CTA, & Ringkasan Sensor -->
                <div class="lg:col-span-6 text-left">
                    <div class="inline-flex items-center gap-2 bg-white/[0.07] border border-white/15 rounded-full px-3.5 py-1.5 mb-6">
                        <span class="w-2 h-2 rounded-full bg-amberbrand"></span>
                        <span class="font-mono text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-200">
                            SIMB Karhutla • Dua Lapis Deteksi
                        </span>
                    </div>

                    <h1 class="font-display font-bold text-4xl sm:text-5xl lg:text-[54px] uppercase tracking-tight text-white leading-[1.08] mb-5">
                        PANTAU KARHUTLA PERMUKAAN & <span class="text-amberbrand">BARA GAMBUT.</span>
                    </h1>

                    <p class="text-slate-300 text-base sm:text-lg leading-relaxed max-w-xl mb-8">
                        Sistem Informasi Manajemen Bencana terpadu untuk mendeteksi titik panas satelit, memetakan kerawanan Kesatuan Hidrologis Gambut (KHG), serta mengoordinasikan mitigasi secara cepat.
                    </p>

                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3.5 mb-10">
                        <a href="pyrowatch-v3-siap.html" class="inline-flex items-center justify-center gap-2.5 bg-amberbrand hover:bg-amberbrand-hover text-charcoal-900 font-bold text-sm px-7 py-3.5 rounded-full transition-all shadow-[0_8px_24px_rgba(251,191,36,0.28)]">
                            <i class="fa-solid fa-map-location-dot"></i>
                            Buka Dashboard Peta
                        </a>
                        <a href="#edukasi" class="inline-flex items-center justify-center gap-2.5 bg-transparent hover:bg-white/10 text-white border border-white/25 hover:border-white/50 font-semibold text-sm px-7 py-3.5 rounded-full transition-all">
                            <i class="fa-solid fa-book-open text-amberbrand"></i>
                            Pelajari Mitigasi
                        </a>
                    </div>

                    <div class="grid grid-cols-3 gap-4 pt-6 border-t border-white/10 max-w-lg">
                        <div>
                            <span class="font-mono text-[10px] uppercase tracking-wider text-slate-400 block">Sensor Satelit</span>
                            <span class="font-display font-bold text-sm sm:text-base text-white mt-0.5 block">VIIRS 375m</span>
                        </div>
                        <div>
                            <span class="font-mono text-[10px] uppercase tracking-wider text-slate-400 block">Analisis Lapisan</span>
                            <span class="font-display font-bold text-sm sm:text-base text-amberbrand mt-0.5 block">KHG Gambut</span>
                        </div>
                        <div>
                            <span class="font-mono text-[10px] uppercase tracking-wider text-slate-400 block">Data Atmosfer</span>
                            <span class="font-display font-bold text-sm sm:text-base text-emerald-400 mt-0.5 block">AQI & Angin</span>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan (6 Grid): Kartu Ilustrasi Pemindaian Satelit Termal (Dual-Layer) -->
                <div class="lg:col-span-6 flex justify-center">
                    <div class="w-full max-w-[540px] bg-charcoal-800/75 border border-white/10 rounded-2xl p-4 sm:p-6 shadow-2xl">
                        <div class="flex items-center justify-between border-b border-white/10 pb-3 mb-4 font-mono text-[11px] text-slate-400">
                            <span class="flex items-center gap-2 text-slate-200 font-semibold">
                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                DUAL-LAYER THERMAL SCANNER
                            </span>
                            <span>KHG-01 • SEBANGAU</span>
                        </div>

                        <!-- Grafik Vektor SVG Satelit Memindai Api Permukaan & Bara Gambut -->
                        <svg class="w-full h-auto rounded-xl" viewBox="0 0 520 360" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Ilustrasi pemindaian satelit termal terhadap api permukaan hutan dan bara gambut bawah tanah">
                            <rect width="520" height="360" rx="12" fill="#171D22"/>
                            <g stroke="#FFFFFF" stroke-opacity="0.05" stroke-width="1">
                                <line x1="0" y1="72" x2="520" y2="72"/>
                                <line x1="0" y1="144" x2="520" y2="144"/>
                                <line x1="0" y1="216" x2="520" y2="216"/>
                                <line x1="130" y1="0" x2="130" y2="360"/>
                                <line x1="260" y1="0" x2="260" y2="360"/>
                                <line x1="390" y1="0" x2="390" y2="360"/>
                            </g>
                            <path d="M55 75C165 22 355 22 465 75" stroke="#94A3B8" stroke-opacity="0.25" stroke-width="1.5" stroke-dasharray="5 5"/>
                            <polygon points="260,56 145,215 235,215" fill="url(#scanBeamLeft)" class="animate-scan"/>
                            <polygon points="260,56 290,275 395,275" fill="url(#scanBeamRight)" class="animate-scan"/>
                            <g class="animate-satellite">
                                <rect x="196" y="42" width="44" height="16" rx="3" fill="#38BDF8" fill-opacity="0.25" stroke="#38BDF8" stroke-width="1.5"/>
                                <line x1="210" y1="42" x2="210" y2="58" stroke="#38BDF8" stroke-width="1"/>
                                <line x1="225" y1="42" x2="225" y2="58" stroke="#38BDF8" stroke-width="1"/>
                                <rect x="280" y="42" width="44" height="16" rx="3" fill="#38BDF8" fill-opacity="0.25" stroke="#38BDF8" stroke-width="1.5"/>
                                <line x1="295" y1="42" x2="295" y2="58" stroke="#38BDF8" stroke-width="1"/>
                                <line x1="310" y1="42" x2="310" y2="58" stroke="#38BDF8" stroke-width="1"/>
                                <rect x="246" y="36" width="28" height="28" rx="6" fill="#F8FAFC" stroke="#1E252B" stroke-width="2"/>
                                <circle cx="260" cy="50" r="5" fill="#FBBF24"/>
                                <path d="M252 64H268L264 71H256L252 64Z" fill="#FBBF24"/>
                            </g>
                            <path d="M0 195L45 170L95 190L155 162L215 188L285 165L350 190L425 160L480 185L520 172V225H0V195Z" fill="#22302B"/>
                            <g>
                                <rect x="52" y="185" width="6" height="35" fill="#475569"/>
                                <polygon points="55,140 32,190 78,190" fill="#15803D"/>
                                <polygon points="55,124 37,164 73,164" fill="#22C55E"/>
                                <rect x="102" y="180" width="6" height="40" fill="#475569"/>
                                <polygon points="105,130 78,186 132,186" fill="#166534"/>
                                <polygon points="105,112 84,158 126,158" fill="#16A34A"/>
                            </g>
                            <g>
                                <path d="M188 168C178 150 192 132 210 124C215 106 238 98 254 110C270 104 286 118 280 134" stroke="#94A3B8" stroke-opacity="0.45" stroke-width="6" stroke-linecap="round"/>
                                <path d="M190 172C190 172 208 188 208 205C208 216 199 224 188 224C177 224 168 216 168 205C168 196 173 188 178 184C178 190 182 194 185 194C183 185 186 177 190 172Z" fill="#EA580C"/>
                                <path d="M189 191C189 191 198 199 198 208C198 214 193 219 188 219C183 219 178 214 178 208C178 202 184 196 189 191Z" fill="#FBBF24"/>
                                <rect x="160" y="166" width="56" height="60" rx="6" stroke="#EA580C" stroke-width="1.5" stroke-dasharray="4 3"/>
                            </g>
                            <g>
                                <rect x="335" y="182" width="6" height="38" fill="#475569"/>
                                <polygon points="338,134 313,188 363,188" fill="#15803D"/>
                                <rect x="405" y="178" width="6" height="42" fill="#475569"/>
                                <polygon points="408,128 380,185 436,185" fill="#166534"/>
                                <rect x="462" y="186" width="6" height="34" fill="#475569"/>
                                <polygon points="465,144 444,190 486,190" fill="#15803D"/>
                            </g>
                            <rect x="0" y="220" width="520" height="30" fill="#3B2F2A"/>
                            <line x1="0" y1="220" x2="520" y2="220" stroke="#78716C" stroke-width="2"/>
                            <rect x="0" y="250" width="520" height="110" fill="#261C18"/>
                            <g stroke="#44332C" stroke-width="2" stroke-linecap="round">
                                <line x1="28" y1="274" x2="88" y2="274"/>
                                <line x1="120" y1="305" x2="195" y2="305"/>
                                <line x1="42" y1="332" x2="110" y2="332"/>
                                <line x1="420" y1="282" x2="490" y2="282"/>
                                <line x1="405" y1="325" x2="475" y2="325"/>
                            </g>
                            <g>
                                <ellipse cx="342" cy="296" rx="54" ry="26" fill="#7C2D12" fill-opacity="0.65"/>
                                <ellipse cx="342" cy="296" rx="34" ry="15" fill="#EA580C"/>
                                <ellipse cx="342" cy="296" rx="16" ry="7" fill="#FBBF24"/>
                                <path d="M326 268C322 252 330 238 326 222" stroke="#CBD5E1" stroke-opacity="0.45" stroke-width="2" stroke-dasharray="3 3"/>
                                <path d="M356 268C360 250 352 236 356 220" stroke="#CBD5E1" stroke-opacity="0.45" stroke-width="2" stroke-dasharray="3 3"/>
                                <rect x="282" y="264" width="120" height="64" rx="8" stroke="#FBBF24" stroke-width="1.5" stroke-dasharray="4 3"/>
                            </g>
                            <g font-family="JetBrains Mono, monospace" font-size="10" font-weight="600">
                                <rect x="24" y="226" width="152" height="20" rx="4" fill="#1E252B"/>
                                <circle cx="35" cy="236" r="3.5" fill="#EA580C"/>
                                <text x="45" y="239" fill="#F8FAFC">API PERMUKAAN (FRP > 8MW)</text>

                                <rect x="256" y="334" width="174" height="20" rx="4" fill="#1E252B"/>
                                <circle cx="267" cy="344" r="3.5" fill="#FBBF24"/>
                                <text x="277" y="347" fill="#FBBF24">BARA GAMBUT BAWAH TANAH</text>
                            </g>
                            <defs>
                                <linearGradient id="scanBeamLeft" x1="260" y1="56" x2="190" y2="215" gradientUnits="userSpaceOnUse">
                                    <stop stop-color="#EA580C" stop-opacity="0.35"/>
                                    <stop offset="1" stop-color="#EA580C" stop-opacity="0.02"/>
                                </linearGradient>
                                <linearGradient id="scanBeamRight" x1="260" y1="56" x2="342" y2="275" gradientUnits="userSpaceOnUse">
                                    <stop stop-color="#FBBF24" stop-opacity="0.32"/>
                                    <stop offset="1" stop-color="#FBBF24" stop-opacity="0.02"/>
                                </linearGradient>
                            </defs>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- ===================================================================== -->
    <!-- 4. SECTION EDUKASI & STRATIFIKASI (#edukasi)                          -->
    <!-- ===================================================================== -->
    <section id="edukasi" class="py-20 lg:py-28 bg-surface-white border-b border-line">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mb-14">
                <span class="font-mono text-xs font-bold uppercase tracking-[0.2em] text-forest block mb-2.5">
                    01 / Materi Akademik & Klasifikasi
                </span>
                <h2 class="font-display font-bold text-3xl sm:text-4xl lg:text-[42px] text-ink tracking-tight leading-tight">
                    Definisi & Stratifikasi Karhutla
                </h2>
                <p class="text-ink-body text-base mt-3 leading-relaxed">
                    Memahami perbedaan karakteristik ruang terbakar serta lapisan vertikal api sangat menentukan strategi pemadaman di lapangan.
                </p>
            </div>

            <!-- 01. KARTU PENGERTIAN GENERAL KARHUTLA (DINAMIS DARI DATABASE) -->
            <div class="bg-surface-slate border border-line rounded-2xl p-7 sm:p-10 mb-14 relative overflow-hidden">
                <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-forest"></div>
                <div class="grid lg:grid-cols-12 gap-6 items-center">
                    <div class="lg:col-span-9">
                        <span class="font-mono text-[11px] font-bold uppercase tracking-[0.18em] text-forest bg-forest-soft border border-forest/20 px-3 py-1 rounded-full inline-block mb-3">
                            01 • Konsep Dasar Bencana
                        </span>
                        <h3 class="font-display font-bold text-2xl sm:text-3xl text-ink mb-3">
                            Pengertian General Karhutla
                        </h3>
                        <p class="text-ink-body text-base sm:text-lg leading-relaxed">
                            <!-- Highlight otomatis kata "tidak terkendali" dengan warna oranye-merah -->
                            <?= str_replace('tidak terkendali', '<strong class="text-ember font-bold">tidak terkendali</strong>', h($pengertianUmum)) ?>
                        </p>
                    </div>
                    <div class="lg:col-span-3 flex lg:justify-end">
                        <div class="bg-white border border-line rounded-xl p-4 w-full sm:w-auto">
                            <span class="font-mono text-[10px] uppercase tracking-wider text-ink-muted block">Parameter Utama</span>
                            <ul class="mt-2 space-y-1.5 text-xs font-semibold text-ink">
                                <li class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-forest"></i> Penjalaran Tak Terkendali</li>
                                <li class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-ember"></i> Kerugian Multi-Sektor</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 02. PERBANDINGAN KEBAKARAN HUTAN VS KEBAKARAN LAHAN (LOOPING PHP) -->
            <div class="mb-20">
                <div class="flex flex-col sm:flex-row sm:items-end justify-between border-b border-line pb-4 mb-8 gap-2">
                    <div>
                        <span class="font-mono text-xs font-bold uppercase tracking-[0.18em] text-ink-muted block mb-1">
                            02 • Diferensiasi Regional
                        </span>
                        <h3 class="font-display font-bold text-2xl sm:text-3xl text-ink">
                            Kebakaran Hutan vs. Kebakaran Lahan
                        </h3>
                    </div>
                    <span class="font-mono text-xs text-ink-muted">Perbandingan Status Kawasan</span>
                </div>

                <div class="grid md:grid-cols-2 gap-8">
                    <?php foreach ($kawasanList as $kIdx => $kaw): $isFirst = ($kIdx % 2 === 0); ?>
                    <article class="bg-white border border-line <?= $isFirst ? 'hover:border-forest/50' : 'hover:border-ember/50' ?> rounded-2xl p-6 sm:p-8 transition-all duration-300 shadow-[0_4px_20px_rgba(15,23,42,0.03)] flex flex-col justify-between">
                        <div>
                            <div class="bg-surface-slate border border-line rounded-xl p-4 mb-6">
                                <?php if ($isFirst): ?>
                                <!-- Ilustrasi SVG Kawasan Hutan -->
                                <svg class="w-full h-36" viewBox="0 0 360 130" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <rect width="360" height="130" rx="8" fill="#F0FDF4"/>
                                    <rect x="0" y="104" width="360" height="26" rx="4" fill="#166534"/>
                                    <rect x="54" y="65" width="7" height="39" fill="#78350F"/>
                                    <polygon points="57.5,18 28,74 87,74" fill="#15803D"/>
                                    <polygon points="57.5,8 35,48 80,48" fill="#22C55E"/>
                                    <rect x="122" y="58" width="8" height="46" fill="#78350F"/>
                                    <polygon points="126,12 92,70 160,70" fill="#166534"/>
                                    <rect x="196" y="66" width="7" height="38" fill="#78350F"/>
                                    <polygon points="199.5,22 172,74 227,74" fill="#15803D"/>
                                    <path d="M276 66C276 66 292 78 292 92C292 100 285 106 276 106C267 106 260 100 260 92C260 84 268 74 276 66Z" fill="#EA580C"/>
                                    <path d="M276 81C276 81 284 87 284 94C284 99 280 102 276 102C272 102 268 99 268 94C268 89 272 85 276 81Z" fill="#FBBF24"/>
                                    <rect x="242" y="12" width="106" height="22" rx="11" fill="#1E252B"/>
                                    <text x="295" y="26" text-anchor="middle" fill="#FFFFFF" font-family="JetBrains Mono, monospace" font-size="9" font-weight="700">KAWASAN HUTAN</text>
                                </svg>
                                <?php else: ?>
                                <!-- Ilustrasi SVG Non-Kawasan Hutan / Lahan -->
                                <svg class="w-full h-36" viewBox="0 0 360 130" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <rect width="360" height="130" rx="8" fill="#FFF7ED"/>
                                    <rect x="0" y="102" width="360" height="28" rx="4" fill="#78350F"/>
                                    <g stroke="#B45309" stroke-width="2" stroke-linecap="round">
                                        <path d="M45 102V78M45 84L33 74M45 84L57 74"/>
                                        <path d="M105 102V74M105 82L91 70M105 82L119 70"/>
                                        <path d="M165 102V80M165 86L153 76M165 86L177 76"/>
                                    </g>
                                    <path d="M225 70C225 70 242 82 242 94C242 101 235 106 225 106C215 106 208 101 208 94C208 86 216 77 225 70Z" fill="#EA580C"/>
                                    <path d="M280 76C280 76 293 85 293 95C293 101 287 105 280 105C273 105 267 101 267 95C267 88 273 81 280 76Z" fill="#F59E0B"/>
                                    <rect x="220" y="12" width="128" height="22" rx="11" fill="#1E252B"/>
                                    <text x="284" y="26" text-anchor="middle" fill="#FBBF24" font-family="JetBrains Mono, monospace" font-size="9" font-weight="700">NON-KAWASAN HUTAN</text>
                                </svg>
                                <?php endif; ?>
                            </div>

                            <div class="flex items-center gap-2.5 mb-3">
                                <span class="w-8 h-8 rounded-lg <?= $isFirst ? 'bg-forest-soft text-forest' : 'bg-ember-soft text-ember' ?> flex items-center justify-center text-sm">
                                    <i class="fa-solid <?= $isFirst ? 'fa-tree' : 'fa-wheat-awn' ?>"></i>
                                </span>
                                <h4 class="font-display font-bold text-xl text-ink"><?= h($kaw['nama']) ?></h4>
                            </div>

                            <p class="text-ink-body text-sm sm:text-[15px] leading-relaxed mb-5">
                                <?= h($kaw['deskripsi']) ?>
                            </p>
                        </div>

                        <div class="pt-4 border-t border-line flex items-center justify-between font-mono text-[11px] text-ink-muted">
                            <span><?= $isFirst ? 'VEGETASI: TEGAKAN POHON ALAMI' : 'AREA: KEBUN, SEMAK, RAWA' ?></span>
                            <span class="<?= $isFirst ? 'text-forest' : 'text-ember' ?> font-bold"><?= $isFirst ? 'EKOSISTEM PRIMER' : 'PEMBUKAAN LAHAN' ?></span>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 03. STRATA VERTIKAL PENJALARAN API (DIAGRAM SVG INTERAKTIF + 3 KARTU) -->
            <div>
                <div class="flex flex-col sm:flex-row sm:items-end justify-between border-b border-line pb-4 mb-8 gap-2">
                    <div>
                        <span class="font-mono text-xs font-bold uppercase tracking-[0.18em] text-ink-muted block mb-1">
                            03 • Vertikalisasi Tipe Kebakaran
                        </span>
                        <h3 class="font-display font-bold text-2xl sm:text-3xl text-ink">
                            Strata Vertikal Penjalaran Api
                        </h3>
                    </div>
                    <span class="font-mono text-xs text-ember font-semibold">
                        Klik lapisan atau kartu untuk inspeksi strata
                    </span>
                </div>

                <!-- Box Inspeksi Interaktif (Diagram SVG Kiri + Panel Detail Kanan) -->
                <div class="bg-charcoal-900 rounded-2xl p-5 sm:p-7 mb-8 text-white border border-charcoal-700">
                    <div class="grid lg:grid-cols-12 gap-6 items-center">
                        <div class="lg:col-span-7">
                            <!-- Setiap lapisan memanggil fungsi JS selectStrata() saat diklik -->
                            <svg id="strataSvg" class="w-full h-auto rounded-xl bg-charcoal-950 border border-white/10" viewBox="0 0 540 270" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Diagram penampang vertikal tiga tipe kebakaran hutan dan lahan">
                                <g id="svg-band-tajuk" class="cursor-pointer transition-opacity duration-300" onclick="selectStrata('tajuk')">
                                    <rect x="8" y="10" width="524" height="82" rx="8" fill="#EF4444" fill-opacity="0.14" stroke="#EF4444" stroke-width="1.5"/>
                                    <text x="22" y="30" fill="#FCA5A5" font-family="JetBrains Mono, monospace" font-size="10" font-weight="700">01. <?= strtoupper(h($strataDefaults['tajuk']['title'])) ?></text>
                                    <text x="22" y="45" fill="#CBD5E1" font-family="Plus Jakarta Sans, sans-serif" font-size="10">Menjalar antar-kanopi pohon tinggi</text>
                                </g>
                                <g id="svg-band-permukaan" class="cursor-pointer transition-opacity duration-300 opacity-55" onclick="selectStrata('permukaan')">
                                    <rect x="8" y="98" width="524" height="74" rx="8" fill="#F97316" fill-opacity="0.12" stroke="#F97316" stroke-width="1.2"/>
                                    <text x="22" y="118" fill="#FDBA74" font-family="JetBrains Mono, monospace" font-size="10" font-weight="700">02. <?= strtoupper(h($strataDefaults['permukaan']['title'])) ?></text>
                                    <text x="22" y="133" fill="#CBD5E1" font-family="Plus Jakarta Sans, sans-serif" font-size="10">Serasah kering & semak belukar</text>
                                </g>
                                <g id="svg-band-bawah" class="cursor-pointer transition-opacity duration-300 opacity-55" onclick="selectStrata('bawah')">
                                    <rect x="8" y="178" width="524" height="82" rx="8" fill="#D97706" fill-opacity="0.14" stroke="#FBBF24" stroke-width="1.2"/>
                                    <text x="22" y="198" fill="#FDE68A" font-family="JetBrains Mono, monospace" font-size="10" font-weight="700">03. <?= strtoupper(h($strataDefaults['bawah']['title'])) ?></text>
                                    <text x="22" y="213" fill="#CBD5E1" font-family="Plus Jakarta Sans, sans-serif" font-size="10">Bara merambat lambat di lapisan gambut</text>
                                </g>
                                <rect x="348" y="54" width="12" height="122" fill="#78716C"/>
                                <polygon points="354,18 304,88 404,88" fill="#15803D"/>
                                <polygon points="444,26 402,88 486,88" fill="#166534"/>
                                <rect x="439" y="64" width="10" height="112" fill="#78716C"/>
                                <path d="M398 32C398 32 414 44 414 58C414 66 407 72 398 72C389 72 382 66 382 58C382 49 390 40 398 32Z" fill="#EF4444"/>
                                <path d="M398 46C398 46 406 53 406 60C406 64 402 68 398 68C394 68 390 64 390 60C390 55 394 50 398 46Z" fill="#FBBF24"/>
                                <path d="M312 138C312 138 324 148 324 159C324 166 318 171 312 171C306 171 300 166 300 159C300 152 306 144 312 138Z" fill="#F97316"/>
                                <path d="M472 142C472 142 482 150 482 160C482 166 477 171 472 171C467 171 462 166 462 160C462 154 467 147 472 142Z" fill="#FBBF24"/>
                                <ellipse cx="395" cy="222" rx="56" ry="18" fill="#B45309"/>
                                <ellipse cx="395" cy="222" rx="30" ry="9" fill="#FBBF24"/>
                            </svg>
                        </div>

                        <!-- Panel Detail Strata yang isinya berganti dinamis lewat JavaScript -->
                        <div class="lg:col-span-5 bg-charcoal-800 border border-white/10 rounded-xl p-6" id="strataInspector">
                            <span id="strataBadge" class="<?= $strataDefaults['tajuk']['badgeClass'] ?>">
                                <?= h($strataDefaults['tajuk']['badge']) ?>
                            </span>
                            <h4 id="strataTitle" class="font-display font-bold text-2xl text-white mb-2.5">
                                <?= h($strataDefaults['tajuk']['title']) ?>
                            </h4>
                            <p id="strataDesc" class="text-slate-300 text-sm leading-relaxed mb-5">
                                <?= h($strataDefaults['tajuk']['desc']) ?>
                            </p>
                            <div class="space-y-2.5 pt-4 border-t border-white/10 font-mono text-xs">
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Kecepatan Rambat:</span>
                                    <span id="strataSpeed" class="text-white font-semibold"><?= h($strataDefaults['tajuk']['speed']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Metode Penanganan:</span>
                                    <span id="strataAction" class="text-amberbrand font-semibold"><?= h($strataDefaults['tajuk']['action']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3 Kartu Pilihan Strata di Bawah Diagram -->
                <div class="grid lg:grid-cols-3 gap-6">
                    <?php foreach ($strataKeys as $kIdx => $sKey): $st = $strataDefaults[$sKey]; ?>
                    <div id="strata-card-<?= $sKey ?>" onclick="selectStrata('<?= $sKey ?>')" class="cursor-pointer bg-white <?= $kIdx === 0 ? 'border-2 border-red-500' : 'border border-line hover:border-slate-400' ?> rounded-2xl p-7 transition-all shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <span class="font-mono text-[11px] font-bold uppercase tracking-wider <?= $st['tagClass'] ?> px-3 py-1 rounded-full"><?= h($st['tag']) ?></span>
                            <i class="fa-solid <?= $st['icon'] ?> <?= $st['iconColor'] ?> text-lg"></i>
                        </div>
                        <h4 class="font-display font-bold text-xl text-ink mb-2.5"><?= h($st['title']) ?></h4>
                        <p class="text-ink-body text-sm leading-relaxed"><?= h($st['desc']) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- ===================================================================== -->
    <!-- 5. SECTION PENYEBAB UTAMA (#penyebab — CAROUSEL OTOMATIS DARI DB)     -->
    <!-- ===================================================================== -->
    <section id="penyebab" class="py-20 lg:py-28 bg-charcoal-900 text-white relative overflow-hidden">
        <div class="absolute inset-0 pointer-events-none select-none" aria-hidden="true">
            <svg class="absolute bottom-0 left-0 w-full h-44" viewBox="0 0 1440 180" fill="none" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0 95C240 45 480 65 720 105C960 145 1200 75 1440 95V180H0V95Z" fill="#181E24"/>
            </svg>
        </div>

        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12">
                <div>
                    <span class="font-mono text-xs font-bold uppercase tracking-[0.2em] text-amberbrand block mb-2.5">
                        02 / Analisis Faktor Pemicu
                    </span>
                    <h2 class="font-display font-bold text-3xl sm:text-4xl lg:text-[42px] uppercase tracking-tight text-white">
                        <?= count($penyebabList) ?> Penyebab Utama Karhutla
                    </h2>
                </div>
                <p class="text-slate-300 text-sm sm:text-base max-w-md leading-relaxed">
                    Kombinasi antara faktor antropogenik (aktivitas manusia) dan dinamika iklim ekstrem memicu terjadinya kebakaran hutan dan lahan.
                </p>
            </div>

            <!-- Wadah Utama Carousel Penyebab -->
            <div id="causeCarousel" class="bg-charcoal-800 border border-white/10 rounded-3xl overflow-hidden shadow-2xl" tabindex="0" aria-roledescription="carousel" aria-label="Penyebab Utama Karhutla">
                <!-- Garis indikator waktu jalan otomatis (progress bar) -->
                <div class="w-full h-1 bg-white/10 overflow-hidden">
                    <div id="carouselProgress" class="h-full bg-amberbrand carousel-progress-active"></div>
                </div>

                <!-- Track Horizontal yang digeser dengan CSS translate3d -->
                <div class="overflow-hidden relative">
                    <div id="causeTrack" class="flex transition-transform duration-700 ease-[cubic-bezier(0.22,1,0.36,1)] will-change-transform">
                        <?php
                        $totalPenyebab = count($penyebabList);
                        $padLen = max(2, strlen((string)$totalPenyebab));
                        foreach ($penyebabList as $pIdx => $pItem):
                            $meta = $penyebabMeta[$pIdx] ?? [
                                'cat' => 'Faktor Eksternal',
                                'catColor' => 'text-amberbrand',
                                'm1Label' => 'KATEGORI PEMICU',
                                'm1Val' => 'Risiko Lapangan',
                                'm2Label' => 'TINGKAT ESKALASI',
                                'm2Val' => 'Perlu Mitigasi',
                                'm2Color' => 'text-amberbrand'
                            ];
                            // Modulo 6 untuk memilih 1 dari 6 ilustrasi SVG secara berulang jika data > 6
                            $svgType = $pIdx % 6;
                            $numStr = str_pad((string)($pIdx + 1), $padLen, '0', STR_PAD_LEFT);
                            $totStr = str_pad((string)$totalPenyebab, $padLen, '0', STR_PAD_LEFT);
                        ?>
                        <div class="cause-slide w-full flex-shrink-0 p-7 sm:p-10 lg:p-14">
                            <div class="grid lg:grid-cols-12 gap-8 lg:gap-12 items-center">
                                <div class="lg:col-span-6">
                                    <div class="flex items-center gap-3 mb-5">
                                        <span class="font-mono text-xs font-bold bg-amberbrand text-charcoal-900 px-3 py-1 rounded-full">FAKTOR <?= $numStr ?> / <?= $totStr ?></span>
                                        <span class="font-mono text-xs uppercase tracking-wider <?= $meta['catColor'] ?> font-semibold"><?= h($meta['cat']) ?></span>
                                    </div>
                                    <h3 class="font-display font-bold text-2xl sm:text-4xl text-white uppercase tracking-tight leading-tight mb-4">
                                        <?= h($pItem['judul']) ?>
                                    </h3>
                                    <p class="text-slate-300 text-base leading-relaxed mb-7">
                                        <?= h($pItem['deskripsi']) ?>
                                    </p>
                                    <div class="grid grid-cols-2 gap-4 pt-5 border-t border-white/10 font-mono text-xs">
                                        <div>
                                            <span class="text-slate-400 block"><?= h($meta['m1Label']) ?></span>
                                            <span class="text-white font-semibold mt-1 block"><?= h($meta['m1Val']) ?></span>
                                        </div>
                                        <div>
                                            <span class="text-slate-400 block"><?= h($meta['m2Label']) ?></span>
                                            <span class="<?= $meta['m2Color'] ?> font-semibold mt-1 block"><?= h($meta['m2Val']) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="lg:col-span-6">
                                    <!-- Render ilustrasi SVG sesuai urutan faktor -->
                                    <?php if ($svgType === 0): ?>
                                    <svg class="w-full h-auto rounded-2xl bg-charcoal-950 border border-white/10" viewBox="0 0 480 300" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="<?= h($pItem['judul']) ?>">
                                        <rect width="480" height="300" fill="#151B20"/>
                                        <path d="M0 220C140 205 320 208 480 225V300H0V220Z" fill="#29211D"/>
                                        <rect x="64" y="198" width="16" height="24" rx="2" fill="#78350F"/>
                                        <rect x="114" y="202" width="14" height="20" rx="2" fill="#78350F"/>
                                        <path d="M195 142C195 142 224 166 224 192C224 209 211 222 195 222C179 222 166 209 166 192C166 177 180 158 195 142Z" fill="#EA580C"/>
                                        <path d="M195 168C195 168 210 181 210 196C210 206 203 213 195 213C187 213 180 206 180 196C180 186 188 176 195 168Z" fill="#FBBF24"/>
                                        <path d="M256 164C256 164 276 181 276 200C276 212 267 221 256 221C245 221 236 212 236 200C236 189 246 175 256 164Z" fill="#F97316"/>
                                        <circle cx="215" cy="108" r="24" fill="#64748B" fill-opacity="0.35"/>
                                        <circle cx="242" cy="92" r="30" fill="#94A3B8" fill-opacity="0.3"/>
                                        <circle cx="276" cy="78" r="26" fill="#CBD5E1" fill-opacity="0.22"/>
                                        <rect x="348" y="156" width="8" height="66" fill="#57534E"/>
                                        <polygon points="352,96 318,166 386,166" fill="#15803D"/>
                                        <rect x="408" y="148" width="8" height="74" fill="#57534E"/>
                                        <polygon points="412,84 374,160 450,160" fill="#166534"/>
                                    </svg>
                                    <?php elseif ($svgType === 1): ?>
                                    <svg class="w-full h-auto rounded-2xl bg-charcoal-950 border border-white/10" viewBox="0 0 480 300" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="<?= h($pItem['judul']) ?>">
                                        <rect width="480" height="300" fill="#151B20"/>
                                        <path d="M0 225H480V300H0V225Z" fill="#29211D"/>
                                        <path d="M90 225L140 208M130 225L175 212M290 225L345 206M335 225L390 214" stroke="#92400E" stroke-width="4" stroke-linecap="round"/>
                                        <g transform="rotate(-14 210 198)">
                                            <rect x="150" y="192" width="32" height="12" rx="2" fill="#D97706"/>
                                            <rect x="182" y="192" width="68" height="12" rx="2" fill="#F8FAFC"/>
                                            <rect x="250" y="192" width="10" height="12" rx="2" fill="#EA580C"/>
                                        </g>
                                        <circle cx="272" cy="176" r="4" fill="#FBBF24"/>
                                        <circle cx="288" cy="154" r="3" fill="#EA580C"/>
                                        <circle cx="264" cy="142" r="2.5" fill="#FBBF24"/>
                                        <path d="M294 176C294 176 312 190 312 206C312 216 304 224 294 224C284 224 276 216 276 206C276 196 285 185 294 176Z" fill="#EA580C"/>
                                        <path d="M294 192C294 192 303 200 303 209C303 215 299 219 294 219C289 219 285 215 285 209C285 203 290 197 294 192Z" fill="#FBBF24"/>
                                        <circle cx="262" cy="186" r="68" stroke="#FBBF24" stroke-opacity="0.35" stroke-width="1.5" stroke-dasharray="5 5"/>
                                    </svg>
                                    <?php elseif ($svgType === 2): ?>
                                    <svg class="w-full h-auto rounded-2xl bg-charcoal-950 border border-white/10" viewBox="0 0 480 300" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="<?= h($pItem['judul']) ?>">
                                        <rect width="480" height="300" fill="#151B20"/>
                                        <circle cx="340" cy="84" r="46" fill="#FBBF24" fill-opacity="0.18"/>
                                        <circle cx="340" cy="84" r="30" fill="#FBBF24"/>
                                        <path d="M120 110C135 98 150 122 165 110M190 95C205 83 220 107 235 95M145 145C160 133 175 157 190 145" stroke="#F59E0B" stroke-width="2.5" stroke-linecap="round"/>
                                        <rect x="0" y="218" width="480" height="82" fill="#3B271E"/>
                                        <path d="M70 218L92 248L76 280M210 218L228 255L245 290M360 218L344 252L364 285" stroke="#1E1410" stroke-width="2.5" stroke-linecap="round"/>
                                        <path d="M135 218V148M135 174L108 152M135 164L162 142M245 218V160M245 182L222 164M245 175L268 158" stroke="#A8A29E" stroke-width="4" stroke-linecap="round"/>
                                        <rect x="36" y="34" width="118" height="28" rx="14" fill="#EA580C"/>
                                        <text x="95" y="52" text-anchor="middle" fill="#FFFFFF" font-family="JetBrains Mono, monospace" font-size="11" font-weight="700">SUHU > 36°C</text>
                                    </svg>
                                    <?php elseif ($svgType === 3): ?>
                                    <svg class="w-full h-auto rounded-2xl bg-charcoal-950 border border-white/10" viewBox="0 0 480 300" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="<?= h($pItem['judul']) ?>">
                                        <rect width="480" height="300" fill="#151B20"/>
                                        <rect x="0" y="226" width="480" height="74" fill="#1E293B"/>
                                        <path d="M36 92H260C282 92 292 74 278 62" stroke="#38BDF8" stroke-width="3" stroke-linecap="round"/>
                                        <path d="M52 128H345C368 128 378 110 364 98" stroke="#7DD3FC" stroke-width="2.5" stroke-linecap="round"/>
                                        <path d="M28 164H230" stroke="#38BDF8" stroke-opacity="0.6" stroke-width="2" stroke-linecap="round"/>
                                        <path d="M110 144C110 144 138 168 138 196C138 213 125 226 110 226C95 226 82 213 82 196C82 180 96 160 110 144Z" fill="#EA580C"/>
                                        <path d="M110 172C110 172 124 185 124 200C124 210 118 217 110 217C102 217 96 210 96 200C96 190 103 180 110 172Z" fill="#FBBF24"/>
                                        <circle cx="195" cy="148" r="5" fill="#FBBF24"/>
                                        <circle cx="248" cy="136" r="4.5" fill="#EA580C"/>
                                        <circle cx="304" cy="154" r="5" fill="#FBBF24"/>
                                        <path d="M368 168C368 168 388 185 388 205C388 217 379 226 368 226C357 226 348 217 348 205C348 193 358 179 368 168Z" fill="#EA580C"/>
                                        <path d="M368 186C368 186 378 195 378 206C378 213 373 218 368 218C363 218 358 213 358 206C358 199 363 192 368 186Z" fill="#FBBF24"/>
                                    </svg>
                                    <?php elseif ($svgType === 4): ?>
                                    <svg class="w-full h-auto rounded-2xl bg-charcoal-950 border border-white/10" viewBox="0 0 480 300" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="<?= h($pItem['judul']) ?>">
                                        <rect width="480" height="300" fill="#151B20"/>
                                        <path d="M0 130H165L185 162H235L255 130H480V300H0V130Z" fill="#3B2922"/>
                                        <rect x="185" y="150" width="50" height="12" fill="#38BDF8" fill-opacity="0.55"/>
                                        <rect x="0" y="168" width="480" height="132" fill="#241814"/>
                                        <line x1="0" y1="258" x2="480" y2="258" stroke="#38BDF8" stroke-width="1.5" stroke-dasharray="6 4"/>
                                        <text x="18" y="252" fill="#7DD3FC" font-family="JetBrains Mono, monospace" font-size="9">MUKA AIR TANAH TURUN</text>
                                        <ellipse cx="348" cy="212" rx="64" ry="26" fill="#9A3412"/>
                                        <ellipse cx="348" cy="212" rx="38" ry="14" fill="#EA580C"/>
                                        <ellipse cx="348" cy="212" rx="18" ry="6" fill="#FBBF24"/>
                                        <path d="M325 184C320 156 332 132 326 102M368 184C374 154 362 128 368 96" stroke="#CBD5E1" stroke-opacity="0.55" stroke-width="3" stroke-linecap="round" stroke-dasharray="4 4"/>
                                    </svg>
                                    <?php else: ?>
                                    <svg class="w-full h-auto rounded-2xl bg-charcoal-950 border border-white/10" viewBox="0 0 480 300" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="<?= h($pItem['judul']) ?>">
                                        <rect width="480" height="300" fill="#151B20"/>
                                        <path d="M145 78C145 58 162 42 182 42C190 28 206 20 224 20C248 20 268 37 272 60C288 62 300 75 300 92C300 109 286 122 268 122H165C148 122 135 109 135 92C135 84 139 78 145 78Z" fill="#334155"/>
                                        <polygon points="228,118 202,166 226,166 212,208 258,154 232,154 248,118" fill="#FBBF24"/>
                                        <rect x="0" y="232" width="480" height="68" fill="#1E293B"/>
                                        <rect x="116" y="182" width="7" height="50" fill="#475569"/>
                                        <polygon points="119.5,134 92,188 147,188" fill="#15803D"/>
                                        <rect x="226" y="186" width="8" height="46" fill="#78350F"/>
                                        <polygon points="230,142 200,192 260,192" fill="#166534"/>
                                        <circle cx="230" cy="186" r="18" fill="#EA580C" fill-opacity="0.85"/>
                                        <circle cx="230" cy="186" r="9" fill="#FBBF24"/>
                                        <rect x="342" y="178" width="7" height="54" fill="#475569"/>
                                        <polygon points="345.5,126 316,184 375,184" fill="#15803D"/>
                                    </svg>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Tombol Navigasi Bawah Carousel (Prev, Dots Indikator, Next) -->
                <div class="bg-charcoal-950/80 border-t border-white/10 px-6 sm:px-10 py-4 flex items-center justify-between gap-4">
                    <button id="prevSlideBtn" type="button" aria-label="Slide penyebab sebelumnya" class="w-10 h-10 rounded-full border border-white/15 hover:border-amberbrand hover:bg-amberbrand hover:text-charcoal-900 text-white flex items-center justify-center transition-all">
                        <i class="fa-solid fa-arrow-left text-xs"></i>
                    </button>

                    <div id="carouselDots" class="flex items-center justify-center gap-2.5 flex-wrap" role="tablist" aria-label="Navigasi slide penyebab"></div>

                    <button id="nextSlideBtn" type="button" aria-label="Slide penyebab berikutnya" class="w-10 h-10 rounded-full border border-white/15 hover:border-amberbrand hover:bg-amberbrand hover:text-charcoal-900 text-white flex items-center justify-center transition-all">
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- ===================================================================== -->
    <!-- 6. SECTION DUALITAS DAMPAK (#dampak — FILTER NEGATIF VS POSITIF)      -->
    <!-- ===================================================================== -->
    <section id="dampak" class="py-20 lg:py-28 bg-surface-slate border-b border-line">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 border-b border-line pb-8 mb-10">
                <div>
                    <span class="font-mono text-xs font-bold uppercase tracking-[0.2em] text-ember block mb-2.5">
                        03 / Neraca Ekologi & Sosial
                    </span>
                    <h2 class="font-display font-bold text-3xl sm:text-4xl lg:text-[42px] text-ink tracking-tight">
                        Dualitas Dampak Kebakaran
                    </h2>
                    <p class="text-ink-body text-sm sm:text-base mt-2 max-w-xl leading-relaxed">
                        Bandingkan dampak destruktif akibat karhutla tak terkendali dengan peran ekologis kebakaran alami skala kecil yang terkontrol.
                    </p>
                </div>

                <!-- Tombol Switcher Filter Kategori Dampak (memanggil JS filterDampak) -->
                <div class="inline-flex bg-white p-1.5 rounded-full border border-line shadow-sm self-start lg:self-auto" role="tablist" aria-label="Filter kategori dampak">
                    <button type="button" id="btn-negatif" onclick="filterDampak('negatif')" class="px-5 py-2.5 rounded-full text-xs font-bold transition-all bg-ember text-white shadow-sm">
                        Dampak Negatif (<?= count($dampakNegatif) ?>)
                    </button>
                    <button type="button" id="btn-positif" onclick="filterDampak('positif')" class="px-5 py-2.5 rounded-full text-xs font-bold transition-all text-ink-body hover:text-ink">
                        Dampak Positif Alami (<?= count($dampakPositif) ?>)
                    </button>
                </div>
            </div>

            <div id="dampak-container" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- LOOPING KARTU DAMPAK NEGATIF (Tampil default) -->
                <?php foreach ($dampakNegatif as $nIdx => $neg):
                    $icon = $negIcons[$nIdx % count($negIcons)];
                    $tag  = $negTags[$nIdx] ?? 'DAMPAK KARHUTLA';
                    $num  = str_pad((string)($nIdx + 1), 2, '0', STR_PAD_LEFT);
                ?>
                <div class="dampak-card negatif bg-white border border-line hover:border-ember/40 rounded-2xl p-7 transition-all shadow-[0_2px_12px_rgba(15,23,42,0.03)]">
                    <div class="flex items-center justify-between mb-5">
                        <div class="w-11 h-11 rounded-xl bg-ember-soft text-ember flex items-center justify-center text-lg">
                            <i class="fa-solid <?= $icon ?>"></i>
                        </div>
                        <span class="font-mono text-[11px] font-bold text-ink-muted"><?= $num ?> • <?= $tag ?></span>
                    </div>
                    <h3 class="font-display font-bold text-xl text-ink mb-2"><?= h($neg['judul']) ?></h3>
                    <p class="text-ink-body text-sm leading-relaxed">
                        <?= h($neg['deskripsi']) ?>
                    </p>
                </div>
                <?php endforeach; ?>

                <!-- LOOPING KARTU DAMPAK POSITIF ALAMI (Awalnya hidden, muncul saat tab Positif diklik) -->
                <?php foreach ($dampakPositif as $pIdx => $pos):
                    $icon = $posIcons[$pIdx % count($posIcons)];
                    $tag  = $posTags[$pIdx] ?? 'EKOLOGI ALAMI';
                    $num  = str_pad((string)($pIdx + 1), 2, '0', STR_PAD_LEFT);
                ?>
                <div class="dampak-card positif hidden bg-white border border-line hover:border-forest/40 rounded-2xl p-7 transition-all shadow-[0_2px_12px_rgba(15,23,42,0.03)]">
                    <div class="flex items-center justify-between mb-5">
                        <div class="w-11 h-11 rounded-xl bg-forest-soft text-forest flex items-center justify-center text-lg">
                            <i class="fa-solid <?= $icon ?>"></i>
                        </div>
                        <span class="font-mono text-[11px] font-bold text-forest"><?= $num ?> • <?= $tag ?></span>
                    </div>
                    <h3 class="font-display font-bold text-xl text-ink mb-2"><?= h($pos['judul']) ?></h3>
                    <p class="text-ink-body text-sm leading-relaxed">
                        <?= h($pos['deskripsi']) ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ===================================================================== -->
    <!-- 7. SECTION PROTOKOL MITIGASI (#mitigasi — 3 FASE MANAJEMEN BENCANA)   -->
    <!-- ===================================================================== -->
    <section id="mitigasi" class="py-20 lg:py-28 bg-surface-white border-b border-line">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 border-b border-line pb-8 mb-10">
                <div>
                    <span class="font-mono text-xs font-bold uppercase tracking-[0.2em] text-forest block mb-2.5">
                        04 / Siklus Manajemen Bencana
                    </span>
                    <h2 class="font-display font-bold text-3xl sm:text-4xl lg:text-[42px] text-ink tracking-tight">
                        Protokol Mitigasi 3 Fase
                    </h2>
                </div>

                <!-- Navigasi Tab 3 Fase Mitigasi (memanggil JS switchTab) -->
                <div class="inline-flex flex-wrap gap-1.5 bg-surface-slate p-1.5 rounded-full border border-line self-start lg:self-auto" role="tablist" aria-label="Fase mitigasi bencana">
                    <button type="button" id="tab-btn-pra" onclick="switchTab('pra')" class="px-5 py-2.5 rounded-full text-xs font-bold transition-all bg-charcoal-900 text-white shadow-sm">
                        01. Pra-Bencana (<?= count($mitigasiPra) ?>)
                    </button>
                    <button type="button" id="tab-btn-tanggap" onclick="switchTab('tanggap')" class="px-5 py-2.5 rounded-full text-xs font-bold transition-all text-ink-body hover:text-ink">
                        02. Tanggap Darurat (<?= count($mitigasiTanggap) ?>)
                    </button>
                    <button type="button" id="tab-btn-pasca" onclick="switchTab('pasca')" class="px-5 py-2.5 rounded-full text-xs font-bold transition-all text-ink-body hover:text-ink">
                        03. Pascabencana (<?= count($mitigasiPasca) ?>)
                    </button>
                </div>
            </div>

            <div class="max-w-6xl mx-auto">
                <!-- PANEL FASE 01: PRA-BENCANA -->
                <div id="content-pra" class="panel-reveal">
                    <div class="bg-forest-soft border border-forest/20 rounded-2xl px-6 py-4 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <span class="font-mono text-xs font-bold uppercase tracking-wider text-forest">
                            FASE 01 • PENCEGAHAN & KESIAPSIAGAAN
                        </span>
                        <span class="text-xs text-forest-dark font-medium">Fokus: Eliminasi pemicu awal & pemantauan satelit rutin</span>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-6">
                        <?php foreach ($mitigasiPra as $mIdx => $mItem): $ic = $praIcons[$mIdx % count($praIcons)]; ?>
                        <div class="bg-white border border-line border-l-4 border-l-forest rounded-2xl p-7 flex gap-4 items-start">
                            <div class="w-11 h-11 rounded-xl bg-forest-soft text-forest flex items-center justify-center text-lg shrink-0">
                                <i class="fa-solid <?= $ic ?>"></i>
                            </div>
                            <div>
                                <h4 class="font-display font-bold text-lg text-ink mb-1.5"><?= h($mItem['judul']) ?></h4>
                                <p class="text-ink-body text-sm leading-relaxed"><?= h($mItem['deskripsi']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- PANEL FASE 02: TANGGAP DARURAT -->
                <div id="content-tanggap" class="hidden panel-reveal">
                    <div class="bg-ember-soft border border-ember/20 rounded-2xl px-6 py-4 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <span class="font-mono text-xs font-bold uppercase tracking-wider text-ember">
                            FASE 02 • OPERASI PEMADAMAN & PENYELAMATAN
                        </span>
                        <span class="text-xs text-ember-dark font-medium">Fokus: Lokalisasi api, evakuasi warga, & mitigasi kabut asap</span>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-6">
                        <?php foreach ($mitigasiTanggap as $mIdx => $mItem): $ic = $tanggapIcons[$mIdx % count($tanggapIcons)]; ?>
                        <div class="bg-white border border-line border-l-4 border-l-ember rounded-2xl p-7 flex gap-4 items-start">
                            <div class="w-11 h-11 rounded-xl bg-ember-soft text-ember flex items-center justify-center text-lg shrink-0">
                                <i class="fa-solid <?= $ic ?>"></i>
                            </div>
                            <div>
                                <h4 class="font-display font-bold text-lg text-ink mb-1.5"><?= h($mItem['judul']) ?></h4>
                                <p class="text-ink-body text-sm leading-relaxed"><?= h($mItem['deskripsi']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- PANEL FASE 03: PASCABENCANA -->
                <div id="content-pasca" class="hidden panel-reveal">
                    <div class="bg-sky-50 border border-sky-200 rounded-2xl px-6 py-4 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <span class="font-mono text-xs font-bold uppercase tracking-wider text-sky-800">
                            FASE 03 • REHABILITASI, RESTORASI & PENEGAKAN HUKUM
                        </span>
                        <span class="text-xs text-sky-900 font-medium">Fokus: Pembasahan gambut & verifikasi padam tuntas</span>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-6">
                        <?php foreach ($mitigasiPasca as $mIdx => $mItem): $ic = $pascaIcons[$mIdx % count($pascaIcons)]; ?>
                        <div class="bg-white border border-line border-l-4 border-l-sky-600 rounded-2xl p-7 flex gap-4 items-start">
                            <div class="w-11 h-11 rounded-xl bg-sky-50 text-sky-700 flex items-center justify-center text-lg shrink-0">
                                <i class="fa-solid <?= $ic ?>"></i>
                            </div>
                            <div>
                                <h4 class="font-display font-bold text-lg text-ink mb-1.5"><?= h($mItem['judul']) ?></h4>
                                <p class="text-ink-body text-sm leading-relaxed"><?= h($mItem['deskripsi']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===================================================================== -->
    <!-- 8. SECTION STUDI KASUS (#studi-kasus — ARSIP KASUS INTERNASIONAL)     -->
    <!-- ===================================================================== -->
    <section id="studi-kasus" class="py-20 lg:py-24 bg-surface-slate border-b border-line">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Carousel studi kasus: 1 kasus = tampil diam, 2+ kasus = geser otomatis -->
            <div id="studiCarousel" class="relative bg-white border border-line rounded-3xl overflow-hidden shadow-[0_12px_36px_rgba(15,23,42,0.04)]" data-total="<?= (int)$studiTotal ?>" <?= $studiTotal > 1 ? 'tabindex="0" aria-roledescription="carousel" aria-label="Riwayat studi kasus karhutla"' : '' ?>>
                <div class="overflow-hidden">
                    <div id="studiTrack" class="flex transition-transform duration-700 ease-[cubic-bezier(0.22,1,0.36,1)] will-change-transform">
                    <?php foreach ($studiList as $sIdx => $sk):
                        $skJudul   = studi_pick($sk, ['judul']);
                        $skLokasi  = studi_pick($sk, ['lokasi']);
                        $skTahun   = studi_pick($sk, ['tahun']);
                        $skSumber  = studi_pick($sk, ['sumber']);
                        $skDesk    = studi_pick($sk, ['deskripsi']);
                        $skTipe    = studi_pick($sk, ['tipe_dominan', 'tipe']);
                        $skPemicu  = studi_pick($sk, ['faktor_pemicu', 'pemicu']);
                        $skImg     = studi_media_url(studi_pick($sk, ['gambar', 'foto', 'gambar_url', 'image']));
                        $skLink    = studi_link_url(studi_pick($sk, ['link_berita', 'link', 'url_berita', 'url', 'referensi']));
                        $skDomain  = studi_domain($skLink);
                        $skNo      = str_pad((string)($sIdx + 1), $studiPad, '0', STR_PAD_LEFT);
                        $skTot     = str_pad((string)$studiTotal, $studiPad, '0', STR_PAD_LEFT);
                    ?>
                    <div class="studi-slide w-full flex-shrink-0 grid lg:grid-cols-12">
                        <!-- Kolom Kiri: Foto dokumentasi asli (atau ilustrasi cadangan) -->
                        <div class="lg:col-span-6 bg-charcoal-900 p-6 sm:p-8 flex flex-col justify-between">
                            <div class="flex items-center justify-between font-mono text-[11px] text-slate-400 mb-4">
                                <span class="text-amberbrand font-semibold">CASE ARCHIVE<?= $skTahun !== '' ? ' • ' . h(strtoupper($skTahun)) : '' ?></span>
                                <span><?= $studiTotal > 1 ? 'KASUS ' . $skNo . ' / ' . $skTot : h($skLokasi) ?></span>
                            </div>

                            <div class="relative aspect-[16/10] rounded-2xl overflow-hidden bg-charcoal-950 border border-white/10">
                                <?php if ($skImg !== ''): ?>
                                <img src="<?= h($skImg) ?>" alt="<?= h($skJudul) ?>" decoding="async" class="absolute inset-0 w-full h-full object-cover"
                                     onerror="this.onerror=null;this.style.display='none';var f=this.parentNode.querySelector('.studi-fallback');if(f)f.classList.remove('hidden');var c=this.parentNode.querySelector('.studi-caption');if(c)c.classList.add('hidden');">
                                <div class="studi-fallback hidden absolute inset-0"><?= $studiFallbackSvg ?></div>
                                <div class="studi-caption absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 to-transparent px-4 pt-10 pb-3 font-mono text-[10px] sm:text-[11px] text-slate-200 uppercase tracking-wider">
                                    <i class="fa-solid fa-camera text-amberbrand mr-1.5"></i> Foto dokumentasi<?= $skSumber !== '' ? ' • ' . h($skSumber) : '' ?>
                                </div>
                                <?php else: ?>
                                <div class="absolute inset-0"><?= $studiFallbackSvg ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mt-4 flex items-center justify-between gap-3 text-xs text-slate-300 font-mono">
                                <span class="truncate"><i class="fa-solid fa-newspaper text-ember mr-1.5"></i> Source: <?= h($skSumber !== '' ? $skSumber : ($skDomain !== '' ? $skDomain : '-')) ?></span>
                                <span class="shrink-0"><?= h($skLokasi) ?></span>
                            </div>
                        </div>

                        <!-- Kolom Kanan: Narasi, parameter kejadian & link referensi berita -->
                        <div class="lg:col-span-6 p-7 sm:p-10 lg:p-12 flex flex-col justify-center">
                            <span class="font-mono text-xs font-bold uppercase tracking-[0.2em] text-ember block mb-2.5">
                                05 / Studi Kasus Internasional
                            </span>
                            <h3 class="font-display font-bold text-2xl sm:text-4xl text-ink tracking-tight mb-4">
                                <?= h($skJudul) ?>
                            </h3>
                            <p class="text-ink-body text-sm sm:text-base leading-relaxed mb-7">
                                <?= h($skDesk) ?>
                            </p>

                            <div class="grid grid-cols-2 gap-4 pt-6 border-t border-line font-mono text-xs">
                                <div>
                                    <span class="text-ink-muted block">LOKASI & TAHUN</span>
                                    <span class="text-ink font-bold mt-1 block"><?= h($skLokasi . ($skTahun !== '' ? ' (' . $skTahun . ')' : '')) ?></span>
                                </div>
                                <?php if ($skTipe !== ''): ?>
                                <div>
                                    <span class="text-ink-muted block">TIPE DOMINAN</span>
                                    <span class="text-ember font-bold mt-1 block"><?= h($skTipe) ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($skPemicu !== ''): ?>
                                <div>
                                    <span class="text-ink-muted block">FAKTOR PEMICU</span>
                                    <span class="text-ink font-bold mt-1 block"><?= h($skPemicu) ?></span>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <span class="text-ink-muted block">SUMBER DOKUMENTASI</span>
                                    <span class="text-ink font-bold mt-1 block"><?= h($skSumber !== '' ? $skSumber : '-') ?></span>
                                </div>
                            </div>

                            <?php if ($skLink !== ''): ?>
                            <a href="<?= h($skLink) ?>" target="_blank" rel="noopener noreferrer nofollow"
                               class="mt-7 self-start inline-flex items-center gap-2.5 bg-charcoal-900 hover:bg-ember text-white font-bold text-xs px-5 py-3 rounded-full transition-colors">
                                <i class="fa-solid fa-arrow-up-right-from-square text-[11px]"></i>
                                Baca referensi berita<?= $skDomain !== '' ? ' • ' . h($skDomain) : '' ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($studiTotal > 1): ?>
                <!-- Kontrol carousel (hanya muncul jika kasus lebih dari 1) -->
                <div class="bg-surface-slate border-t border-line px-6 sm:px-10 py-4 flex items-center justify-between gap-4">
                    <button id="studiPrev" type="button" aria-label="Studi kasus sebelumnya" class="w-10 h-10 rounded-full border border-line bg-white hover:bg-charcoal-900 hover:text-white hover:border-charcoal-900 text-ink flex items-center justify-center transition-all">
                        <i class="fa-solid fa-arrow-left text-xs"></i>
                    </button>
                    <div id="studiDots" class="flex items-center justify-center gap-2.5 flex-wrap" role="tablist" aria-label="Navigasi studi kasus"></div>
                    <button id="studiNext" type="button" aria-label="Studi kasus berikutnya" class="w-10 h-10 rounded-full border border-line bg-white hover:bg-charcoal-900 hover:text-white hover:border-charcoal-900 text-ink flex items-center justify-center transition-all">
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>


    <!-- ===================================================================== -->
    <!-- 9. SECTION TIM KELOMPOK (#tim — DAFTAR ANGGOTA OTOMATIS DARI DB)      -->
    <!-- ===================================================================== -->
    <section id="tim" class="py-20 lg:py-24 bg-surface-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <span class="font-mono text-xs font-bold uppercase tracking-[0.2em] text-forest block mb-2.5">
                    06 / Pengembang Platform
                </span>
                <h2 class="font-display font-bold text-3xl sm:text-4xl text-ink tracking-tight">
                    Tim Kelompok 6
                </h2>
                <p class="text-ink-body text-sm mt-2">
                    Mata Kuliah Sistem Informasi Manajemen Bencana
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-5">
                <?php foreach ($timList as $member):
                    // Ekstrak 2 huruf inisial pertama dari nama anggota untuk dijadikan avatar lingkaran
                    $parts = preg_split('/\s+/', trim((string)($member['nama'] ?? '')));
                    $initials = '';
                    foreach (array_slice($parts, 0, 2) as $p) {
                        if ($p !== '') $initials .= mb_strtoupper(mb_substr($p, 0, 1));
                    }
                    $isLead = (($member['nim'] ?? '') === '124250160');
                ?>
                <div class="bg-surface-slate border border-line <?= $isLead ? 'hover:border-amberbrand' : 'hover:border-charcoal-900' ?> rounded-2xl p-6 text-center transition-all">
                    <div class="w-11 h-11 rounded-full <?= $isLead ? 'bg-charcoal-900 text-amberbrand' : 'bg-white border border-line text-charcoal-900' ?> font-display font-bold text-sm mx-auto flex items-center justify-center mb-4">
                        <?= h($initials) ?>
                    </div>
                    <h4 class="font-bold text-ink text-sm"><?= h($member['nama']) ?></h4>
                    <p class="font-mono text-xs <?= $isLead ? 'text-ember font-semibold' : 'text-ink-muted' ?> mt-1.5"><?= h($member['nim']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ===================================================================== -->
    <!-- 10. FOOTER HALAMAN UTAMA                                              -->
    <!-- ===================================================================== -->
    <footer class="bg-charcoal-950 text-white pt-16 pb-10 border-t border-white/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-8 pb-12 border-b border-white/10">
                <div class="text-center md:text-left">
                    <div class="inline-flex items-center gap-3 mb-3">
                        <div class="w-8 h-8 rounded-lg bg-amberbrand text-charcoal-900 flex items-center justify-center">
                            <i class="fa-solid fa-fire-flame-curved text-sm"></i>
                        </div>
                        <span class="font-display font-bold text-xl tracking-tight text-white">
                            PYROWATCH<span class="text-amberbrand">ID</span>
                        </span>
                    </div>
                    <p class="font-display font-bold text-lg sm:text-xl tracking-wider text-slate-200 uppercase">
                        PREVENT, PROTECT, AND RESTORE.
                    </p>
                    <p class="text-xs sm:text-sm text-slate-400 mt-1.5">
                        Tugas Mata Kuliah Sistem Informasi Manajemen Bencana — Kelompok 6.
                    </p>
                </div>

                <a href="pyrowatch-v3-siap.html" class="inline-flex items-center gap-2.5 bg-amberbrand hover:bg-amberbrand-hover text-charcoal-900 font-bold text-xs uppercase tracking-wider px-6 py-3.5 rounded-full transition-all">
                    <i class="fa-solid fa-map-location-dot"></i>
                    Akses Dashboard Peta Karhutla
                </a>
            </div>

            <div class="pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 font-mono text-xs text-slate-500">
                <span>© 2026 PyroWatch ID Team. All rights reserved.</span>
                <span>Data Integrasi: NASA FIRMS VIIRS • GIBS • KHG Gambut</span>
            </div>
        </div>
    </footer>

    <!-- ===================================================================== -->
    <!-- LOGIKA INTERAKTIF JAVASCRIPT                                          -->
    <!-- ===================================================================== -->
    <script>
        /* 1. Pengatur Buka-Tutup Menu Navigasi Mobile */
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileMenu = document.getElementById('mobileMenu');

        mobileMenuBtn.addEventListener('click', () => {
            const isHidden = mobileMenu.classList.toggle('hidden');
            mobileMenuBtn.setAttribute('aria-expanded', String(!isHidden));
        });

        // Tutup menu mobile otomatis saat salah satu tautan diklik
        mobileMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                mobileMenu.classList.add('hidden');
                mobileMenuBtn.setAttribute('aria-expanded', 'false');
            });
        });

        /* 2. Pengatur Diagram Interaktif 3 Lapisan Kebakaran (#edukasi) */
        // Data strata dikirim langsung dari PHP ke JavaScript dalam bentuk objek JSON
        const strataData = <?= json_encode($strataDefaults, JSON_UNESCAPED_UNICODE) ?>;

        function selectStrata(key) {
            const info = strataData[key];
            if (!info) return;

            // Perbarui teks & badge pada panel inspektor di sebelah kanan diagram SVG
            const badgeEl = document.getElementById('strataBadge');
            badgeEl.className = info.badgeClass;
            badgeEl.textContent = info.badge;
            document.getElementById('strataTitle').textContent = info.title;
            document.getElementById('strataDesc').textContent = info.desc;
            document.getElementById('strataSpeed').textContent = info.speed;
            document.getElementById('strataAction').textContent = info.action;

            // Sorot lapisan SVG & kartu yang dipilih, redupkan 2 lapisan lainnya
            ['tajuk', 'permukaan', 'bawah'].forEach(item => {
                const band = document.getElementById(`svg-band-${item}`);
                const card = document.getElementById(`strata-card-${item}`);
                if (item === key) {
                    band.classList.remove('opacity-55');
                    card.className = `cursor-pointer bg-white ${strataData[item].cardBorder} rounded-2xl p-7 transition-all shadow-md`;
                } else {
                    band.classList.add('opacity-55');
                    card.className = 'cursor-pointer bg-white border border-line hover:border-slate-400 rounded-2xl p-7 transition-all shadow-sm';
                }
            });
        }

        /* 3. Carousel Otomatis Faktor Penyebab Karhutla (#penyebab) */
        (function initSingleSlideCarousel() {
            const carousel = document.getElementById('causeCarousel');
            const track = document.getElementById('causeTrack');
            const slides = Array.from(track.querySelectorAll('.cause-slide'));
            const dotsContainer = document.getElementById('carouselDots');
            const prevBtn = document.getElementById('prevSlideBtn');
            const nextBtn = document.getElementById('nextSlideBtn');
            const progressBar = document.getElementById('carouselProgress');

            const totalSlides = slides.length;
            if (!totalSlides) return;
            let activeIndex = 0;
            let timerId = null;
            let isPaused = false;
            const INTERVAL_MS = 4500; // Durasi perpindahan slide otomatis setiap 4,5 detik

            // Buat tombol indikator titik (dots) sesuai jumlah slide dari database
            function renderDots() {
                dotsContainer.innerHTML = '';
                for (let i = 0; i < totalSlides; i++) {
                    const dot = document.createElement('button');
                    dot.type = 'button';
                    dot.setAttribute('aria-label', `Tampilkan penyebab ${String(i + 1).padStart(2, '0')}`);
                    dot.setAttribute('aria-selected', String(i === activeIndex));
                    dot.className = i === activeIndex
                        ? 'h-2.5 w-9 rounded-full bg-amberbrand transition-all duration-300'
                        : 'h-2.5 w-2.5 rounded-full bg-white/25 hover:bg-white/50 transition-all duration-300';
                    dot.addEventListener('click', () => {
                        goToSlide(i);
                        resetTimer();
                    });
                    dotsContainer.appendChild(dot);
                }
            }

            // Reset animasi progress bar dari 0% setiap kali berpindah slide
            function restartProgress() {
                progressBar.classList.remove('carousel-progress-active');
                void progressBar.offsetWidth; // Trigger reflow CSS agar animasi ulang dari awal
                progressBar.classList.add('carousel-progress-active');
                progressBar.classList.toggle('carousel-progress-paused', isPaused);
            }

            // Geser track horizontal ke indeks slide target
            function goToSlide(index) {
                activeIndex = ((index % totalSlides) + totalSlides) % totalSlides;
                track.style.transform = `translate3d(-${activeIndex * 100}%, 0, 0)`;
                renderDots();
                restartProgress();
            }

            function startTimer() {
                clearInterval(timerId);
                timerId = setInterval(() => {
                    if (!isPaused) {
                        goToSlide(activeIndex + 1);
                    }
                }, INTERVAL_MS);
                restartProgress();
            }

            function resetTimer() {
                clearInterval(timerId);
                startTimer();
            }

            // Event tombol panah kiri & kanan
            prevBtn.addEventListener('click', () => {
                goToSlide(activeIndex - 1);
                resetTimer();
            });

            nextBtn.addEventListener('click', () => {
                goToSlide(activeIndex + 1);
                resetTimer();
            });

            // Jeda (pause) otomatis saat kursor mouse diarahkan ke carousel
            carousel.addEventListener('mouseenter', () => {
                isPaused = true;
                progressBar.classList.add('carousel-progress-paused');
            });

            carousel.addEventListener('mouseleave', () => {
                isPaused = false;
                progressBar.classList.remove('carousel-progress-paused');
            });

            // Dukungan gestur geser layar sentuh (touch swipe) di perangkat mobile
            let touchStartX = 0;
            carousel.addEventListener('touchstart', (e) => {
                touchStartX = e.changedTouches[0].screenX;
                isPaused = true;
                progressBar.classList.add('carousel-progress-paused');
            }, { passive: true });

            carousel.addEventListener('touchend', (e) => {
                const deltaX = touchStartX - e.changedTouches[0].screenX;
                if (Math.abs(deltaX) > 40) {
                    goToSlide(deltaX > 0 ? activeIndex + 1 : activeIndex - 1);
                }
                isPaused = false;
                progressBar.classList.remove('carousel-progress-paused');
                resetTimer();
            }, { passive: true });

            // Dukungan navigasi menggunakan tombol panah keyboard
            carousel.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowLeft') {
                    goToSlide(activeIndex - 1);
                    resetTimer();
                } else if (e.key === 'ArrowRight') {
                    goToSlide(activeIndex + 1);
                    resetTimer();
                }
            });

            renderDots();
            startTimer();
        })();

        /* 4. Filter Kategori Dampak Negatif vs Positif Alami (#dampak) */
        function filterDampak(kategori) {
            const cards = document.querySelectorAll('.dampak-card');
            const btnNegatif = document.getElementById('btn-negatif');
            const btnPositif = document.getElementById('btn-positif');

            if (kategori === 'negatif') {
                btnNegatif.className = 'px-5 py-2.5 rounded-full text-xs font-bold transition-all bg-ember text-white shadow-sm';
                btnPositif.className = 'px-5 py-2.5 rounded-full text-xs font-bold transition-all text-ink-body hover:text-ink';
                cards.forEach(card => {
                    const show = card.classList.contains('negatif');
                    card.classList.toggle('hidden', !show);
                    if (show) card.classList.add('panel-reveal');
                });
            } else {
                btnPositif.className = 'px-5 py-2.5 rounded-full text-xs font-bold transition-all bg-forest text-white shadow-sm';
                btnNegatif.className = 'px-5 py-2.5 rounded-full text-xs font-bold transition-all text-ink-body hover:text-ink';
                cards.forEach(card => {
                    const show = card.classList.contains('positif');
                    card.classList.toggle('hidden', !show);
                    if (show) card.classList.add('panel-reveal');
                });
            }
        }

        /* 5. Pengatur Perpindahan Tab 3 Fase Mitigasi (#mitigasi) */
        function switchTab(fase) {
            ['pra', 'tanggap', 'pasca'].forEach(t => {
                const btn = document.getElementById(`tab-btn-${t}`);
                const content = document.getElementById(`content-${t}`);
                if (t === fase) {
                    btn.className = 'px-5 py-2.5 rounded-full text-xs font-bold transition-all bg-charcoal-900 text-white shadow-sm';
                    content.classList.remove('hidden');
                } else {
                    btn.className = 'px-5 py-2.5 rounded-full text-xs font-bold transition-all text-ink-body hover:text-ink';
                    content.classList.add('hidden');
                }
            });
        }
    </script>

    <!-- Lapisan Animasi Dinamis (Scroll Progress Bar, Partikel Bara Api, Scroll Reveal, & Efek 3D Tilt) -->
    <script>
    (()=>{const st=document.createElement('style');st.textContent=`
    html{scroll-padding-top:72px}
    #scrollBar{position:fixed;top:0;left:0;height:3px;width:100%;transform-origin:0 50%;transform:scaleX(0);background:linear-gradient(90deg,#FBBF24,#EA580C);z-index:100}
    #toTop{position:fixed;right:22px;bottom:22px;width:46px;height:46px;border-radius:999px;background:#FBBF24;color:#1E252B;box-shadow:0 10px 28px rgba(0,0,0,.3);z-index:90;opacity:0;translate:0 20px;pointer-events:none;transition:opacity .4s,translate .4s,transform .25s}
    #toTop.show{opacity:1;translate:0 0;pointer-events:auto}
    #toTop:hover{transform:translateY(-4px) scale(1.06)}
    nav.sticky{transition:box-shadow .35s}
    nav.sticky>div:first-child{transition:height .35s ease}
    nav.nav-scrolled{box-shadow:0 10px 34px rgba(0,0,0,.38)}
    nav.nav-scrolled>div:first-child{height:3.5rem}
    .nav-link{position:relative}
    .nav-link::after{content:"";position:absolute;left:0;right:0;bottom:-6px;height:2px;border-radius:2px;background:#FBBF24;transform:scaleX(0);transform-origin:left;transition:transform .35s cubic-bezier(.22,1,.36,1)}
    .nav-link:hover::after,.nav-link.active::after{transform:scaleX(1)}
    html nav .nav-link.active{color:#FBBF24}
    html .reveal{opacity:0;translate:0 38px;will-change:opacity,translate;transition:opacity .9s cubic-bezier(.22,1,.36,1) var(--d,0s),translate .9s cubic-bezier(.22,1,.36,1) var(--d,0s),transform .35s ease,box-shadow .35s ease,border-color .3s ease}
    html .reveal.from-left{translate:-52px 0}
    html .reveal.from-right{translate:52px 0}
    html .reveal.in{opacity:1;translate:0 0}
    article:hover,.dampak-card:hover,#tim .grid>div:hover,[id^=strata-card]:hover{transform:translateY(-6px);box-shadow:0 20px 44px rgba(15,23,42,.10)}
    header{--mx:50%;--my:50%}
    header::after{content:"";position:absolute;inset:0;pointer-events:none;z-index:1;background:radial-gradient(520px circle at var(--mx) var(--my),rgba(251,191,36,.10),transparent 60%)}
    header .grid>div:last-child>div{transition:transform .35s ease-out}
    header h1 span.text-amberbrand{background:linear-gradient(90deg,#FBBF24,#FDE68A,#EA580C,#FBBF24);background-size:220% auto;-webkit-background-clip:text;background-clip:text;color:transparent;animation:shine 6s linear infinite}
    @keyframes shine{to{background-position:220% center}}
    .embers{position:absolute;inset:0;overflow:hidden;pointer-events:none;z-index:1}
    .ember-p{position:absolute;bottom:-12px;left:var(--x);width:var(--s);height:var(--s);border-radius:50%;background:#FBBF24;box-shadow:0 0 10px 2px rgba(234,88,12,.7);opacity:0;animation:rise var(--t) linear var(--dl) infinite}
    @keyframes rise{0%{opacity:0;transform:translate(0,0)}12%{opacity:.9}100%{opacity:0;transform:translate(var(--dx),-75vh)}}
    svg path[fill="#EA580C"],svg path[fill="#F97316"],svg path[fill="#FBBF24"]{transform-box:fill-box;transform-origin:50% 100%;animation:flick 1.7s ease-in-out infinite}
    svg path[fill="#FBBF24"]{animation-duration:1.25s;animation-direction:reverse}
    @keyframes flick{0%,100%{transform:scale(1,1)}35%{transform:scale(.93,1.08)}70%{transform:scale(1.05,.96)}}
    svg ellipse[fill="#FBBF24"],svg ellipse[fill="#EA580C"]{transform-box:fill-box;transform-origin:center;animation:glow 2.6s ease-in-out infinite}
    @keyframes glow{50%{opacity:.6;transform:scale(1.12)}}
    .cause-slide .grid>div:first-child>*{opacity:0;translate:-26px 0;transition:opacity .7s ease,translate .8s cubic-bezier(.22,1,.36,1)}
    .cause-slide .grid>div:last-child svg{opacity:0;scale:.93;transition:opacity .8s ease .2s,scale 1s cubic-bezier(.22,1,.36,1) .2s}
    .cause-slide.is-active .grid>div:first-child>*{opacity:1;translate:0 0}
    .cause-slide.is-active .grid>div:last-child svg{opacity:1;scale:1}
    .cause-slide.is-active .grid>div:first-child>:nth-child(2){transition-delay:.1s}
    .cause-slide.is-active .grid>div:first-child>:nth-child(3){transition-delay:.2s}
    .cause-slide.is-active .grid>div:first-child>:nth-child(4){transition-delay:.3s}
    @media (prefers-reduced-motion:reduce){
     html .reveal{opacity:1;translate:none;transition:none}
     *{animation:none!important}
     .cause-slide .grid>div>*,.cause-slide svg{opacity:1!important;translate:none!important;scale:none!important}
    }
    `;document.head.append(st);})();

    (()=>{
    const reduce=matchMedia('(prefers-reduced-motion: reduce)').matches;
    const $=(s,r=document)=>[...r.querySelectorAll(s)];

    // Tambahkan elemen garis progress scroll & tombol kembali ke atas (#toTop)
    const bar=document.createElement('div');bar.id='scrollBar';document.body.append(bar);
    const topBtn=document.createElement('button');topBtn.id='toTop';topBtn.setAttribute('aria-label','Kembali ke atas');
    topBtn.innerHTML='<i class="fa-solid fa-arrow-up"></i>';topBtn.onclick=()=>scrollTo({top:0,behavior:'smooth'});document.body.append(topBtn);

    // Bangkitkan 22 partikel bara api melayang di area Hero jika pengguna tidak mengaktifkan reduced-motion
    const hero=document.querySelector('header');
    if(!reduce){const box=document.createElement('div');box.className='embers';
     for(let i=0;i<22;i++){const p=document.createElement('span');p.className='ember-p';
      p.style.cssText=`--x:${Math.random()*100}%;--s:${2+Math.random()*4}px;--t:${7+Math.random()*7}s;--dl:${-Math.random()*12}s;--dx:${(Math.random()-.5)*160}px`;box.append(p);}
     hero.prepend(box);}

    // Daftarkan elemen-elemen halaman agar memiliki efek animasi muncul bertahap (staggered reveal)
    const add=(sel,cls='',step=.1,mod=3)=>$(sel).forEach(el=>{el.classList.add('reveal');if(cls)el.classList.add(cls);
     const sib=[...el.parentElement.children].filter(c=>c.classList.contains('reveal'));
     el.style.setProperty('--d',(sib.indexOf(el)%mod)*step+'s');});
    add('header .grid > div:first-child > *','',.12,8);
    add('header .grid > div:last-child','from-right');
    document.querySelector('header .grid > div:last-child').style.setProperty('--d','.3s');
    add('section > div > div:first-child:not(.grid)','',.1,1);
    add('#edukasi .bg-surface-slate.rounded-2xl','from-left');
    add('#edukasi .border-b.border-line','',.1,1);
    add('article','',.15,2);
    add('#edukasi .bg-charcoal-900');
    add('[id^=strata-card]');
    add('#causeCarousel');
    add('.dampak-card','',.1,3);
    add('[id^=content-] > div:first-child','',.1,1);
    add('[id^=content-] .grid > *','',.1,2);
    add('#tim .grid > *','',.09,5);
    add('footer .max-w-7xl > *','',.12,3);

    // IntersectionObserver memantau kapan elemen masuk ke layar untuk memicu animasi .in
    const io=new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target);}}),{threshold:.12,rootMargin:'0px 0px -6% 0px'});
    $('.reveal').forEach(el=>io.observe(el));

    // Sinkronisasi posisi scroll dengan progress bar, tinggi navbar, efek parallax, & menu aktif
    const nav=document.querySelector('nav.sticky');
    const heroBg=hero.querySelector('svg');
    const heroCard=hero.querySelector('.grid > div:last-child > div');
    const links=$('nav > div > div:nth-child(2) a[href^="#"]');
    links.forEach(a=>a.classList.add('nav-link'));
    const secs=links.map(a=>document.querySelector(a.getAttribute('href')));
    let tick=false;
    function onScroll(){if(tick)return;tick=true;requestAnimationFrame(()=>{
     const y=scrollY,h=document.documentElement.scrollHeight-innerHeight;
     bar.style.transform=`scaleX(${h>0?y/h:0})`;
     nav.classList.toggle('nav-scrolled',y>40);
     topBtn.classList.toggle('show',y>700);
     if(!reduce&&y<hero.offsetHeight){heroBg.style.translate=`0 ${y*.18}px`;heroCard.style.translate=`0 ${-y*.05}px`;}
     let cur=-1;secs.forEach((s,i)=>{if(s&&s.getBoundingClientRect().top<innerHeight*.35)cur=i;});
     links.forEach((a,i)=>a.classList.toggle('active',i===cur));
     tick=false;});}
    addEventListener('scroll',onScroll,{passive:true});onScroll();

    // Efek kemiringan 3D (3D perspective tilt) dan sorotan cahaya mengikuti kursor di Hero
    if(!reduce&&matchMedia('(hover:hover)').matches){
     hero.addEventListener('mousemove',e=>{const r=hero.getBoundingClientRect();
      const x=(e.clientX-r.left)/r.width-.5,y=(e.clientY-r.top)/r.height-.5;
      hero.style.setProperty('--mx',e.clientX-r.left+'px');hero.style.setProperty('--my',e.clientY-r.top+'px');
      heroCard.style.transform=`perspective(1000px) rotateY(${x*7}deg) rotateX(${-y*7}deg)`;});
     hero.addEventListener('mouseleave',()=>heroCard.style.transform='');
    }

    // Tandai slide carousel penyebab yang sedang aktif agar animasi kontennya berjalan
    const track=document.getElementById('causeTrack'),slides=$('.cause-slide',track);
    const setActive=()=>{const m=/-?([\d.]+)%/.exec(track.style.transform||'');const i=m?Math.round(parseFloat(m[1])/100):0;slides.forEach((s,k)=>s.classList.toggle('is-active',k===i));};
    new MutationObserver(setActive).observe(track,{attributes:true,attributeFilter:['style']});setActive();
    })();
    </script>

    <!-- Cek Status Sesi Admin: Ubah teks tombol "Login Admin" menjadi "Dashboard Admin" jika sudah login -->
    <script>
    fetch('api/auth.php?aksi=status',{credentials:'same-origin'}).then(r=>r.json()).then(d=>{
      if(d&&d.ok)document.querySelectorAll('.adm-link span:last-child').forEach(s=>{if(s.textContent.trim()==='Login Admin')s.textContent='Dashboard Admin'})
    }).catch(()=>{});
    </script>

    <!-- Notifikasi Toast Berhasil Log Out (Muncul dari Atas setelah dialihkan dari admin.php) -->
    <div id="logoutToast" role="status" aria-live="polite" class="fixed top-5 left-1/2 -translate-x-1/2 -translate-y-24 opacity-0 pointer-events-none z-[120] transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]">
        <div class="flex items-center gap-3 bg-charcoal-900/95 backdrop-blur-md text-white border border-emerald-500/40 shadow-[0_14px_34px_rgba(0,0,0,0.45)] px-5 py-3 rounded-full">
            <span class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xs shrink-0">
                <i class="fa-solid fa-check"></i>
            </span>
            <span class="font-sans text-xs sm:text-sm font-semibold tracking-wide">Berhasil log out</span>
        </div>
    </div>

    <script>
    (() => {
        // Baca tanda 'pw_logout_toast' dari sessionStorage; jika ada, tampilkan notifikasi selama 3,5 detik
        if (sessionStorage.getItem('pw_logout_toast') === '1') {
            sessionStorage.removeItem('pw_logout_toast');
            const toast = document.getElementById('logoutToast');
            if (toast) {
                requestAnimationFrame(() => {
                    toast.classList.remove('-translate-y-24', 'opacity-0', 'pointer-events-none');
                    toast.classList.add('translate-y-0', 'opacity-100');
                });
                setTimeout(() => {
                    toast.classList.remove('translate-y-0', 'opacity-100');
                    toast.classList.add('-translate-y-24', 'opacity-0', 'pointer-events-none');
                }, 3500);
            }
        }
    })();
    </script>
    <!-- Carousel Studi Kasus: 1 kasus = diam, 2+ kasus = geser otomatis -->
    <script>
    (function initStudiCarousel() {
        const root = document.getElementById('studiCarousel');
        const track = document.getElementById('studiTrack');
        if (!root || !track) return;
        const slides = Array.from(track.querySelectorAll('.studi-slide'));
        const total = slides.length;
        if (total < 2) return; // hanya 1 kasus: tetap diam, tanpa animasi geser

        const dotsBox = document.getElementById('studiDots');
        const prevBtn = document.getElementById('studiPrev');
        const nextBtn = document.getElementById('studiNext');
        const INTERVAL_MS = 6500;
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let idx = 0, timer = null, paused = false;

        function renderDots() {
            dotsBox.innerHTML = '';
            for (let i = 0; i < total; i++) {
                const d = document.createElement('button');
                d.type = 'button';
                d.setAttribute('aria-label', 'Tampilkan studi kasus ' + (i + 1));
                d.setAttribute('aria-selected', String(i === idx));
                d.className = i === idx
                    ? 'h-2.5 w-9 rounded-full bg-ember transition-all duration-300'
                    : 'h-2.5 w-2.5 rounded-full bg-slate-300 hover:bg-slate-400 transition-all duration-300';
                d.addEventListener('click', () => { go(i); restart(); });
                dotsBox.appendChild(d);
            }
        }
        function go(i) {
            idx = ((i % total) + total) % total;
            track.style.transform = 'translate3d(-' + (idx * 100) + '%,0,0)';
            slides.forEach((s, k) => {
                s.setAttribute('aria-hidden', String(k !== idx));
                if ('inert' in s) s.inert = (k !== idx); // slide tersembunyi tidak bisa difokus keyboard
            });
            renderDots();
        }
        function stop() { clearInterval(timer); timer = null; }
        function start() {
            stop();
            if (reduceMotion) return; // hormati preferensi "kurangi gerakan"
            timer = setInterval(() => { if (!paused && !document.hidden) go(idx + 1); }, INTERVAL_MS);
        }
        function restart() { start(); }

        prevBtn.addEventListener('click', () => { go(idx - 1); restart(); });
        nextBtn.addEventListener('click', () => { go(idx + 1); restart(); });
        root.addEventListener('mouseenter', () => { paused = true; });
        root.addEventListener('mouseleave', () => { paused = false; });
        root.addEventListener('focusin', () => { paused = true; });
        root.addEventListener('focusout', () => { paused = false; });
        root.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') { go(idx - 1); restart(); }
            else if (e.key === 'ArrowRight') { go(idx + 1); restart(); }
        });

        let touchX = 0;
        root.addEventListener('touchstart', (e) => { touchX = e.changedTouches[0].screenX; paused = true; }, { passive: true });
        root.addEventListener('touchend', (e) => {
            const dx = touchX - e.changedTouches[0].screenX;
            if (Math.abs(dx) > 40) go(dx > 0 ? idx + 1 : idx - 1);
            paused = false; restart();
        }, { passive: true });

        go(0);
        start();
    })();
    </script>
</body>
</html>

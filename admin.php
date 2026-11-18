<?php
// Halaman Admin PyroWatch ID (terhubung ke api/auth.php & api/admin.php)
// Mengatur header agar output dirender sebagai halaman HTML UTF-8
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin | PyroWatch ID</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* Variabel palet warna tema gelap PyroWatch */
:root{--bg:#14191d;--p:#1e252b;--p2:#262f37;--ln:rgba(255,255,255,.1);--tx:#f1f5f9;--mu:#94a3b8;--am:#f59e0b;--fl:#ea580c;--rd:#dc2626;--gn:#34d399}
*{box-sizing:border-box}[hidden]{display:none!important}
body{margin:0;font:400 14px/1.5 "Plus Jakarta Sans",system-ui,sans-serif;background:var(--bg);color:var(--tx)}
.mu{color:var(--mu)}

/* Styling form input dan tombol utama */
button,input,select,textarea{font:inherit;color:inherit}
input,select,textarea{width:100%;padding:9px 12px;border-radius:10px;background:#0f1418;border:1px solid var(--ln)}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--am);box-shadow:0 0 0 3px rgba(245,158,11,.18)}
.btn{border:none;border-radius:999px;padding:9px 18px;font-weight:700;cursor:pointer;color:#14191d;background:linear-gradient(135deg,var(--am),var(--fl));transition:transform .2s,box-shadow .2s}
.btn:hover{transform:translateY(-1px);box-shadow:0 8px 22px rgba(234,88,12,.35)}
.btn.g{background:rgba(255,255,255,.07);color:var(--tx);border:1px solid var(--ln)}
.brand{display:flex;align-items:center;gap:10px;font:700 18px "Space Grotesk",sans-serif;letter-spacing:.05em}
.brand i{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;background:linear-gradient(135deg,var(--am),var(--fl));color:#14191d}
.brand b{color:var(--am)}

/* Styling halaman Login Admin (Lapisan 1) */
#login{min-height:100vh;display:grid;place-items:center;padding:20px;background:radial-gradient(600px circle at 50% 0,rgba(245,158,11,.12),transparent 70%)}
.box{width:min(380px,100%);background:linear-gradient(155deg,#1e252b,#14191d);border:1px solid var(--ln);border-radius:20px;padding:28px;box-shadow:0 25px 60px rgba(0,0,0,.6);animation:up .6s cubic-bezier(.22,1,.36,1) both}
@keyframes up{from{opacity:0;transform:translateY(16px)}}
.box h1{font:700 1.3rem "Space Grotesk",sans-serif;margin:18px 0 4px}
.box label,#mf label{display:block;margin-top:12px;font-weight:600;font-size:.8rem}
.box .btn{width:100%;margin-top:18px;padding:11px}
#le{color:#fca5a5;font-size:.8rem;min-height:18px;margin-top:10px}

/* Styling Header & Layout Grid 2 Kolom Dashboard (Sidebar kiri 250px + Konten kanan) */
header{height:60px;display:flex;align-items:center;justify-content:space-between;padding:0 20px;background:rgba(30,37,43,.95);border-bottom:1px solid var(--ln);position:sticky;top:0;z-index:5}
header .r{display:flex;gap:10px;align-items:center}
header a{color:var(--mu);text-decoration:none;font-size:.82rem;font-weight:600}header a:hover{color:#fff}
.wrap{display:grid;grid-template-columns:250px 1fr;min-height:calc(100vh - 60px)}
aside{border-right:1px solid var(--ln);padding:14px 12px;background:#181f25}
aside h6{margin:16px 8px 6px;font-size:.66rem;letter-spacing:.14em;text-transform:uppercase;color:var(--mu)}
aside button{display:flex;align-items:center;gap:10px;width:100%;text-align:left;background:none;border:none;color:#cbd5e1;padding:9px 10px;border-radius:10px;cursor:pointer;font-weight:500}
aside button i{width:16px;text-align:center;color:var(--mu)}
aside button:hover{background:rgba(255,255,255,.06)}
aside button.on{background:rgba(245,158,11,.14);color:#fff}aside button.on i{color:var(--am)}
main{padding:22px;min-width:0}

/* Styling Toolbar Pencarian & Tabel Data CRUD */
.bar{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:16px}
.bar h2{margin:0;font:700 1.4rem "Space Grotesk",sans-serif}
.bar .a{display:flex;gap:10px}.bar input{width:220px}
.tw{overflow:auto;border:1px solid var(--ln);border-radius:14px;background:var(--p)}
table{border-collapse:collapse;width:100%;min-width:560px}
th{position:sticky;top:0;background:var(--p2);text-align:left;font-size:.68rem;text-transform:uppercase;letter-spacing:.08em;color:var(--mu);padding:11px 14px;white-space:nowrap}
td{padding:10px 14px;border-top:1px solid var(--ln);max-width:340px;vertical-align:top}
tbody tr{transition:background .15s}tbody tr:hover{background:rgba(255,255,255,.035)}
.kosong{text-align:center;color:var(--mu);padding:34px}
.chip{background:rgba(245,158,11,.16);color:#fcd34d;padding:2px 10px;border-radius:99px;font-size:.72rem;font-weight:700;white-space:nowrap}
.ac{white-space:nowrap;text-align:right}
.ic{width:32px;height:32px;border-radius:9px;border:1px solid var(--ln);background:rgba(255,255,255,.04);cursor:pointer;margin-left:6px;transition:all .2s}
.ic:hover{border-color:var(--am);color:var(--am)}.ic.dn:hover{border-color:var(--rd);color:#f87171}

/* Styling Modal Pop-up Form Tambah/Ubah & Notifikasi Toast */
#md{position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);display:grid;place-items:center;padding:16px;z-index:20}
.m{width:min(520px,100%);max-height:92vh;overflow:auto;background:linear-gradient(155deg,#1e252b,#14191d);border:1px solid var(--ln);border-radius:18px;padding:22px;animation:up .35s both}
.m h3{margin:0 0 4px;font:700 1.15rem "Space Grotesk",sans-serif;color:var(--am)}
#mf small{font-weight:400;color:var(--mu)}
.bt{display:flex;justify-content:flex-end;gap:10px;margin-top:18px}
#toast{position:fixed;left:50%;bottom:24px;transform:translate(-50%,20px);background:#14532d;border:1px solid #22c55e;color:#dcfce7;padding:10px 18px;border-radius:12px;opacity:0;pointer-events:none;transition:all .3s;z-index:30}
#toast.show{opacity:1;transform:translate(-50%,0)}#toast.err{background:#7f1d1d;border-color:#ef4444;color:#fee2e2}

/* Responsivitas layar mobile (< 820px): sidebar berubah menjadi menu bar horizontal di atas */
@media(max-width:820px){.wrap{grid-template-columns:1fr}aside{display:flex;overflow-x:auto;gap:4px;padding:8px;border-right:none;border-bottom:1px solid var(--ln)}aside h6{display:none}aside button{width:auto;white-space:nowrap}.bar input{width:100%}}
</style>
</head>
<body>

<!-- ===================================================================== -->
<!-- LAPISAN 1: FORM LOGIN ADMIN (Muncul jika belum memiliki sesi login)   -->
<!-- ===================================================================== -->
<div id="login" hidden>
  <form class="box" id="lf">
    <div class="brand"><i class="fa-solid fa-fire-flame-curved"></i><span>PYROWATCH<b>ID</b></span></div>
    <h1>Masuk Admin</h1><div class="mu">Kelola data database PyroWatch.</div>
    <label>Username<input id="lu" autocomplete="username" required></label>
    <label>Password<input id="lp" type="password" autocomplete="current-password" required></label>
    <div id="le" role="alert"></div>
    <button class="btn">Masuk</button>
  </form>
</div>

<!-- ===================================================================== -->
<!-- LAPISAN 2: DASHBOARD UTAMA ADMIN (Muncul setelah berhasil login)      -->
<!-- ===================================================================== -->
<div id="app" hidden>
  <header>
    <div class="brand"><i class="fa-solid fa-fire-flame-curved"></i><span>PYROWATCH<b>ID</b> <span class="mu" style="font-size:.7rem;letter-spacing:.14em">ADMIN</span></span></div>
    <div class="r"><a href="index.php">Beranda</a><a href="pyrowatch-v3-siap.html">Peta</a><button class="btn g" id="lo"><i class="fa-solid fa-right-from-bracket"></i> Keluar</button></div>
  </header>
  <div class="wrap">
    <!-- Sidebar kiri: daftar tabel database akan dirender otomatis oleh JS -->
    <aside id="nav"></aside>
    <!-- Area utama kanan: judul tabel, pencarian, tombol tambah, dan tabel data -->
    <main>
      <div class="bar">
        <div><h2 id="jd">…</h2><div class="mu" id="jm"></div></div>
        <div class="a"><input id="q" type="search" placeholder="Cari teks…" aria-label="Cari"><button class="btn" id="tm"><i class="fa-solid fa-plus"></i> Tambah</button></div>
      </div>
      <div class="tw"><table><thead id="th"></thead><tbody id="tb"></tbody></table></div>
    </main>
  </div>
</div>

<!-- ===================================================================== -->
<!-- LAPISAN 3: MODAL FORM TAMBAH/UBAH DATA & NOTIFIKASI TOAST             -->
<!-- ===================================================================== -->
<div id="md" hidden><div class="m"><h3 id="mt"></h3><form id="mf"></form></div></div>
<div id="toast" role="status"></div>

<!-- ===================================================================== -->
<!-- LOGIKA JAVASCRIPT SINGLE PAGE APPLICATION (SPA) CRUD ADMIN            -->
<!-- ===================================================================== -->
<script>
// Helper singkat untuk seleksi elemen DOM ($), sanitasi HTML anti-XSS (esc), dan penyimpanan state (S)
// S.t = nama tabel yang sedang dibuka | S.m = metadata kolom & relasi tabel aktif
const $=s=>document.querySelector(s),esc=s=>String(s??'').replace(/[&<>"]/g,c=>({'&':'&','<':'<','>':'>','"':'"'}[c]));
const S={t:null,m:null};

// Fungsi terpusat untuk mengirim request GET / POST JSON ke endpoint di folder api/
async function api(u,body){
  const o=body?{method:'POST',body:JSON.stringify(body)}:{};
  const r=await fetch('api/'+u,{credentials:'same-origin',headers:{'Content-Type':'application/json','X-Requested-With':'fetch'},...o});
  let d;try{d=await r.json()}catch(e){d={ok:false,error:'Server tidak merespons. Pastikan Apache & MySQL menyala dan halaman dibuka lewat http://localhost.'}}
  // Jika status HTTP 401 (sesi habis / belum login), otomatis kembalikan ke layar login
  if(r.status===401&&u.indexOf('auth.php')<0)tampil(false);
  return d;
}

// Menampilkan notifikasi toast sementara (2,8 detik) di bawah layar
function toast(m,err){const t=$('#toast');t.textContent=m;t.className='show'+(err?' err':'');clearTimeout(toast.x);toast.x=setTimeout(()=>t.className='',2800)}

// Mengatur pergantian visibilitas antara layar login (#login) dan dashboard (#app)
function tampil(ok){$('#login').hidden=ok;$('#app').hidden=!ok}

// Cek status login saat halaman pertama kali dibuka
async function init(){const d=await api('auth.php?aksi=status');tampil(!!d.ok);if(d.ok)muatTabel();else if(d.error)$('#le').textContent=d.error}

// Proses submit form login ke api/auth.php?aksi=login
$('#lf').onsubmit=async e=>{e.preventDefault();const d=await api('auth.php?aksi=login',{u:$('#lu').value,p:$('#lp').value});
  if(d.ok){$('#lp').value='';$('#le').textContent='';tampil(true);muatTabel()}else $('#le').textContent=d.error||'Gagal masuk'};

// Proses logout: panggil API logout, simpan flag notifikasi di sessionStorage, lalu arahkan ke index.php
$('#lo').onclick=async()=>{try{await api('auth.php?aksi=logout',{})}catch(e){}sessionStorage.setItem('pw_logout_toast','1');location.href='index.php'};

// Ambil daftar seluruh tabel dari backend lalu kelompokkan berdasarkan kategori ('grup') di sidebar
async function muatTabel(){
  const d=await api('admin.php?aksi=semua');if(!d.ok)return toast(d.error,1);
  const g={};d.tabel.forEach(x=>(g[x.grup]=g[x.grup]||[]).push(x));
  $('#nav').innerHTML=Object.entries(g).map(([k,v])=>`<h6>${esc(k)}</h6>`+v.map(x=>`<button data-t="${x.t}"><i class="fa-solid ${x.ic}"></i>${esc(x.label)}</button>`).join('')).join('');
  document.querySelectorAll('#nav button').forEach(b=>b.onclick=()=>{$('#q').value='';buka(b.dataset.t)});
  buka(S.t||d.tabel[0].t);
}

// Ambil isi data & struktur kolom dari tabel 't', lalu render ke dalam elemen <table>
async function buka(t){
  S.t=t;document.querySelectorAll('#nav button').forEach(b=>b.classList.toggle('on',b.dataset.t==t));
  const d=await api('admin.php?aksi=daftar&t='+t+'&q='+encodeURIComponent($('#q').value));if(!d.ok)return toast(d.error,1);
  S.m=d;$('#jd').textContent=d.label;$('#jm').textContent=d.total+' data'+(d.total>d.rows.length?` (menampilkan ${d.rows.length} pertama)`:'');
  // Helper untuk mengubah ID foreign key menjadi nama teks yang mudah dibaca
  const fkv=(c,v)=>{const o=(d.fk[c.n]||[]).find(x=>x.id==v);return o?o.nama:v};
  $('#th').innerHTML='<tr><th>ID</th>'+d.cols.map(c=>`<th>${esc(c.n.replace(/_/g,' '))}</th>`).join('')+'<th></th></tr>';
  $('#tb').innerHTML=d.rows.length?d.rows.map(r=>`<tr><td class="mu">${r.id}</td>`+d.cols.map(c=>{
      const s=String((c.type=='fk'?fkv(c,r[c.n]):r[c.n])??'');
      // Jika tipe kolom 'enum', tampilkan sebagai badge/chip; jika teks panjang (>80 huruf), potong dengan '…'
      return c.type=='enum'?`<td><span class="chip">${esc(s)}</span></td>`:`<td title="${esc(s)}">${s?esc(s.length>80?s.slice(0,80)+'…':s):'<span class="mu">–</span>'}</td>`
    }).join('')+`<td class="ac"><button class="ic" data-e="${r.id}" aria-label="Ubah"><i class="fa-solid fa-pen"></i></button><button class="ic dn" data-d="${r.id}" aria-label="Hapus"><i class="fa-solid fa-trash"></i></button></td></tr>`).join('')
    :`<tr><td colspan="${d.cols.length+2}" class="kosong">Belum ada data. Klik “Tambah” untuk membuat yang pertama.</td></tr>`;
  // Pasang event klik untuk setiap tombol Ubah ([data-e]) dan Hapus ([data-d])
  document.querySelectorAll('[data-e]').forEach(b=>b.onclick=()=>form(d.rows.find(r=>r.id==b.dataset.e)));
  document.querySelectorAll('[data-d]').forEach(b=>b.onclick=()=>hapus(+b.dataset.d));
}

// Membangun form Tambah (r = null) atau Ubah (r = objek baris) di dalam modal secara otomatis sesuai tipe kolom DB
function form(r){
  const d=S.m,baru=!r;$('#mt').textContent=(baru?'Tambah ':'Ubah ')+d.label;
  $('#mf').innerHTML=d.cols.map(c=>{
    const v=r?r[c.n]:'',lb=`<label>${esc(c.n.replace(/_/g,' '))}${c.null?' <small>(opsional)</small>':''}`;let f;
    if(c.type=='area')f=`<textarea name="${c.n}" rows="4">${esc(v)}</textarea>`;
    else if(c.type=='enum')f=`<select name="${c.n}">${c.opts.map(o=>`<option${o==v?' selected':''}>${esc(o)}</option>`).join('')}</select>`;
    else if(c.type=='fk')f=`<select name="${c.n}">${(d.fk[c.n]||[]).map(o=>`<option value="${o.id}"${o.id==v?' selected':''}>${esc(o.nama)}</option>`).join('')}</select>`;
    else f=`<input name="${c.n}" type="${c.type=='num'?'number':'text'}"${c.type=='num'?' step="any"':''} value="${esc(v)}">`;
    return lb+f+'</label>';
  }).join('')+'<div class="bt"><button type="button" class="btn g" id="bc">Batal</button><button class="btn">Simpan</button></div>';
  $('#md').hidden=false;$('#bc').onclick=()=>$('#md').hidden=true;
  // Proses simpan form ke api/admin.php?aksi=tambah atau aksi=ubah
  $('#mf').onsubmit=async e=>{e.preventDefault();const data={};d.cols.forEach(c=>data[c.n]=e.target.elements[c.n].value);
    const x=await api('admin.php?aksi='+(baru?'tambah':'ubah'),{t:S.t,id:r&&r.id,data});
    if(x.ok){$('#md').hidden=true;toast(baru?'Data ditambahkan':'Perubahan disimpan');buka(S.t)}else toast(x.error,1)};
  const f=$('#mf').querySelector('input,textarea,select');f&&f.focus();
}

// Menghapus baris data berdasarkan ID setelah konfirmasi pengguna
async function hapus(id){
  if(!confirm('Hapus data ini? Tindakan tidak bisa dibatalkan.'))return;
  const x=await api('admin.php?aksi=hapus',{t:S.t,id});
  if(x.ok){toast('Data dihapus');buka(S.t)}else toast(x.error,1);
}

// Event listener tombol Tambah, pencarian real-time (dengan jeda debounce 300ms), & tombol Escape untuk menutup modal
$('#tm').onclick=()=>form(null);
$('#q').addEventListener('input',()=>{clearTimeout(S.x);S.x=setTimeout(()=>buka(S.t),300)});
addEventListener('keydown',e=>{if(e.key=='Escape')$('#md').hidden=true});

// Jalankan inisialisasi halaman saat script selesai dimuat
init();
</script>
</body>
</html>
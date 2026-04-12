<?php
session_start();
include 'koneksi.php';

// cek apakah user sudah login
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

// ambil data user dari session
$user_id = $_SESSION['user_id'];
$nama_user = $_SESSION['nama'];

// ambil data booking
$bookings = [];
$q = mysqli_query($koneksi,"SELECT * FROM booking");
while($b=mysqli_fetch_assoc($q)){
    $bookings[]=$b;
}

$jam_ops = ["08:00","09:00","10:00","11:00","12:00","13:00","14:00","15:00","16:00","17:00","18:00"];

function isBooked($jam,$lapangan,$bookings){
    foreach($bookings as $b){
        if(
            $b['lapangan']==$lapangan &&
            $jam >= substr($b['jam_mulai'],0,5) &&
            $jam < substr($b['jam_selesai'],0,5)
        ){
            return true;
        }
    }
    return false;
}
?>

<!DOCTYPE html>
<html>
<head>
<script src="https://cdn.tailwindcss.com"></script>
<style>
body{background:#0a0a0a;color:white}
.card{background:#111;border:1px solid rgba(255,255,255,.05)}
.active{border:2px solid #ff2d2d;background:#1a0000}
.jam-aktif{background:#ff2d2d !important}
.metode-active{border:2px solid #ff2d2d;background:#1a0000}
.tipe-active{outline:2px solid white}
.btn{background:#ff2d2d}
</style>
</head>

<body class="p-6">

<h1 class="text-3xl font-bold text-red-500 mb-6">SMART FUTSAL</h1>

<div class="grid md:grid-cols-3 gap-6">

<!-- KIRI -->
<div class="md:col-span-2 space-y-6">

<!-- DATA -->
<div class="card p-5 rounded">
<input id="nama" placeholder="Nama Penyewa" class="w-full mb-2 p-3 bg-black border rounded">
<input id="wa" placeholder="Nomor WhatsApp" class="w-full p-3 bg-black border rounded">
</div>

<!-- LAPANGAN -->
<div class="card p-5 rounded">
<h2 class="mb-3">Pilih Lapangan</h2>
<div class="grid grid-cols-2 gap-3">

<div onclick="pilihLap(this,'Lapangan A',180000)" class="lap p-4 border rounded cursor-pointer">
<b>Lapangan A</b><br>Rumput<br><span class="text-red-500">180.000</span>
</div>

<div onclick="pilihLap(this,'Lapangan B',160000)" class="lap p-4 border rounded cursor-pointer">
<b>Lapangan B</b><br>Rumput<br><span class="text-red-500">160.000</span>
</div>

<div onclick="pilihLap(this,'Lapangan C',130000)" class="lap p-4 border rounded cursor-pointer">
<b>Lapangan C</b><br>Sintetis<br><span class="text-red-500">130.000</span>
</div>

<div onclick="pilihLap(this,'Lapangan D',100000)" class="lap p-4 border rounded cursor-pointer">
<b>Lapangan D</b><br>Sintetis<br><span class="text-red-500">100.000</span>
</div>

</div>
</div>

<!-- TANGGAL -->
<div class="card p-5 rounded">
<input type="date" id="tgl" class="w-full p-3 bg-black border rounded text-white [&::-webkit-calendar-picker-indicator]:invert">
</div>

<!-- JAM -->
<div class="card p-5 rounded">
<h2 class="mb-3">Pilih Jam</h2>

<div class="grid grid-cols-4 gap-2">
<div onclick="pilihJam(this,'08')" class="jam p-3 text-center rounded font-bold bg-green-500 cursor-pointer">08:00</div>
<div onclick="pilihJam(this,'09')" class="jam p-3 text-center rounded font-bold bg-green-500 cursor-pointer">09:00</div>
<div onclick="pilihJam(this,'10')" class="jam p-3 text-center rounded font-bold bg-green-500 cursor-pointer">10:00</div>
<div onclick="pilihJam(this,'11')" class="jam p-3 text-center rounded font-bold bg-green-500 cursor-pointer">11:00</div>
<div onclick="pilihJam(this,'12')" class="jam p-3 text-center rounded font-bold bg-green-500 cursor-pointer">12:00</div>
<div onclick="pilihJam(this,'13')" class="jam p-3 text-center rounded font-bold bg-green-500 cursor-pointer">13:00</div>
<div onclick="pilihJam(this,'14')" class="jam p-3 text-center rounded font-bold bg-green-500 cursor-pointer">14:00</div>
<div onclick="pilihJam(this,'15')" class="jam p-3 text-center rounded font-bold bg-green-500 cursor-pointer">15:00</div>
<div onclick="pilihJam(this,'16')" class="jam p-3 text-center rounded font-bold bg-green-500 cursor-pointer">16:00</div>
<div onclick="pilihJam(this,'17')" class="jam p-3 text-center rounded font-bold bg-green-500 cursor-pointer">17:00</div>
<div onclick="pilihJam(this,'18')" class="jam p-3 text-center rounded font-bold bg-green-500 cursor-pointer">18:00</div>
</div>

<!-- DURASI -->
<div id="durasiBox" class="mt-4 hidden">
<select id="durasi" onchange="update()" class="w-full p-3 bg-black border rounded">
<option value="">-- Pilih Durasi --</option>
<option value="1">1 Jam</option>
<option value="2">2 Jam</option>
<option value="3">3 Jam</option>
<option value="4">4 Jam</option>
<option value="5">5 Jam</option>
</select>
</div>

</div>

<!-- PEMBAYARAN -->
<div class="card p-5 rounded">
<h2 class="mb-3 text-lg">Pembayaran</h2>

<div class="flex gap-2 mb-4">
<button onclick="setTipe('Lunas',this)" class="tipe-btn bg-green-600 w-full p-3 rounded font-bold">Lunas</button>
<button onclick="setTipe('DP',this)" class="tipe-btn bg-yellow-500 w-full p-3 rounded font-bold">DP 50%</button>
</div>

<div class="grid grid-cols-2 gap-3 text-sm">
<div onclick="setMetode('GoPay',this)" class="metode p-4 bg-black rounded cursor-pointer">📱 GoPay<br><span class="text-xs text-gray-400">081234567890</span></div>
<div onclick="setMetode('OVO',this)" class="metode p-4 bg-black rounded cursor-pointer">💜 OVO<br><span class="text-xs text-gray-400">081234567890</span></div>
<div onclick="setMetode('DANA',this)" class="metode p-4 bg-black rounded cursor-pointer">💙 DANA<br><span class="text-xs text-gray-400">081234567890</span></div>
<div onclick="setMetode('BNI',this)" class="metode p-4 bg-black rounded cursor-pointer">🏦 BNI<br><span class="text-xs text-gray-400">1234567890 a.n Smart Futsal</span></div>
</div>

<!-- UPLOAD -->
<div class="mt-4">
<div onclick="document.getElementById('bukti').click()" class="border-2 border-dashed border-white/20 p-4 rounded text-center cursor-pointer">
<div id="uploadText">📸 Upload Bukti Transfer</div>
<div id="previewBox" class="hidden">
<img id="previewImg" class="mx-auto max-h-32 rounded">
<div id="fileName" class="text-xs text-green-400"></div>
</div>
</div>
<input type="file" id="bukti" hidden onchange="previewBukti(this)">
</div>

</div>

</div>

<!-- RINGKASAN -->
<div class="card p-5 rounded">
<h2 class="mb-3">Ringkasan</h2>
<div id="summary" class="text-sm space-y-2"></div>

<button onclick="konfirmasi()" class="btn w-full mt-4 py-3 rounded font-bold">
Konfirmasi Booking
</button>
</div>

</div>

<!-- NOTA -->
<div id="nota" class="hidden fixed inset-0 bg-black/80 flex items-center justify-center">
<div class="bg-white text-black p-6 rounded w-80">
<h2 class="font-bold mb-3">BUKTI BOOKING</h2>
<div id="notaIsi"></div>
<button onclick="selesaiBooking()" class="mt-3 bg-red-600 text-white w-full p-2 rounded">
Selesai
</button>
</div>
</div>

<script>
let lap="",harga=0,jam="",tipe="",metode="",buktiFile=null;

function pilihLap(el,n,h){
document.querySelectorAll('.lap').forEach(e=>e.classList.remove('active'));
el.classList.add('active');
lap=n;harga=h;update();
}

function pilihJam(el,j){
document.querySelectorAll('.jam').forEach(e=>e.classList.remove('jam-aktif'));
el.classList.add('jam-aktif');
jam=j;
document.getElementById("durasiBox").classList.remove("hidden");
update();
}

function setTipe(t,el){
tipe=t;
document.querySelectorAll('.tipe-btn').forEach(e=>e.classList.remove('tipe-active'));
el.classList.add('tipe-active');
update();
}

function setMetode(m,el){
metode=m;
document.querySelectorAll('.metode').forEach(e=>e.classList.remove('metode-active'));
el.classList.add('metode-active');
update();
}

function previewBukti(input){
const file=input.files[0];
if(!file)return;
if(file.size>5000000){alert("Max 5MB");return;}
buktiFile=file;

let reader=new FileReader();
reader.onload=function(e){
uploadText.classList.add("hidden");
previewBox.classList.remove("hidden");
previewImg.src=e.target.result;
fileName.innerText=file.name;
};
reader.readAsDataURL(file);
update();
}

function update(){
let dur=document.getElementById("durasi").value;
if(!lap||!jam||!dur)return;

let total=harga*dur;
let bayar=tipe=="DP"?total/2:total;
let selesai = parseInt(jam)+parseInt(dur);

summary.innerHTML=`
<div class="border-b border-white/10 pb-2 mb-2 font-bold text-red-400">
RINGKASAN BOOKING
</div>

<div class="grid grid-cols-2 gap-2 text-xs">
<div class="text-gray-400">Nama</div>
<div>: ${nama.value || '-'}</div>

<div class="text-gray-400">No WhatsApp</div>
<div>: ${wa.value || '-'}</div>

<div class="text-gray-400">Lapangan</div>
<div>: ${lap || '-'}</div>

<div class="text-gray-400">Tanggal</div>
<div>: ${tgl.value || '-'}</div>

<div class="text-gray-400">Jam Main</div>
<div>: ${jam}:00 - ${selesai}:00</div>

<div class="text-gray-400">Durasi</div>
<div>: ${dur} Jam</div>

<div class="text-gray-400">Total</div>
<div>: Rp ${total.toLocaleString()}</div>

<div class="text-gray-400">Tipe Bayar</div>
<div>: ${tipe || '-'}</div>

<div class="text-gray-400">Jumlah Dibayar</div>
<div>: Rp ${bayar.toLocaleString()}</div>

<div class="text-gray-400">Metode</div>
<div>: ${metode || '-'}</div>

${buktiFile?`
<div class="text-gray-400">Bukti</div>
<div>: <span class="text-green-400">${buktiFile.name}</span></div>
`:''}
</div>
`;
}

function konfirmasi(){
let dur=document.getElementById("durasi").value;

if(!nama.value || !wa.value || !lap || !jam || !dur || !metode){
alert("Lengkapi semua data dulu!");
return;
}

nota.classList.remove("hidden");

notaIsi.innerHTML=`
Nama: ${nama.value}<br>
WA: ${wa.value}<br>
Lapangan: ${lap}<br>
Tanggal: ${tgl.value}<br>
Jam: ${jam}:00 - ${parseInt(jam)+parseInt(dur)}:00<br>
Durasi: ${dur} jam<br>
Total: Rp ${(harga*dur).toLocaleString()}<br>
Metode: ${metode}<br>
${buktiFile?`Bukti: ${buktiFile.name}`:''}
`;
}

function selesaiBooking(){
alert("Booking berhasil!");
window.location.href = "riwayat.php";
}

</script>

</body>
</html>
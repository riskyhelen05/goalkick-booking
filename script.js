/* ===============================
   DATA
================================*/
let dataSewa = JSON.parse(localStorage.getItem("dataFutsal")) || [];
let editIndex = -1;
const hargaPerJam = 80000;

/* ===============================
   ELEMENT
================================*/
const formSewa = document.getElementById("formSewa");

const nama = document.getElementById("nama");
const wa = document.getElementById("wa");
const lapangan = document.getElementById("lapangan");
const tanggal = document.getElementById("tanggal");
const jamMulai = document.getElementById("jamMulai");
const jamSelesai = document.getElementById("jamSelesai");

const durasiText = document.getElementById("durasi");
const totalHarga = document.getElementById("totalHarga");

const tabelData = document.getElementById("tabelData");
const search = document.getElementById("search");
const jadwalHarian = document.getElementById("jadwalHarian");

const totalBooking = document.getElementById("totalBooking");
const totalPendapatan = document.getElementById("totalPendapatan");

const errNama = document.getElementById("errNama");
const errWa = document.getElementById("errWa");
const errLapangan = document.getElementById("errLapangan");
const errTanggal = document.getElementById("errTanggal");
const errJadwal = document.getElementById("errJadwal");

/* ===============================
   TOAST
================================*/
function showToast(pesan){

const toast=document.getElementById("toast");

toast.textContent=pesan;
toast.classList.remove("opacity-0");
toast.classList.add("opacity-100");

setTimeout(()=>{
toast.classList.remove("opacity-100");
toast.classList.add("opacity-0");
},2500);

}

/* ===============================
   BATAS TANGGAL
================================*/
tanggal.min=new Date().toISOString().split("T")[0];

/* ===============================
   WA ANGKA SAJA
================================*/
wa.addEventListener("input",()=>{
wa.value=wa.value.replace(/[^0-9]/g,'');
});

/* ===============================
   HITUNG DURASI + HARGA
================================*/
function hitungHarga(){

if(!jamMulai.value||!jamSelesai.value)return;

let m1=new Date("1970-01-01T"+jamMulai.value);
let m2=new Date("1970-01-01T"+jamSelesai.value);

let durasi=(m2-m1)/(1000*60*60);

if(durasi>0){

durasiText.textContent=durasi+" Jam";

let total=durasi*hargaPerJam;

totalHarga.textContent=
"Rp"+total.toLocaleString("id-ID");

}else{

durasiText.textContent="0 Jam";
totalHarga.textContent="Rp0";

}

}

jamMulai.onchange=hitungHarga;
jamSelesai.onchange=hitungHarga;

/* ===============================
   VALIDASI FORM
================================*/
function validasiForm(d){

document.querySelectorAll(".error")
.forEach(e=>e.textContent="");

let valid=true;

if(d.nama===""){
errNama.textContent="Nama wajib diisi";
valid=false;
}

if(d.wa===""){
errWa.textContent="Nomor WhatsApp wajib diisi";
valid=false;
}

if(d.lapangan===""){
errLapangan.textContent="Lapangan wajib dipilih";
valid=false;
}

if(d.tanggal===""){
errTanggal.textContent="Tanggal wajib diisi";
valid=false;
}

if(d.jamMulai===""||d.jamSelesai===""){
errJadwal.textContent="Jam mulai dan selesai wajib diisi";
valid=false;
}
else if(d.jamMulai>=d.jamSelesai){
errJadwal.textContent="Jam selesai harus lebih besar";
valid=false;
}

return valid;

}

/* ===============================
   CEK BENTROK
================================*/
function cekKetersediaan(d){

return !dataSewa.some((x,i)=>

i!==editIndex &&
x.lapangan===d.lapangan &&
x.tanggal===d.tanggal &&
d.jamMulai<x.jamSelesai &&
d.jamSelesai>x.jamMulai

);

}

/* ===============================
   SUBMIT BOOKING
================================*/
formSewa.addEventListener("submit",e=>{

e.preventDefault();

let data={

id:editIndex>-1
?dataSewa[editIndex].id
:"BK"+(dataSewa.length+1).toString().padStart(3,"0"),

nama:nama.value,
wa:wa.value,
lapangan:lapangan.value,
tanggal:tanggal.value,
jamMulai:jamMulai.value,
jamSelesai:jamSelesai.value

};

if(!validasiForm(data))return;

if(!cekKetersediaan(data)){
errJadwal.textContent="Slot sudah terisi!";
return;
}

/* EDIT */
if(editIndex>-1){

dataSewa[editIndex]=data;
editIndex=-1;

showToast("Data berhasil diperbarui ✏️");

}else{

dataSewa.push(data);
showToast("Booking berhasil ✅");

}

localStorage.setItem("dataFutsal",JSON.stringify(dataSewa));

formSewa.reset();
totalHarga.textContent="Rp0";
durasiText.textContent="0 Jam";

tampilData();
tampilJadwalHariIni();
hitungStatistik();
tampilSlot();

});

/* ===============================
   TAMPIL DATA
================================*/
function tampilData(filter=""){

tabelData.innerHTML="";

dataSewa
.filter(d=>d.nama.toLowerCase().includes(filter.toLowerCase()))

.forEach((d,index)=>{

let m1=new Date("1970-01-01T"+d.jamMulai);
let m2=new Date("1970-01-01T"+d.jamSelesai);

let durasi=(m2-m1)/(1000*60*60);

let total=durasi*hargaPerJam;

tabelData.innerHTML+=`
<tr>
<td>${d.id}</td>
<td>${d.nama}</td>
<td>${d.wa}</td>
<td>${d.lapangan}</td>
<td>${d.tanggal}</td>
<td>${d.jamMulai}-${d.jamSelesai}</td>

<td class="font-bold text-yellow-300">
⏱ ${durasi} Jam
</td>

<td><span class="bg-green-500 text-white px-2 py-1 rounded text-xs">
Tersedia
</span></td>

<td>Rp${total.toLocaleString("id-ID")}</td>

<td>

<div class="flex gap-2 justify-center">

<button onclick="editData(${index})"
class="bg-blue-500 hover:bg-blue-600 hover:scale-105 transition text-white px-3 py-1 rounded text-sm">
Edit
</button>

<button onclick="hapusData(${index})"
class="bg-red-500 hover:bg-red-600 hover:scale-105 transition text-white px-3 py-1 rounded text-sm">
Hapus
</button>

</div>

</td>

</tr>

`;

});

}

search.onkeyup=()=>tampilData(search.value);

/* ===============================
   EDIT
================================*/
function editData(index){

let d=dataSewa[index];

nama.value=d.nama;
wa.value=d.wa;
lapangan.value=d.lapangan;
tanggal.value=d.tanggal;
jamMulai.value=d.jamMulai;
jamSelesai.value=d.jamSelesai;

editIndex=index;

hitungHarga();

window.scrollTo({top:0,behavior:"smooth"});

}

/* ===============================
   HAPUS
================================*/
function hapusData(index){

if(confirm("Yakin ingin menghapus booking ini?")){

dataSewa.splice(index,1);

localStorage.setItem("dataFutsal",JSON.stringify(dataSewa));

tampilData();
tampilJadwalHariIni();
hitungStatistik();
tampilSlot();

showToast("Data dihapus 🗑️");

}

}

/* ===============================
   JADWAL HARI INI
================================*/
function tampilJadwalHariIni(){

let today=new Date().toISOString().split("T")[0];

jadwalHarian.innerHTML="";

dataSewa
.filter(d=>d.tanggal===today)

.forEach(d=>{

jadwalHarian.innerHTML+=`

<div class="bg-white/20 backdrop-blur-xl border border-white/30 p-5 rounded-xl text-center shadow">

<h3 class="text-lg font-bold">
Lapangan ${d.lapangan}
</h3>

<p>${d.nama}</p>

<p class="text-yellow-300 font-semibold">
${d.jamMulai} - ${d.jamSelesai}
</p>

</div>

`;

});

}

/* ===============================
   STATISTIK
================================*/
function hitungStatistik(){

let today=new Date().toISOString().split("T")[0];

let total=0;
let jumlah=0;

dataSewa.forEach(d=>{

if(d.tanggal===today){

let m1=new Date("1970-01-01T"+d.jamMulai);
let m2=new Date("1970-01-01T"+d.jamSelesai);

total+=((m2-m1)/(1000*60*60))*hargaPerJam;
jumlah++;

}

});

totalBooking.textContent=jumlah;

totalPendapatan.textContent=
"Rp"+total.toLocaleString("id-ID");

}

/* ===============================
   SLOT LAPANGAN
================================*/
function tampilSlot(){

const slotLapangan=document.getElementById("slotLapangan");

const jamList=[
"08:00","09:00","10:00","11:00",
"12:00","13:00","14:00","15:00",
"16:00","17:00","18:00","19:00",
"20:00","21:00","22:00","23:00"
];

slotLapangan.innerHTML="";

jamList.forEach(jam=>{

let booked=dataSewa.some(d=>{

return(
d.lapangan===lapangan.value &&
d.tanggal===tanggal.value &&
jam>=d.jamMulai &&
jam<d.jamSelesai
);

});

slotLapangan.innerHTML+=`

<div class="${
booked
?"bg-red-500"
:"bg-green-500"
} text-white text-center p-3 rounded-lg font-semibold hover:scale-105 transition">

${jam}

</div>

`;

});

}

lapangan.addEventListener("change",tampilSlot);
tanggal.addEventListener("change",tampilSlot);

/* ===============================
   RESET
================================*/
function resetData(){

if(confirm("Hapus semua data?")){
localStorage.clear();
location.reload();
}

}

/* ===============================
   INIT
================================*/
tampilData();
tampilJadwalHariIni();
hitungStatistik();
tampilSlot();
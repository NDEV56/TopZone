<?php
session_start();
include 'koneksi.php'; 
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pembayaran Top Up</title>

<style>
*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:Arial,sans-serif;
}

body{
background:#eef2fa;
padding:25px;
color:#111827;
}

.container{
max-width:1400px;
margin:auto;
display:grid;
grid-template-columns:2fr 1fr;
gap:22px;
}

.card{
background:#fff;
border:1px solid #dbe1ea;
border-radius:8px;
padding:22px;
margin-bottom:18px;
}

h2{
font-size:30px;
margin-bottom:18px;
}

.row{
display:flex;
justify-content:space-between;
gap:20px;
}

.product{
display:flex;
gap:15px;
}

.logo{
width:70px;
height:70px;
background:#111;
color:#fff;
display:flex;
justify-content:center;
align-items:center;
border-radius:8px;
font-weight:bold;
}

.name{
font-size:25px;
font-weight:bold;
}

.sub{
font-size:17px;
color:#6b7280;
margin-top:5px;
}

.right-price{
font-size:28px;
font-weight:bold;
}

.line{
height:1px;
background:#e5e7eb;
margin:18px 0;
}

.option{
margin-bottom:15px;
}

.option label{
font-size:22px;
font-weight:bold;
cursor:pointer;
}

.check{
width:22px;
height:22px;
margin-right:10px;
cursor:pointer;
}

.wallet,
.methods{
display:grid;
grid-template-columns:1fr 1fr;
gap:15px;
}

.wallet-box,
.method{
border:1px solid #ddd;
padding:15px;
border-radius:8px;
cursor:pointer;
transition:0.2s;
font-size:22px;
font-weight:bold;
text-align:center;
}

.wallet-box:hover,
.method:hover{
border-color:#2563eb;
}

.wallet-box.active,
.method.active{
border:2px solid #2563eb;
background:#eff6ff;
}

.summary-item{
display:flex;
justify-content:space-between;
font-size:22px;
margin-bottom:14px;
}

.total{
border-top:1px solid #ddd;
padding-top:15px;
font-size:28px;
font-weight:bold;
}

button{
width:100%;
padding:15px;
background:#ff6a00;
color:white;
border:none;
border-radius:8px;
font-size:24px;
font-weight:bold;
cursor:pointer;
margin-top:15px;
}

button:hover{
background:#e55d00;
}

@media(max-width:900px){
.container{
grid-template-columns:1fr;
}
}
</style>
</head>

<body>

<div class="container">

<div>

<div class="card">
<h2>Informasi Pesanan</h2>

<div class="row">
<div class="product">
<div class="logo">FF</div>

<div>
<div class="name">140 Diamonds</div>
<div class="sub">Garena Free Fire</div>
<div class="sub">Player ID: 7201307495</div>
</div>
</div>

<div class="right-price">Rp 18.523</div>
</div>

<div class="line"></div>

<div class="option">
<input type="checkbox" id="jaminan" class="check" onchange="hitung()">
<label for="jaminan">Jaminan Anti Telat (+600)</label>
</div>

<div class="option">
<input type="checkbox" id="premium" class="check" checked onchange="hitung()">
<label for="premium">Layanan Premium (+5000)</label>
</div>

</div>

<div class="card">
<h2>Dompet & Koin</h2>

<div class="wallet">

<div class="wallet-box" onclick="toggleWallet(this,'dompet')">
💼 Dompetku
</div>

<div class="wallet-box" onclick="toggleWallet(this,'koin')">
🪙 Koinku
</div>

</div>
</div>

<div class="card">
<h2>Metode Pembayaran</h2>

<div class="methods">

<div class="method active" onclick="pilih(this,'DANA',266)">
DANA
</div>

<div class="method" onclick="pilih(this,'QRIS',170)">
QRIS
</div>

<div class="method" onclick="pilih(this,'GoPay',483)">
GoPay
</div>

<div class="method" onclick="pilih(this,'Transfer Bank',500)">
Transfer Bank
</div>

</div>

</div>

</div>


<div>

<div class="card">

<h2>Detail Pembayaran</h2>

<div class="summary-item">
<span>Metode</span>
<span id="metode">DANA</span>
</div>

<div class="summary-item">
<span>Harga Produk</span>
<span>Rp 18.523</span>
</div>

<div class="summary-item">
<span>Tambahan</span>
<span id="tambahan">Rp 5.266</span>
</div>

<div class="summary-item">
<span>Potongan</span>
<span id="potongan">Rp 0</span>
</div>

<div class="summary-item total">
<span>Total</span>
<span id="total">Rp 23.789</span>
</div>

<button onclick="bayar()">Bayar</button>

</div>

</div>

</div>

<script>
let harga = 18523;
let admin = 266;
let metode = "DANA";
let dompet = false;
let koin = false;

function rupiah(x){
return "Rp " + x.toLocaleString("id-ID");
}

function pilih(el,nama,biaya){
document.querySelectorAll(".method").forEach(item=>{
item.classList.remove("active");
});

el.classList.add("active");
metode = nama;
admin = biaya;

document.getElementById("metode").innerText = nama;

hitung();
}

function toggleWallet(el,jenis){
el.classList.toggle("active");

if(jenis === "dompet"){
dompet = !dompet;
}

if(jenis === "koin"){
koin = !koin;
}

hitung();
}

function hitung(){
let premium = document.getElementById("premium").checked ? 5000 : 0;
let jaminan = document.getElementById("jaminan").checked ? 600 : 0;

let tambahan = premium + jaminan + admin;

let potongan = 0;

if(dompet) potongan += 2000;
if(koin) potongan += 1000;

let total = harga + tambahan - potongan;

document.getElementById("tambahan").innerText = rupiah(tambahan);
document.getElementById("potongan").innerText = rupiah(potongan);
document.getElementById("total").innerText = rupiah(total);
}

function bayar(){
alert(
"✅ Pembayaran Berhasil\n\n" +
"Metode: " + metode + "\n" +
"Total: " + document.getElementById("total").innerText
);
}

hitung();
</script>

</body>
</html>
itu fi kodingannya
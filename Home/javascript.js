/* ===== GLOBAL VARIABLES ===== */
let kategoriAktif = "";
let sliderIndex = 0;

/* ===== CORE FUNCTIONS (SEARCH & FILTER) ===== */
function loadData() {
    const searchInput = document.getElementById("searchInput");
    const slider = document.getElementById("sliderWrap");
    const productList = document.getElementById("productList");
    const notFound = document.getElementById("notFound");
    const mainTitle = document.getElementById("mainTitle");

    const search = searchInput ? searchInput.value.trim() : ""; 

    if (search.length > 0 || kategoriAktif !== "") {
        if (slider) slider.style.display = "none";
        if (mainTitle) mainTitle.innerText = search.length > 0 ? `🔍 Hasil: "${search}"` : `📂 Kategori: ${kategoriAktif}`;
    } else {
        if (slider) slider.style.display = "flex";
        if (mainTitle) mainTitle.innerText = "🔥 Semua Produk";
    }

    fetch(`search.php?search=${encodeURIComponent(search)}&kategori=${encodeURIComponent(kategoriAktif)}`)
    .then(res => res.text())
    .then(data => {
        const cleanData = data.trim();
        if (cleanData === "") {
            if (productList) { productList.innerHTML = ""; productList.style.display = "none"; }
            if (notFound) notFound.style.display = "block";
        } else {
            if (productList) { productList.innerHTML = cleanData; productList.style.display = "grid"; }
            if (notFound) notFound.style.display = "none";
        }
    })
    .catch(err => console.error("Error:", err));
}

function searchRealtime() { loadData(); }

function filterKategori(kat, el) {
    kategoriAktif = kat;
    const allItems = document.querySelectorAll(".tp-sidebar li");
    allItems.forEach(li => li.classList.remove("active"));
    if (el) el.classList.add("active");
    loadData();
}

/* ===== LOGIN SYSTEM CHECK ===== */
function checkLogin() {
    // Kita cek apakah ada data user di localStorage (setelah login sukses)
    const user = localStorage.getItem("user");
    if (!user || user === "undefined" || user === "Guest") {
        alert("Paok! Login dulu mprruy sebelum belanja!");
        window.location.href = "login.php"; // Arahkan ke file login lu
        return false;
    }
    return true;
}


function hapusSemua() {/* ===== GLOBAL VARIABLES ===== */
let kategoriAktif = "";
let sliderIndex = 0;

/* ===== CORE FUNCTIONS (SEARCH & FILTER) ===== */
function loadData() {
    const searchInput = document.getElementById("searchInput");
    const slider = document.getElementById("sliderWrap");
    const productList = document.getElementById("productList");
    const notFound = document.getElementById("notFound");
    const mainTitle = document.getElementById("mainTitle");

    const search = searchInput ? searchInput.value.trim() : ""; 

    if (search.length > 0 || kategoriAktif !== "") {
        if (slider) slider.style.display = "none";
        if (mainTitle) mainTitle.innerText = search.length > 0 ? `🔍 Hasil: "${search}"` : `📂 Kategori: ${kategoriAktif}`;
    } else {
        if (slider) slider.style.display = "flex";
        if (mainTitle) mainTitle.innerText = "🔥 Semua Produk";
    }

    fetch(`search.php?search=${encodeURIComponent(search)}&kategori=${encodeURIComponent(kategoriAktif)}`)
    .then(res => res.text())
    .then(data => {
        const cleanData = data.trim();
        if (cleanData === "") {
            if (productList) { productList.innerHTML = ""; productList.style.display = "none"; }
            if (notFound) notFound.style.display = "block";
        } else {
            if (productList) { productList.innerHTML = cleanData; productList.style.display = "grid"; }
            if (notFound) notFound.style.display = "none";
        }
    })
    .catch(err => console.error("Error:", err));
}

function searchRealtime() { loadData(); }

function filterKategori(kat, el) {
    kategoriAktif = kat;
    const allItems = document.querySelectorAll(".tp-sidebar li");
    allItems.forEach(li => li.classList.remove("active"));
    if (el) el.classList.add("active");
    loadData();
}

/* ===== LOGIN SYSTEM CHECK ===== */
function checkLogin() {
    // Cek session via PHP/server side lebih aman, tapi ini filter dasar JS
    const isGuest = document.querySelector('.btn-login-nav'); 
    if (isGuest) {
        alert("Paok! Login dulu mprruy sebelum belanja!");
        window.location.href = "../Login/tampilanlogin.php";
        return false;
    }
    return true;
}

/* ===== CART LOGIC (GACOR VERSION) ===== */
// Fungsi Refresh Keranjang dari DB
function updateCartDisplay() {
    fetch('ambil_keranjang_db.php')
    .then(res => res.json())
    .then(data => {
        const cartCount = document.getElementById("cartCount");
        const listContainer = document.getElementById("cartItemsList");

        if (cartCount) cartCount.innerText = data.length; // Update angka di icon

        if (listContainer) {
            if (data.length === 0) {
                listContainer.innerHTML = "<p style='padding:10px; text-align:center;'>Kosong mprruy!</p>";
            } else {
                listContainer.innerHTML = data.map(item => `
                    <div style="border-bottom:1px solid #eee; padding:10px 0; display:flex; justify-content:space-between; align-items:center;">
                        <div style="font-size:12px;">
                            <b>${item.nama_produk}</b><br>
                            <span style="color:red">Rp ${parseInt(item.harga).toLocaleString('id-ID')}</span>
                        </div>
                        <button onclick="hapusItemDB(${item.id_keranjang})" style="color:red; border:none; background:none; cursor:pointer; font-size:16px;">❌</button>
                    </div>
                `).join('');
            }
        }
    });
}

// Fungsi Hapus Permanen dari MySQL
function hapusItemDB(id) {
    if(!confirm("Yakin mau hapus item ini mprruy?")) return;

    fetch(`hapus_keranjang_db.php?id=${id}`)
    .then(res => res.json())
    .then(data => {
        if(data.status === 'sukses') {
            updateCartDisplay();
        } else {
            alert("Gagal hapus: " + data.pesan);
        }
    });
}

// Pastikan fungsi ini dipanggil saat user klik paket (misal: 80 Robux)
let selectedProduct = "";

function selectPackage(name, price) {
    selectedPrice = price;
    selectedProduct = name;
    // Update tampilan Total Bayar di UI
    document.getElementById('displayTotal').innerText = "Rp " + price.toLocaleString();
}
// Variable global yang sudah ada di kode lo
let selectedPrice = 0;
let currentQty = 1;
let selectedProductName = "";

// ... fungsi switchTab, selectProduct, dll tetap biarin ...

function tambahKeKeranjang() {
    // 1. Cek Login
    if (!checkLogin()) {
        alert("Login dulu mprruy!");
        window.location.href = "../Login/tampilanlogin.php";
        return;
    }

    // 2. CEK APAKAH PAKET SUDAH DIPILIH
    if (selectedPrice === 0 || selectedProductName === "") {
        alert("Pilih dulu paket itemnya mprruy, jangan buru-buru!");
        return; // Berhenti di sini, gak bakal lanjut kirim data
    }

    // 3. Kalau sudah pilih paket, baru kirim ke database
    let dataKeKeranjang = new FormData();
    dataKeKeranjang.append('id_game', '<?php echo $id_game; ?>');
    dataKeKeranjang.append('nama_paket', selectedProductName);
    dataKeKeranjang.append('harga', selectedPrice);
    dataKeKeranjang.append('qty', currentQty);

    fetch('proses_keranjang.php', {
        method: 'POST',
        body: dataKeKeranjang
    })
    .then(res => res.text())
    .then(hasil => {
        alert("Mantap! " + selectedProductName + " masuk keranjang 🔥");
        location.reload(); // Biar angka di ikon keranjang langsung update
    });
}
// --- TARUH INI DI PALING BAWAH FILE JAVASCRIPT.JS ---

function toggleCartModal() {
    // Sekarang pakai variabel global yang kita oper tadi
    if (!IS_REAL_USER) {
        alert("Eits! Guest gak punya keranjang. Login dulu mprruy!");
        // Arahin ke folder Login lo
        window.location.href = "Login/tampilanlogin.php"; 
        return;
    }

    // ... sisa kode buka keranjang lo ...
    const dropdown = document.getElementById("cartDropdown");
    if(dropdown) {
        dropdown.style.display = (dropdown.style.display === "none") ? "block" : "none";
    }
}

/* ===== UI UIX (TOAST) ===== */
function showSuccessToast() {
    const toast = document.getElementById("toastSuccess");
    if(!toast) return;
    toast.style.display = "block";
    toast.classList.remove("toast-fade-out");
    setTimeout(() => {
        toast.classList.add("toast-fade-out");
        setTimeout(() => { toast.style.display = "none"; }, 500);
    }, 3000);
}

/* ===== PROFILE PHOTO AJAX ===== */
function initPhotoUpload() {
    const input_foto = document.getElementById('input_foto'); // Sesuaikan ID dengan index.php lo
    if (input_foto) {
        input_foto.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const formData = new FormData();
                formData.append('foto_profil', file);

                fetch('update_foto_profil_ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success' || data.status === 'sukses') {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            // Update semua elemen foto di page
                            if(document.getElementById('prev_foto')) document.getElementById('prev_foto').src = e.target.result;
                            if(document.getElementById('prev_foto_navbar')) document.getElementById('prev_foto_navbar').src = e.target.result;
                        }
                        reader.readAsDataURL(file);
                        alert("Foto profil berhasil diganti mprruy! 🔥");
                    } else {
                        alert(data.pesan);
                    }
                })
                .catch(err => console.error("Error mprruy:", err));
            }
        });
    }
}

/* ===== INITIALIZE ON LOAD ===== */
document.addEventListener("DOMContentLoaded", () => {
    loadData();
    updateCartDisplay();
    initPhotoUpload();

    // Slider Logic
    const track = document.getElementById("sliderTrack");
    const slides = document.querySelectorAll(".tp-slide");
    if (track && slides.length > 0) {
        setInterval(() => {
            let sliderWrap = document.getElementById("sliderWrap");
            if (sliderWrap && sliderWrap.style.display !== "none") {
                sliderIndex = (sliderIndex + 1) % slides.length;
                track.style.transform = `translateX(-${sliderIndex * 100}%)`;
            }
        }, 3000);
    }
});

// Tutup dropdown kalau klik di luar
window.onclick = function(event) {
    const dropdown = document.getElementById("cartDropdown");
    const cartIcon = document.querySelector(".tp-cart");
    if (dropdown && cartIcon && !cartIcon.contains(event.target)) {
        dropdown.style.display = "none";
    }
}
    if(confirm("Yakin mau dikosongin mprruy?")) {
        localStorage.removeItem("topzone_cart");
        updateCartDisplay();
        toggleCartModal();
    }
}

/* ===== INITIALIZE ===== */
document.addEventListener("DOMContentLoaded", () => {
    loadData();
    updateCartDisplay();
    // Logic slider
    const track = document.getElementById("sliderTrack");
    const slides = document.querySelectorAll(".tp-slide");
    if (track && slides.length > 0) {
        setInterval(() => {
            let sliderWrap = document.getElementById("sliderWrap");
            if (sliderWrap && sliderWrap.style.display !== "none") {
                sliderIndex = (sliderIndex + 1) % slides.length;
                track.style.transform = `translateX(-${sliderIndex * 100}%)`;
            }
        }, 3000);
    }
});

// Klik di luar buat tutup keranjang
window.onclick = function(event) {
    const dropdown = document.getElementById("cartDropdown");
    const cartIcon = document.querySelector(".tp-cart");
    if (dropdown && cartIcon && !cartIcon.contains(event.target)) {
        dropdown.style.display = "none";
    }
}

// --- LOGIKA GANTI FOTO PROFIL INSTAN mprruy ---

const inputFoto = document.getElementById('input_foto');
const prevFoto = document.getElementById('prev_foto');
const fotoBase64 = document.getElementById('foto_base64');

if (inputFoto) {
    inputFoto.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    // Paksa crop jadi kotak (Square) biar pas di lingkaran
                    const canvas = document.createElement('canvas');
                    const size = Math.min(img.width, img.height);
                    canvas.width = 500; // Resolusi hasil simpan
                    canvas.height = 500;
                    const ctx = canvas.getContext('2d');

                    // Ambil bagian tengah foto (Logic Auto-Pasin)
                    ctx.drawImage(img, (img.width - size) / 2, (img.height - size) / 2, size, size, 0, 0, 500, 500);

                    const finalData = canvas.toDataURL('image/png');
                    prevFoto.src = finalData; // Update tampilan sidebar
                    fotoBase64.value = finalData; // Masukin ke input buat dikirim ke PHP
                };
                img.src = e.target.result;
            }
            reader.readAsDataURL(file);
        }
    });
}

// Ganti baris 416 lo jadi begini:
const inputFoto = document.getElementById('input_ganti_foto');

if (inputFoto) { 
    inputFoto.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const formData = new FormData();
            formData.append('foto_profil', file);

            fetch('update_foto_profil_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success' || data.status === 'sukses') {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // Pakai tanda tanya (?) atau cek if lagi biar gak error kalau id ini gak ada
                        if(document.getElementById('prev_foto_navbar')) document.getElementById('prev_foto_navbar').src = e.target.result;
                        if(document.getElementById('prev_foto_besar')) document.getElementById('prev_foto_besar').src = e.target.result;
                    }
                    reader.readAsDataURL(file);
                    alert("Foto profil berhasil diganti mprruy! 🔥");
                } else {
                    alert(data.pesan);
                }
            })
            .catch(err => console.error("Error mprruy:", err));
        }
    });
}

// Fungsi buka tutup modal keranjang
function openCartModal() {
    document.getElementById("modalKeranjang").style.display = "block";
    // Lo bisa pake fetch buat ambil data keranjang terbaru di sini
}

function closeCartModal() {
    document.getElementById("modalKeranjang").style.display = "none";
}

// LOGIKA PILIH PAKET (Di Detail Game)
let paketTerpilih = null;

function pilihItem(nama, harga) {
    paketTerpilih = { nama, harga };
    
    // Aktifkan visual tombol beli
    const btn = document.getElementById('btnTambahKeranjang');
    if(btn) {
        btn.style.opacity = "1";
        btn.innerHTML = "➕ Tambah " + nama + " ke Keranjang";
    }
}

function eksekusiTambah() {
    if (!IS_REAL_USER) {
        alert("Login dulu mprruy!");
        return;
    }
    
    if (!paketTerpilih) {
        alert("Woy mprruy! Pilih dulu paketnya baru klik keranjang! 😅");
        return;
    }

    // Kalau aman, kirim data via Fetch ke proses_keranjang.php
    // Persis kayak yang gue ajarin sebelumnya
}
function toggleCartSidebar() {
    const cartSidebar = document.getElementById("cartSidebar");
    const overlay = document.getElementById("panelOverlay");
    
    // Tutup sidebar profil dulu biar gak tumpang tindih
    document.getElementById("profileSidebar").classList.remove("active");
    
    cartSidebar.classList.toggle("active");
    overlay.style.display = cartSidebar.classList.contains("active") ? "block" : "none";
}

// Update fungsi toggleProfileSidebar biar nutup keranjang juga
function toggleProfileSidebar() {
    const sidebar = document.getElementById("profileSidebar");
    const cartSidebar = document.getElementById("cartSidebar"); // Tambahin ini
    const overlay = document.getElementById("panelOverlay");
    
    cartSidebar.classList.remove("active"); // Nutup keranjang kalau buka profil
    sidebar.classList.toggle("active");
    overlay.style.display = sidebar.classList.contains("active") ? "block" : "none";
}

// --- TARUH DI PALING BAWAH FILE JAVASCRIPT.JS ---

/* ===== CART LOGIC (GACOR VERSION) ===== */

/* --- 1. Fungsi Utama Ambil Data --- */
function updateCartDisplay() {
    fetch('ambil_keranjang_db.php')
    .then(res => res.json())
    .then(data => {
        const listContainer = document.getElementById("cartItemsList");
        if (!listContainer) return;

        if (data.length === 0) {
            listContainer.innerHTML = "<p style='text-align:center; padding:20px;'>Keranjang kosong!</p>";
            return;
        }

        // Loop data buat bikin HTML kartu produk
        let cardsHTML = data.map(item => {
            // Karena file ada di folder Home (satu level ama index.php), pake "./"
            let pathFoto = "./" + item.gambar; 
            let namaFile = item.gambar; // Ini sekarang isinya "FF.jpg" atau "Roblox.jpg"


                return `
                <div class="cart-card">
                    <input type="checkbox" class="cart-checkbox" value="${item.id_keranjang}">
                    
                    <img src="${pathFoto}" 
                        onerror="this.src='./Default.jpg'" 
                        style="width:55px; height:55px; border-radius:8px; object-fit:cover;">
                    
                    <div style="flex:1;">
                        <b>${item.nama_produk}</b>
                        <span style="color:#03ac0e;">Rp ${parseInt(item.harga).toLocaleString('id-ID')}</span>
                    
                    <div style="display:flex; justify-content:space-between; margin-top:5px;">
                        <div class="qty-control" style="display:flex; align-items:center; border:1px solid #ddd; border-radius:10px;">
                            <button onclick="ubahQty(${item.id_keranjang}, -1)" style="border:none; background:none; padding:0 8px;">-</button>
                            <span id="qty-${item.id_keranjang}" style="font-size:11px;">${item.qty}</span>
                            <button onclick="ubahQty(${item.id_keranjang}, 1)" style="border:none; background:none; padding:0 8px;">+</button>
                        </div>
                        <button onclick="hapusItemDB(${item.id_keranjang})" style="border:none; background:none; color:red; cursor:pointer;">🗑️</button>
                    </div>
                </div>
            </div>`;
        }).join(''); // Gabungin array jadi string

        // Render ke HTML: Kartu Produk + Tombol Bayar
        listContainer.innerHTML = cardsHTML + `
            <div style="padding:15px; border-top:2px solid #eee; background:#fff;">
                <div style="display:flex; justify-content:space-between; margin-bottom:10px; font-weight:bold;">
                    <span>Total Tagihan:</span>
                    <span id="totalHargaCart" style="color:#03ac0e;">Rp 0</span>
                </div>
                <button onclick="prosesCheckout()" style="width:100%; background:#03ac0e; color:white; border:none; padding:12px; border-radius:8px; font-weight:bold; cursor:pointer;">
                    BAYAR SEKARANG
                </button>
            </div>`;

        // Tambahkan event listener buat mantau checklist
        tambahEventChecklist();
            })
    .catch(err => console.error("Sidebar Error:", err));
}

// Panggil fungsi pas halaman selesai dimuat
document.addEventListener("DOMContentLoaded", updateCartDisplay);
/* ===== FUNGSI HAPUS PERMANEN mprruy ===== */
function hapusItemDB(id) {
    // Alert konfirmasi biar gak salah hapus
    if(!confirm("Yakin mau buang item ini mprruy? (Nanti nyesel!)")) return;

    fetch(`hapus_keranjang_db.php?id=${id}`)
    .then(res => res.json())
    .then(data => {
        if(data.status === 'sukses') {
            updateCartDisplay(); // Refresh tampilan keranjang biar ilang datanya
        } else {
            alert("Gagal hapus gara-gara: " + data.pesan);
        }
    })
    .catch(err => console.error("Error hapus:", err));
}

function ubahQty(id, delta) {
    const qtySpan = document.getElementById(`qty-${id}`);
    let currentQty = parseInt(qtySpan.innerText);
    let newQty = parseInt(qtySpan.innerText) + delta;

    if (newQty < 1) return; // Jangan sampe nol mprruy

    qtySpan.innerText = newQty;
    hitungTotal();
    fetch('update_qty_keranjang.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `id=${id}&qty=${newQty}`
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'sukses') {
            qtySpan.innerText = newQty;
            // Kalo lo punya total harga keseluruhan, panggil fungsinya di sini
        }
    });
}
function prosesCheckout() {
    let terpilih = [];
    document.querySelectorAll('.cart-checkbox:checked').forEach(cb => {
        terpilih.push(cb.value);
    });

    if (terpilih.length === 0) {
        alert("Pilih dulu item yang mau dibayar mprruy!");
        return;
    }

    // Arahin ke halaman pembayaran bawa data ID yang terpilih
    window.location.href = "checkout.php?ids=" + terpilih.join(',');
}
function tambahEventChecklist() {
    const checkboxes = document.querySelectorAll('.cart-checkbox');
    checkboxes.forEach(cb => {
        cb.addEventListener('change', hitungTotal);
    });
}

function hitungTotal() {
    let total = 0;
    const checkboxes = document.querySelectorAll('.cart-checkbox:checked');
    
    checkboxes.forEach(cb => {
        const kartu = cb.closest('.cart-card');
        
        // 1. Ambil Harga (Bersihin titik dan Rp)
        const hargaTeks = kartu.querySelector('span[style*="color:#03ac0e"]').innerText;
        const hargaAngka = parseInt(hargaTeks.replace(/[^0-9]/g, ''));
        
        // 2. Ambil Quantity (Jumlah beli)
        // Kita ambil teks dari span yang ID-nya ada 'qty-'
        const qtyTeks = kartu.querySelector('span[id^="qty-"]').innerText;
        const qtyAngka = parseInt(qtyTeks);
        
        // 3. Total per item = Harga x Qty
        total += (hargaAngka * qtyAngka);
    });

    // Update tampilan total di bawah
    const totalElement = document.getElementById('totalHargaCart');
    if (totalElement) {
        totalElement.innerText = "Rp " + total.toLocaleString('id-ID');
    }
}

function prosesCheckout() {
    const dipilih = document.querySelectorAll('.cart-checkbox:checked');
    
    // VALIDASI: Kalau belum ada yang di-checklist
    if (dipilih.length === 0) {
        alert("Waduh mprruy, pilih dulu barangnya (checklist) baru bisa bayar! 🙏");
        return;
    }

    // Kalau sudah ada yang diceklis, lanjut ke proses bayar
    let ids = Array.from(dipilih).map(cb => cb.value);
    console.log("Memproses ID Keranjang:", ids);
    window.location.href = "checkout.php?ids=" + ids.join(',');
}

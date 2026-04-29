/* ============================================================
    TOPZONE ULTIMATE JS - CLEAN & GACOR VERSION
    Fitur: Search, Slider, Cart (MySQL), Profile, & Checkout
   ============================================================ */

/* ===== A. GLOBAL VARIABLES ===== */
let kategoriAktif = "";
let sliderIndex = 0;
let selectedPrice = 0;
let selectedProductName = "";
let currentQty = 1;

/* ===== B. CORE DATA LOAD (SEARCH & KATEGORI) ===== */
function loadData() {
    const searchInput = document.getElementById("searchInput");
    const slider = document.getElementById("sliderWrap");
    const productList = document.getElementById("productList");
    const notFound = document.getElementById("notFound");
    const mainTitle = document.getElementById("mainTitle");

    const search = searchInput ? searchInput.value.trim() : ""; 

    if (search.length > 0 || kategoriAktif !== "") {
        if (slider) slider.style.display = "none";
        if (mainTitle) {
            mainTitle.innerHTML = search.length > 0 
                ? `<span style="color:#03ac0e">🔍 Hasil:</span> "${search}"` 
                : `<span style="color:#03ac0e">📂 Kategori:</span> ${kategoriAktif}`;
        }
    } else {
        if (slider) slider.style.display = "flex";
        if (mainTitle) mainTitle.innerText = "🔥 Semua Produk";
    }

    fetch(`search.php?search=${encodeURIComponent(search)}&kategori=${encodeURIComponent(kategoriAktif)}`)
    .then(res => res.text())
    .then(data => {
        const cleanData = data.trim();
        if (cleanData === "" || cleanData.includes("tidak ditemukan")) {
            if (productList) { productList.innerHTML = ""; productList.style.display = "none"; }
            if (notFound) {
                notFound.style.display = "block";
                notFound.innerHTML = `<div style="text-align:center; padding:50px;"><p style="font-size:40px;">📦</p><h3>Waduh mprruy, "${search}" gak ketemu!</h3></div>`;
            }
        } else {
            if (productList) { productList.innerHTML = cleanData; productList.style.display = "grid"; }
            if (notFound) notFound.style.display = "none";
        }
    })
    .catch(err => console.error("Search Error:", err));
}

function searchRealtime() { loadData(); }

function filterKategori(kat, el) {
    // 1. Logic Toggle (klik lagi buat matiin filter)
    if (kategoriAktif === kat) {
        kategoriAktif = ""; 
        if (el) el.classList.remove("active");
        // Kalau dimatiin, balikin active ke menu "Semua"
        document.querySelector('.tp-sidebar li:first-child').classList.add('active');
    } else {
        kategoriAktif = kat;
        document.querySelectorAll(".tp-sidebar li").forEach(li => li.classList.remove("active"));
        if (el) el.classList.add("active");
    }

    // 2. Panggil loadData buat narik data dari database lewat search.php
    loadData();
}
/* ===== C. KERANJANG (CART) SYSTEM - DATABASE CONNECTED ===== */
function updateCartDisplay() {
    fetch('ambil_keranjang_db.php')
    .then(res => res.json())
    .then(data => {
        const cartCount = document.getElementById("cartCount");
        const listContainer = document.getElementById("cartItemsList");
        
        if (cartCount) cartCount.innerText = data.length;
        if (!listContainer) return;

        if (data.length === 0) {
            listContainer.innerHTML = "<p style='text-align:center; padding:20px;'>Keranjang kosong mprruy!</p>";
            if(document.getElementById('totalHargaCart')) document.getElementById('totalHargaCart').innerText = "Rp 0";
            return;
        }

        listContainer.innerHTML = data.map(item => `
            <div class="cart-card" style="display:flex; gap:10px; border-bottom:1px solid #eee; padding:10px 0; align-items:center;">
                <input type="checkbox" class="cart-checkbox" value="${item.id_keranjang}">
                <img src="./${item.gambar}" onerror="this.src='./Default.jpg'" style="width:50px; height:50px; border-radius:8px; object-fit:cover;">
                <div style="flex:1;">
                    <b style="font-size:13px; display:block;">${item.nama_produk}</b>
                    <span style="color:#03ac0e; font-weight:bold;">Rp ${parseInt(item.harga).toLocaleString('id-ID')}</span>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:5px;">
                        <div style="display:flex; border:1px solid #ddd; border-radius:5px; align-items:center;">
                            <button onclick="ubahQty(${item.id_keranjang}, -1)" style="border:none; background:#f9f9f9; padding:2px 8px; cursor:pointer;">-</button>
                            <span id="qty-${item.id_keranjang}" style="padding:0 10px; font-size:12px;">${item.qty}</span>
                            <button onclick="ubahQty(${item.id_keranjang}, 1)" style="border:none; background:#f9f9f9; padding:2px 8px; cursor:pointer;">+</button>
                        </div>
                        <button onclick="hapusItemDB(${item.id_keranjang})" style="border:none; background:none; color:red; cursor:pointer; font-size:16px;">🗑️</button>
                    </div>
                </div>
            </div>
        `).join('') + `
            <div style="padding:15px; border-top:2px solid #eee; margin-top:10px;">
                <div style="display:flex; justify-content:space-between; font-weight:bold; margin-bottom:10px;">
                    <span>Total Tagihan:</span>
                    <span id="totalHargaCart" style="color:#03ac0e;">Rp 0</span>
                </div>
                <button onclick="prosesCheckout()" style="width:100%; background:#03ac0e; color:white; border:none; padding:12px; border-radius:8px; font-weight:bold; cursor:pointer;">
                    BAYAR SEKARANG
                </button>
            </div>`;
        
        tambahEventChecklist();
    }).catch(err => console.error("Cart Error:", err));
}

function ubahQty(id, delta) {
    const qtySpan = document.getElementById(`qty-${id}`);
    let newQty = parseInt(qtySpan.innerText) + delta;
    if (newQty < 1) return;

    fetch('update_qty_keranjang.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `id=${id}&qty=${newQty}`
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'sukses') {
            qtySpan.innerText = newQty;
            hitungTotal();
        }
    });
}

function hapusItemDB(id) {
    if(!confirm("Yakin mau buang item ini mprruy?")) return;
    fetch(`hapus_keranjang_db.php?id=${id}`)
    .then(res => res.json())
    .then(data => {
        if(data.status === 'sukses') updateCartDisplay();
    });
}

function hitungTotal() {
    let total = 0;
    document.querySelectorAll('.cart-checkbox:checked').forEach(cb => {
        const kartu = cb.closest('.cart-card');
        const hargaText = kartu.querySelector('span[style*="color:#03ac0e"]').innerText;
        const harga = parseInt(hargaText.replace(/[^0-9]/g, ''));
        const qty = parseInt(kartu.querySelector('span[id^="qty-"]').innerText);
        total += (harga * qty);
    });
    const el = document.getElementById('totalHargaCart');
    if (el) el.innerText = "Rp " + total.toLocaleString('id-ID');
}

function tambahEventChecklist() {
    document.querySelectorAll('.cart-checkbox').forEach(cb => {
        cb.addEventListener('change', hitungTotal);
    });
}

/* ===== D. SIDEBAR & MODAL CONTROLS ===== */
function toggleCartSidebar() {
    const cartSidebar = document.getElementById("cartSidebar");
    const overlay = document.getElementById("panelOverlay");
    
    // Tutup profil kalau lagi kebuka
    if(document.getElementById("profileSidebar")) document.getElementById("profileSidebar").classList.remove("active");
    
    cartSidebar.classList.toggle("active");
    if(overlay) overlay.style.display = cartSidebar.classList.contains("active") ? "block" : "none";
    
    if(cartSidebar.classList.contains("active")) updateCartDisplay();
}

function toggleProfileSidebar() {
    const sidebar = document.getElementById("profileSidebar");
    const cartSidebar = document.getElementById("cartSidebar");
    const overlay = document.getElementById("panelOverlay");
    
    if(cartSidebar) cartSidebar.classList.remove("active");
    sidebar.classList.toggle("active");
    if(overlay) overlay.style.display = sidebar.classList.contains("active") ? "block" : "none";
}

/* ===== E. PRODUCT INTERACTION (GAME DETAIL) ===== */
function selectPackage(name, price) {
    selectedPrice = price;
    selectedProductName = name;
    const disp = document.getElementById('displayTotal');
    if(disp) disp.innerText = "Rp " + price.toLocaleString('id-ID');
    
    const btn = document.getElementById('btnTambahKeranjang');
    if(btn) {
        btn.style.opacity = "1";
        btn.innerHTML = "➕ Tambah " + name + " ke Keranjang";
    }
}

function eksekusiTambah(id_game) {
    if (typeof IS_REAL_USER !== 'undefined' && !IS_REAL_USER) {
        alert("Login dulu mprruy!");
        window.location.href = "../Login/tampilanlogin.php";
        return;
    }
    
    if (selectedPrice === 0) {
        alert("Pilih paketnya dulu mprruy!");
        return;
    }

    let fd = new FormData();
    fd.append('id_game', id_game);
    fd.append('nama_produk', selectedProductName);
    fd.append('harga', selectedPrice);
    fd.append('qty', currentQty);

    fetch('proses_keranjang.php', {
        method: 'POST',
        body: fd
    })
    .then(res => res.text())
    .then(hasil => {
        alert("Mantap! Masuk keranjang 🔥");
        updateCartDisplay();
    });
}

function prosesCheckout() {
    const dipilih = document.querySelectorAll('.cart-checkbox:checked');
    if (dipilih.length === 0) {
        alert("Pilih dulu barangnya mprruy! 🙏");
        return;
    }
    let ids = Array.from(dipilih).map(cb => cb.value);
    window.location.href = "checkout.php?ids=" + ids.join(',');
}

/* ===== F. INITIALIZE ON LOAD ===== */
/* ===== F. INITIALIZE ON LOAD ===== */
document.addEventListener("DOMContentLoaded", () => {
    // loadData(); // <--- KOMENTARIN ATAU HAPUS BARIS INI BIAR GAK DOUBLE RENDER
    updateCartDisplay();

    // Slider Auto-Play Tetap Jalan
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

// Klik Overlay buat nutup semua panel
const overlay = document.getElementById("panelOverlay");
if(overlay) {
    overlay.onclick = function() {
        document.getElementById("cartSidebar").classList.remove("active");
        document.getElementById("profileSidebar").classList.remove("active");
        this.style.display = "none";
    };
}
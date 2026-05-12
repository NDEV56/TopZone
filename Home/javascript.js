const swalConfig = {
        target: 'body',
        background: 'rgba(20, 20, 20, 0.95)',
        color: '#fff',
        customClass: {
            container: 'tz-swal-container'
        },
        didOpen: () => {
            // Paksa container Swal ke kasta tertinggi (Z-Index 1M)
            const container = document.querySelector('.swal2-container');
            if (container) container.style.zIndex = '9999999';
        }
    };

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
                ? `<span style="color:#gold">Hasil:</span> "${search}"` 
                : `<span style="color:#gold">Kategori:</span> ${kategoriAktif}`;
        }
    } else {
        if (slider) slider.style.display = "flex";
        if (mainTitle) mainTitle.innerText = "Semua Produk";
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
                <input type="checkbox" class="cart-checkbox" value="${item.id}" data-price="${item.qty * item.harga}" onchange="hitungTotal()">
                
                <img src="${item.gambar}" onerror="this.src='Default.jpg'" style="width:55px; height:55px; border-radius:8px; object-fit:cover; border:1px solid #ddd;">
                
                <div style="flex:1;">
                    <small style="color:#888; font-size:10px; display:block; text-transform:uppercase; font-weight:bold;">
                        ${item.nama_game}
                    </small>
                    
                    <b style="font-size:13px; display:block; margin-top:-2px;">${item.nama_produk}</b>
                    <span style="color:#03ac0e; font-weight:bold;">Rp ${parseInt(item.harga).toLocaleString('id-ID')}</span>
                    
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:5px;">
                        <div style="display:flex; border:1px solid #ddd; border-radius:5px; align-items:center;">
                            <button onclick="ubahQty(${item.id}, -1)" style="border:none; background:#f9f9f9; padding:2px 8px; cursor:pointer;">-</button>
                            <span id="qty-${item.id}" style="padding:0 10px; font-size:12px;">${item.qty}</span>
                            <button onclick="ubahQty(${item.id}, 1)" style="border:none; background:#f9f9f9; padding:2px 8px; cursor:pointer;">+</button>
                        </div>
                        <button onclick="hapusItemDB(${item.id})" style="border:none; background:none; color:red; cursor:pointer; font-size:16px;">🗑️</button>
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
    Swal.fire({
        title: 'BUANG ITEM?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus!',
        confirmButtonColor: '#d33',
        background: 'rgba(20, 20, 20, 0.95)',
        color: '#fff',
        didOpen: () => {
            const container = document.querySelector('.swal2-container');
            if (container) container.style.zIndex = '9999999';
        }
    }).then((res) => {
        if (res.isConfirmed) {
            // 1. ILANGIN ITEMNYA DULU
            const allCards = document.querySelectorAll('.cart-card');
            allCards.forEach(card => {
                const cb = card.querySelector('.cart-checkbox');
                if (cb && cb.value == id) {
                    card.style.transition = '0.3s ease';
                    card.style.opacity = '0';
                    card.style.transform = 'translateX(20px)';
                    
                    setTimeout(() => {
                        card.remove();

                        // 2. CEK APAKAH KERANJANG BENERAN ABIS (LIVE CHECK)
                        const remainingItems = document.querySelectorAll('.cart-card');
                        if (remainingItems.length === 0) {
                            // --- KUNCI: ANIMASIIN PANEL CHECKOUT BIAR ILANG ---
                            // Cari container bawah (tempat total tagihan & tombol bayar)
                            const listContainer = document.getElementById("cartItemsList");
                            
                            if (listContainer) {
                                listContainer.style.transition = '0.5s ease';
                                listContainer.style.opacity = '0';
                                
                                setTimeout(() => {
                                    // Ganti isinya jadi pesan kosong
                                    listContainer.innerHTML = `
                                        <div style="text-align:center; padding:40px 20px; opacity:0; transform:translateY(10px); transition:0.5s;" id="emptyMsg">
                                            <p style="color:#888;">Keranjang kosong mprruy!</p>
                                        </div>
                                    `;
                                    listContainer.style.opacity = '1';
                                    setTimeout(() => {
                                        document.getElementById('emptyMsg').style.opacity = '1';
                                        document.getElementById('emptyMsg').style.transform = 'translateY(0)';
                                    }, 50);
                                }, 500);
                            }
                        }
                        
                        // Update angka badge & hitung ulang total
                        if (typeof hitungTotal === "function") hitungTotal();
                        const cartCount = document.getElementById("cartCount");
                        if(cartCount) cartCount.innerText = remainingItems.length;

                    }, 300);
                }
            });

            // 3. TETEP TEMBAK DB BIAR SINKRON
            fetch(`hapus_keranjang_db.php?id=${id}`).catch(err => console.log(err));
        }
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
    fd.append('id_game', id_game); // KIRIM ID GAME-NYA KE PHP
    fd.append('nama_produk', selectedProductName);
    fd.append('harga', selectedPrice);
    fd.append('qty', currentQty);

    fetch('proses_keranjang.php', {
        method: 'POST',
        body: fd
    })
    .then(res => res.text())
    .then(hasil => {
             Toast.fire({
                icon: 'success',
                html: `<span class="tz-toast-title">BERHASIL</span><p class="tz-toast-content"><b>${currentSelectedProduct}</b> masuk keranjang!</p>`
            })
        updateCartDisplay();
    });
}

function prosesCheckout() {
    const dipilih = document.querySelectorAll('.cart-checkbox:checked');
    
    if (dipilih.length === 0) {
            Toast.fire({
                icon: 'error',
                html: `<span class="tz-toast-title">LAU PILIH PRODUK DULU NAPA!</span><p class="tz-toast-content">Pilih dulu sono!</p>`
            })
        return;
    }

    // Ambil ID keranjang yang dicentang
    let ids = Array.from(dipilih).map(cb => cb.value);

    // ARAHKAN KE FOLDER Checkout DAN FILE pembayaran.php
    // Gunakan path yang benar: Checkout/pembayaran.php
    window.location.href = "../Home/Checkout/pembayaran.php?ids=" + ids.join(',');
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
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3500,
    timerProgressBar: true,
    background: 'transparent',
    showClass: {
        popup: 'animate__none' // MATIKAN animasi bawaan Swal agar CSS kita yang handle
    },
    hideClass: {
        popup: 'animate__animated animate__fadeOutRight animate__faster'
    },
    customClass: {
        popup: 'tz-toast-popup',
        timerProgressBar: 'tz-toast-timer'
    },
    didOpen: (toast) => {
        // Paksa ulang warna via JS kalau CSS masih kalah (Jaga-jaga)
        toast.querySelector('.swal2-title').style.setProperty('color', '#ffffff', 'important');
        toast.querySelector('.swal2-html-container').style.setProperty('color', '#ffffff', 'important');
    }
});
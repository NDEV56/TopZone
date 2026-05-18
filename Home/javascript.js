/* ===== SweetAlert2 Config ===== */
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

/* ===== A. GLOBAL VARIABLES ===== */
let kategoriAktif = "";
let sliderIndex = 0;
let selectedPrice = 0;
let selectedProductName = "";
let currentQty = 1;

/* ===== B. REALTIME SEARCH & FILTER SYSTEM ===== */
function loadData() {
    const searchInput = document.getElementById("searchInput");
    const slider = document.getElementById("tzHeroWrap"); 
    const productList = document.getElementById("productList");
    const notFound = document.getElementById("notFound");
    const mainTitle = document.getElementById("mainTitle");

    // Tangkap elemen kontainer luar & wrapper header bray
    const repeatOrderSection = document.querySelector(".tz-repeat-order-section");
    const mainProductsBox = document.querySelector(".tz-main-products-box");
    const tpHeaderWrap = document.querySelector(".tp-header-wrap"); // BIAR API GAK MELAYANG bray!

    const search = searchInput ? searchInput.value.trim() : ""; 

    // --- LIMIT SAKTI 10 HURUF ---
    const maxLimit = 10; 
    const displaySearch = search.length > maxLimit 
        ? search.substring(0, maxLimit) + "..." 
        : search;

    if (productList) productList.innerHTML = "";

    // Skenario A: Jika user sedang MENCARI sesuatu atau memilih KATEGORI
    if (search.length > 0 || kategoriAktif !== "") {
        if (slider) slider.style.setProperty("display", "none", "important"); 
        
        // SEMBUNYIKAN BOX BELI LAGI TOTAL
        if (repeatOrderSection) {
            repeatOrderSection.style.setProperty("display", "none", "important");
        }
        
        // HANCURKAN BACKGROUND UTAMA (Biar gak double box kacanya)
        if (mainProductsBox) {
            mainProductsBox.style.setProperty("background", "transparent", "important");
            mainProductsBox.style.setProperty("backdrop-filter", "none", "important");
            mainProductsBox.style.setProperty("-webkit-backdrop-filter", "none", "important");
            mainProductsBox.style.setProperty("border", "none", "important");
            mainProductsBox.style.setProperty("box-shadow", "none", "important");
            mainProductsBox.style.setProperty("padding", "0px", "important");
        }

        if (mainTitle) {
            mainTitle.style.display = "block";
            mainTitle.innerHTML = search.length > 0 
                ? `<span style="color:#38bdf8">Hasil:</span> "${displaySearch}"` 
                : `<span style="color:#38bdf8">Kategori:</span> ${kategoriAktif}`;
        }

    } else {
        // Skenario B: Kembali ke halaman utama / Beranda awal
        if (slider) slider.style.setProperty("display", "flex", "important"); 
        
        if (repeatOrderSection) {
            repeatOrderSection.style.setProperty("display", "block", "important");
        }

        // KEMBALIKAN EFEK LIQUID GLASS BLUE KREASI LU bray
        if (mainProductsBox) {
            mainProductsBox.style.setProperty("background", "rgba(0, 38, 230, 0.15)", "important");
            mainProductsBox.style.setProperty("backdrop-filter", "blur(20px) saturate(160%)", "important");
            mainProductsBox.style.setProperty("-webkit-backdrop-filter", "blur(20px) saturate(160%)", "important");
            mainProductsBox.style.setProperty("border", "1px solid rgba(59, 130, 246, 0.3)", "important");
            mainProductsBox.style.setProperty("border-top", "1px solid rgba(96, 165, 250, 0.5)", "important");
            mainProductsBox.style.setProperty("padding", "24px", "important");
            mainProductsBox.style.setProperty("box-shadow", "0 15px 35px rgba(0, 38, 230, 0.2)", "important");
        }

        if (tpHeaderWrap) {
            tpHeaderWrap.style.setProperty("display", "flex", "important"); // Tampilkan lagi headernya
        }

        if (mainTitle) {
            mainTitle.style.display = "block";
            mainTitle.innerText = "Semua Produk";
        }
    }

    fetch(`search.php?search=${encodeURIComponent(search)}&kategori=${encodeURIComponent(kategoriAktif)}`)
    .then(res => res.text())
    .then(data => {
        const cleanData = data.trim();
        
        if (cleanData === "" || cleanData.includes("tidak ditemukan")) {
            if (productList) { 
                productList.innerHTML = ""; 
                productList.style.display = "none"; 
            }
            
            // Sembunyikan seluruh bungkus judul biar icon api '🔥' gak ketinggalan melayang sendirian
            if (tpHeaderWrap) {
                tpHeaderWrap.style.setProperty("display", "none", "important");
            }
            if (mainTitle) {
                mainTitle.style.display = "none";
                mainTitle.innerHTML = "";
            }
            
            if (notFound) {
                notFound.style.cssText = `
                    display: flex !important; 
                    grid-column: 1 / -1; 
                    width: calc(100% + 240px) !important; 
                    margin-left: -240px !important; 
                    justify-content: center !important; 
                    align-items: center !important; 
                    padding: 60px 0 !important; 
                    box-sizing: border-box !important;
                `;
                
                notFound.innerHTML = `
                    <div class="tz-poros-notfound-baru" style="width: 100% !important; max-width: 440px !important; box-sizing: border-box !important; padding: 0 20px !important;">
                        <div class="tz-card-notfound-baru" style="
                            background: rgba(0, 38, 230, 0.18) !important; 
                            backdrop-filter: blur(20px) saturate(160%) !important; 
                            -webkit-backdrop-filter: blur(20px) saturate(160%) !important; 
                            border: 1px solid rgba(96, 165, 250, 0.4) !important; 
                            border-top: 1px solid rgba(147, 197, 253, 0.6) !important;
                            box-shadow: 0 20px 45px rgba(0, 38, 230, 0.25), inset 0 0 15px rgba(59, 130, 246, 0.2) !important;
                            padding: 35px 25px !important; 
                            border-radius: 20px !important; 
                            text-align: center !important; 
                            box-sizing: border-box !important;
                        ">
                            <div style="font-size: 45px; margin-bottom: 15px; filter: drop-shadow(0 0 12px rgba(56, 189, 248, 0.8));">🔍</div>
                            <h3 style="color: #ffffff; margin: 0 0 12px 0; font-size: 18px; font-weight: 700; line-height: 1.4;">
                                Waduh mprruy, <span style="color: #38bdf8; word-break: break-all; text-shadow: 0 0 8px rgba(56, 189, 248, 0.5);">"${displaySearch || 'game'}"</span><br>kagak ketemu bray!
                            </h3>
                            <p style="color: #cbd5e1; margin: 0; font-size: 13px; font-weight: 500; opacity: 0.85;">
                                Coba cek kembali ketikan lu atau pilih kategori lain...
                            </p>
                        </div>
                    </div>
                `;
            }
        } else {
            // Skenario pas data ADA / KETEMU
            if (tpHeaderWrap) tpHeaderWrap.style.setProperty("display", "flex", "important");
            if (mainTitle && search.length > 0) mainTitle.style.display = "block";
            
            if (productList) { 
                // 1. Suntik data asli ke produk bray
                productList.innerHTML = cleanData; 
                
                // 2. 🔥 [ANTI DOUBLE WRAPPER] Kalo search.php ngirim div pembungkus lagi, kita bongkar paksa!
                const doubleWrapper = productList.querySelector(':scope > .tp-grid-six, :scope > .tp-grid, :scope > .row');
                if (doubleWrapper) {
                    productList.innerHTML = doubleWrapper.innerHTML;
                }

                // 3. 🔥 Kunci mati class utamanya & set display-nya
                productList.classList.add("tp-grid-six");
                productList.style.setProperty("display", "grid", "important"); 
            }
            if (notFound) {
                notFound.style.display = "none";
                notFound.innerHTML = "";
            }
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
        const firstSidebarLi = document.querySelector('.tp-sidebar li:first-child');
        if (firstSidebarLi) firstSidebarLi.classList.add('active');
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
        
        // 1. CEK APAKAH ADA EROR DARI BACKEND PHP BRAY
        if (data && data.status === 'error') {
            console.error("Database SQL Error:", data.message);
            if (listContainer) {
                listContainer.innerHTML = `
                    <div style="padding: 20px; text-align: center; color: #ff4444;">
                        <b>Waduh mprruy, DB Eror!</b>
                        <p style="font-size: 11px; color: #aaa; margin-top: 5px; word-break: break-all;">
                            ${data.message}
                        </p>
                    </div>
                `;
            }
            return; // Hentikan script di sini biar gak maksain manggil .map()
        }

        if (cartCount) cartCount.innerText = data.length;
        if (!listContainer) return;

        if (data.length === 0) {
            listContainer.innerHTML = "<p style='text-align:center; padding:20px;'>Keranjang kosong mprruy!</p>";
            if(document.getElementById('totalHargaCart')) document.getElementById('totalHargaCart').innerText = "Rp 0";
            return;
        }

        // Jalankan mapping data asli jika data terbukti berupa array aman
        listContainer.innerHTML = data.map(item => `
            <div class="cart-card" style="display:flex; gap:10px; border-bottom:1px solid #eee; padding:10px 0; align-items:center;">
                <input type="checkbox" class="cart-checkbox" value="${item.id_keranjang}" data-user="${item.catatan || ''}" data-price="${item.qty * item.harga}" onchange="hitungTotal()">
                
                <img src="${item.gambar}" onerror="this.src='Default.jpg'" style="width:55px; height:55px; border-radius:8px; object-fit:cover; border:1px solid #ddd;">
                
                <div style="flex:1;">
                    <small style="color:#888; font-size:10px; display:block; text-transform:uppercase; font-weight:bold;">
                        ${item.nama_game}
                    </small>
                    
                    <b style="font-size:13px; display:block; margin-top:-2px;">${item.nama_produk}</b>
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
        
    }).catch(err => console.error("Cart Error:", err));
}

function prosesCheckout() {
    const dipilih = document.querySelectorAll('.cart-checkbox:checked');
    
    if (dipilih.length === 0) {
        Toast.fire({
            icon: 'error',
            html: `<span class="tz-toast-title">PILIH PRODUK BRAY!</span><p class="tz-toast-content">Centang dulu barangnya mprruy!</p>`
        });
        return;
    }

    // Cuma ambil ID keranjang yang dicentang
    let ids = [];
    dipilih.forEach(cb => {
        ids.push(cb.value);
    });

    // Lempar parameter 'ids' ke pembayaran.php (Data akun biar backend PHP yang narik dari DB)
    const targetUrl = "../Home/Checkout/pembayaran.php?ids=" + ids.join(',');
    window.location.href = targetUrl;
}

function ubahQty(id, delta) {
    const qtySpan = document.getElementById(`qty-${id}`);
    if (!qtySpan) return;
    
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
                                        const emptyMsg = document.getElementById('emptyMsg');
                                        if(emptyMsg) {
                                            emptyMsg.style.opacity = '1';
                                            emptyMsg.style.transform = 'translateY(0)';
                                        }
                                    }, 50);
                                }, 500);
                            }
                        }
                        
                        // Update angka badge & hitung ulang total
                        hitungTotal();
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
        if (kartu) {
            const hargaText = kartu.querySelector('span[style*="color:#03ac0e"]').innerText;
            const harga = parseInt(hargaText.replace(/[^0-9]/g, ''));
            const qty = parseInt(kartu.querySelector('span[id^="qty-"]').innerText);
            total += (harga * qty);
        }
    });
    const el = document.getElementById('totalHargaCart');
    if (el) el.innerText = "Rp " + total.toLocaleString('id-ID');
}

function tambahEventChecklist() {
    document.querySelectorAll('.cart-checkbox').forEach(cb => {
        cb.removeEventListener('change', hitungTotal); // Hindari penumpukan listener
        cb.addEventListener('change', hitungTotal);
    });
}

/* ===== D. SIDEBAR & MODAL CONTROLS ===== */
function toggleCartSidebar() {
    const cartSidebar = document.getElementById("cartSidebar");
    const profileSidebar = document.getElementById("profileSidebar");
    const overlay = document.getElementById("panelOverlay");
    
    if(!cartSidebar) return;

    // Tutup profil kalau lagi kebuka
    if(profileSidebar) profileSidebar.classList.remove("active");
    
    cartSidebar.classList.toggle("active");
    if(overlay) overlay.style.display = cartSidebar.classList.contains("active") ? "block" : "none";
    
    if(cartSidebar.classList.contains("active")) updateCartDisplay();
}

function toggleProfileSidebar() {
    const sidebar = document.getElementById("profileSidebar");
    const cartSidebar = document.getElementById("cartSidebar");
    const overlay = document.getElementById("panelOverlay");
    
    if(!sidebar) return;

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
    
    if (selectedPrice === 0 || !selectedProductName) {
        alert("Pilih paketnya dulu mprruy!");
        return;
    }

    // 1. AMBIL DATA USER / UID SECARA DINAMIS SESUAI JENIS GAME BRAY
    let userDataRaw = "";
    try {
        const gameTitleEl = document.querySelector('.tz-game-title');
        const gameName = gameTitleEl ? gameTitleEl.innerText.toLowerCase() : "";

        if (gameName.includes('roblox')) {
            const mode = document.getElementById('roblox_mode') ? document.getElementById('roblox_mode').value : 'login';
            if (mode === 'login') {
                const u = document.getElementById('rblx_user').value.trim();
                const p = document.getElementById('rblx_pass').value.trim();
                const b1 = document.getElementById('rblx_backup1').value.trim();
                const b2 = document.getElementById('rblx_backup2').value.trim();
                const b3 = document.getElementById('rblx_backup3').value.trim();
                if(!u || !p) throw "Username & Password Roblox wajib diisi mprruy!";
                userDataRaw = `Mode: Login | User: ${u} | Pass: ${p} | Backup Codes: ${b1},${b2},${b3}`;
            } else {
                const idOnly = document.getElementById('rblx_id_only').value.trim();
                if(!idOnly) throw "Isi Username Roblox-nya bray!";
                userDataRaw = `Mode: 5 Hari | Target: ${idOnly}`;
            }
        } else if (gameName.includes('genshin')) {
            const uid = document.getElementById('uid_genshin').value.trim();
            const srv = document.getElementById('server_genshin') ? document.getElementById('server_genshin').value : '';
            if(!uid) throw "UID Genshin Impact jangan kosong bray!";
            userDataRaw = `UID: ${uid} | Server: ${srv}`;
        } else {
            // Skenario untuk game umum (Mobile Legends, Free Fire, dll)
            const inputGeneral = document.getElementById('general_user_id');
            if(!inputGeneral || !inputGeneral.value.trim()) throw "Data ID / Target Akun Game jangan kosong mprruy!";
            userDataRaw = inputGeneral.value.trim();
        }
    } catch (pesanError) {
        Swal.fire({ 
            icon: 'error', 
            title: 'DATA BELUM LENGKAP!', 
            text: pesanError, 
            background: 'rgba(20, 20, 20, 0.95)', 
            color: '#fff' 
        });
        return;
    }

    // 2. BUNGKUS KE FORMDATA UNTUK DIKIRIM KE BACKEND PHP
    let fd = new FormData();
    fd.append('id_game', id_game); 
    fd.append('nama_produk', selectedProductName); 
    fd.append('harga', selectedPrice);
    fd.append('qty', currentQty);
    fd.append('user_data', userDataRaw); 

    fetch('proses_keranjang.php', {
        method: 'POST',
        body: fd
    })
    .then(res => res.text())
    .then(hasil => {
         Toast.fire({
              icon: 'success',
              html: `<span class="tz-toast-title">BERHASIL</span><p class="tz-toast-content"><b>${selectedProductName}</b> masuk keranjang!</p>`
         });
         updateCartDisplay();
    })
    .catch(err => {
        console.error("Gagal tambah keranjang:", err);
        alert("Eror saat menyambungkan ke database bray!");
    });
}

/* ===== F. INITIALIZE ON LOAD & HERO SLIDER ===== */
document.addEventListener("DOMContentLoaded", () => {
    // Jalankan render keranjang pertama kali bray
    updateCartDisplay();

    // Setup Klik Overlay buat nutup semua panel sidebar
    const overlay = document.getElementById("panelOverlay");
    if(overlay) {
        overlay.onclick = function() {
            const cartSidebar = document.getElementById("cartSidebar");
            const profileSidebar = document.getElementById("profileSidebar");
            if(cartSidebar) cartSidebar.classList.remove("active");
            if(profileSidebar) profileSidebar.classList.remove("active");
            this.style.display = "none";
        };
    }

    // LOGIC UTAMA INTEGRASI HERO SLIDER `tzHeroTrack`
    const track = document.getElementById("tzHeroTrack");
    const prevBtn = document.getElementById("tzPrevBtn");
    const nextBtn = document.getElementById("tzNextBtn");
    const dotsContainer = document.getElementById("tzHeroDots");

    if (!track || !dotsContainer) return; 

    const slides = Array.from(track.children);
    let currentIndex = 0;

    // 1. Generate Titik-Titik Berdasarkan Jumlah Foto
    dotsContainer.innerHTML = ""; 
    slides.forEach((_, index) => {
        const dot = document.createElement("div");
        dot.classList.add("tz-indicator-dot");
        if (index === 0) dot.classList.add("tz-active");
        
        dot.addEventListener("click", () => {
            goToSlide(index);
        });
        dotsContainer.appendChild(dot);
    });

    // 2. Fungsi Pergeseran Gambar & Reset Loading Progress Bar via CSS Animation Trigger
    function goToSlide(index) {
        // Stop logic jika slider dibungkus / disembunyikan pas mode pencarian aktif
        const sliderWrap = document.getElementById("tzHeroWrap");
        if (sliderWrap && sliderWrap.style.display === "none") return;

        if (index < 0) {
            currentIndex = slides.length - 1;
        } else if (index >= slides.length) {
            currentIndex = 0;
        } else {
            currentIndex = index;
        }

        // Jalankan animasi geser frame bray
        track.style.transform = `translateX(-${currentIndex * 100}%)`;

        // Bersihkan style / listener lama pada indikator
        const currentDots = Array.from(dotsContainer.children);
        currentDots.forEach(dot => {
            dot.classList.remove("tz-active");
            
            // Kloning DOM node untuk reset state CSS Animation linear loading bar-nya bray
            const newDot = dot.cloneNode(true);
            if(dot.parentNode) dot.parentNode.replaceChild(newDot, dot);
        });

        // Ambil ulang referensi array dot baru pasca kloning selesai
        const updatedDots = Array.from(dotsContainer.children);

        // Pasang ulang trigger click manual ke dot baru
        updatedDots.forEach((d, i) => {
            d.addEventListener("click", () => goToSlide(i));
        });

        // Aktifkan indikator bar saat ini
        const activeDot = updatedDots[currentIndex];
        if (activeDot) {
            activeDot.classList.add("tz-active");

            // Kunci Utama: Slide otomatis bergeser jika animasi bar bawaan CSS sudah mencapai 100% (animationend)
            activeDot.addEventListener("animationend", function() {
                goToSlide(currentIndex + 1);
            }, { once: true });
        }
    }

    // 3. Pasang Event untuk Tombol Navigasi Kiri & Kanan Slider Hero
    if (prevBtn) {
        prevBtn.addEventListener("click", () => goToSlide(currentIndex - 1));
    }
    if (nextBtn) {
        nextBtn.addEventListener("click", () => goToSlide(currentIndex + 1));
    }

    // 4. Nyalakan inisialisasi awal slider pertama kali bray!
    goToSlide(0);
});
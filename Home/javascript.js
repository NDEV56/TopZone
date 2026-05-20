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

/* ===== SweetAlert2 Toast Config (Nama Class Sudah Dibedain) ===== */
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2500,                   // Otomatis hilang dalam 2.5 detik
    timerProgressBar: true,
    background: 'transparent',     // Biar warna & blur diatur penuh lewat class custom di CSS
    color: '#ffffff',
    showClass: {
        popup: 'animate__animated animate__fadeInRight animate__faster' 
    },
    hideClass: {
        popup: 'animate__animated animate__fadeOutRight animate__faster'
    },
    customClass: {
        popup: 'tz-pure-toast-container',  // Class baru biar gak nabrak .swal2-toast bawaan
        timerProgressBar: 'tz-pure-toast-progress' // Class progress bar baru
    },
    html: '' // Kosongkan string style di sini biar gak numpuk
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

    const repeatOrderSection = document.querySelector(".tz-repeat-order-section");
    const mainProductsBox = document.querySelector(".tz-main-products-box");
    const tpHeaderWrap = document.querySelector(".tp-header-wrap"); 

    const search = searchInput ? searchInput.value.trim() : ""; 

    const maxLimit = 10; 
    const displaySearch = search.length > maxLimit 
        ? search.substring(0, maxLimit) + "..." 
        : search;

    if (productList) productList.innerHTML = "";

    if (search.length > 0 || kategoriAktif !== "") {
        if (slider) slider.style.setProperty("display", "none", "important"); 
        if (repeatOrderSection) {
            repeatOrderSection.style.setProperty("display", "none", "important");
        }
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
        if (slider) slider.style.setProperty("display", "flex", "important"); 
        if (repeatOrderSection) {
            repeatOrderSection.style.setProperty("display", "block", "important");
        }

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
            tpHeaderWrap.style.setProperty("display", "flex", "important"); 
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
            if (tpHeaderWrap) tpHeaderWrap.style.setProperty("display", "flex", "important");
            if (mainTitle && search.length > 0) mainTitle.style.display = "block";
            
            if (productList) { 
                productList.innerHTML = cleanData; 
                
                const doubleWrapper = productList.querySelector(':scope > .tp-grid-six, :scope > .tp-grid, :scope > .row');
                if (doubleWrapper) {
                    productList.innerHTML = doubleWrapper.innerHTML;
                }

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
    if (kategoriAktif === kat) {
        kategoriAktif = ""; 
        if (el) el.classList.remove("active");
        const firstSidebarLi = document.querySelector('.tp-sidebar li:first-child');
        if (firstSidebarLi) firstSidebarLi.classList.add('active');
    } else {
        kategoriAktif = kat;
        document.querySelectorAll(".tp-sidebar li").forEach(li => li.classList.remove("active"));
        if (el) el.classList.add("active");
    }
    loadData();
}

/* ===== C. KERANJANG (CART) SYSTEM - DATABASE CONNECTED (STABLE VERSION) ===== */
function updateCartDisplay() {
    fetch('ambil_keranjang_db.php')
    .then(res => res.json())
    .then(data => {
        const cartCount = document.getElementById("cartCount");
        const listContainer = document.getElementById("cartItemsList");
        
        if (data && data.status === 'error') {
            console.error("Database SQL Error:", data.message);
            if (listContainer) {
                listContainer.innerHTML = `
                    <div style="padding: 20px; text-align: center; color: #ff4444; background: rgba(239, 68, 68, 0.1); border-radius: 12px; border: 1px solid rgba(239, 68, 68, 0.3); backdrop-filter: blur(10px);">
                        <b>Waduh mprruy, DB Eror!</b>
                        <p style="font-size: 11px; color: #cbd5e1; margin-top: 5px; word-break: break-all;">
                            ${data.message}
                        </p>
                    </div>
                `;
            }
            return; 
        }

        if (cartCount) cartCount.innerText = data.length;
        if (!listContainer) return;

        if (data.length === 0) {
            listContainer.innerHTML = `
                <div style="text-align:center; padding:40px 20px; color:#94a3b8; font-weight:500; font-size:13px;">
                    <span style="font-size: 30px; display:block; margin-bottom:10px;">🛒</span>
                    Keranjang kosong mprruy!
                </div>
            `;
            if(document.getElementById('totalHargaCart')) document.getElementById('totalHargaCart').innerText = "Rp 0";
            return;
        }

        listContainer.innerHTML = data.map(item => `
            <div class="cart-card" style="display:flex; gap:12px; padding:12px; align-items:center; background: rgba(16, 28, 70, 0.45); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(56, 189, 248, 0.15); border-radius: 14px; margin-bottom: 10px; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: inset 0 0 15px rgba(56, 189, 248, 0.02);"
                 onmouseover="this.style.transform='translateY(-2px)'; this.style.borderColor='rgba(56, 189, 248, 0.45)'; this.style.boxShadow='0 8px 25px rgba(0, 8, 48, 0.6), inset 0 0 15px rgba(56, 189, 248, 0.1)';"
                 onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='rgba(56, 189, 248, 0.15)'; this.style.boxShadow='inset 0 0 15px rgba(56, 189, 248, 0.02)';">
                
                <input type="checkbox" class="cart-checkbox" value="${item.id_keranjang}" data-user="${item.catatan || ''}" data-price="${item.qty * item.harga}" onchange="hitungTotal()" style="cursor:pointer; width:16px; height:16px; accent-color:#00f2fe;">
                
                <img src="${item.gambar}" onerror="this.src='Default.jpg'" style="width:58px; height:58px; border-radius:10px; object-fit:cover; border:1px solid rgba(56, 189, 248, 0.25); box-shadow: 0 0 10px rgba(56, 189, 248, 0.1);">
                
                <div style="flex:1;">
                    <small style="color:#38bdf8; font-size:10px; display:block; text-transform:uppercase; font-weight:700; letter-spacing:0.5px;">
                        ${item.nama_game}
                    </small>
                    
                    <b style="font-size:13px; display:block; color:#ffffff; font-weight:700; margin-top:1px; letter-spacing: 0.3px;">${item.nama_produk}</b>
                    
                    <span style="color:#00f2fe; font-weight:800; font-size:13px; display:inline-block; margin-top:2px; text-shadow: 0 0 10px rgba(0, 242, 254, 0.25);">Rp ${parseInt(item.harga).toLocaleString('id-ID')}</span>
                    
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:6px;">
                        <div style="display:flex; background: rgba(255, 255, 255, 0.05); border:1px solid rgba(255, 255, 255, 0.15); border-radius:8px; align-items:center; overflow:hidden;">
                            <button onclick="ubahQty(${item.id_keranjang}, -1)" style="border:none; background:transparent; color:#fff; padding:4px 10px; cursor:pointer; font-weight:bold; font-size:12px; transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.15)'" onmouseout="this.style.background='transparent'">-</button>
                            <span id="qty-${item.id_keranjang}" style="padding:0 6px; font-size:12px; color:#ffffff; font-weight:700; min-width:18px; text-align:center;">${item.qty}</span>
                            <button onclick="ubahQty(${item.id_keranjang}, 1)" style="border:none; background:transparent; color:#fff; padding:4px 10px; cursor:pointer; font-weight:bold; font-size:12px; transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.15)'" onmouseout="this.style.background='transparent'">+</button>
                        </div>
                        
                        <button onclick="hapusItemDB(${item.id_keranjang})" style="border:none; background:none; color:#ef4444; cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center; transition:all 0.2s;" 
                                onmouseover="this.style.transform='scale(1.18)'; this.style.color='#f87171';" 
                                onmouseout="this.style.transform='scale(1)'; this.style.color='#ef4444';">
                            🗑️
                        </button>
                    </div>
                </div>
            </div>
        `).join('') + `
        
        <div style="padding:16px 4px 5px 4px; border-top:1px dashed rgba(255,255,255,0.15); margin-top:15px;">
            <div style="display:flex; justify-content:space-between; align-items:center; font-weight:bold; margin-bottom:14px;">
                <span style="color:#cbd5e1; font-size:13px; font-weight:500;">Total Tagihan:</span>
                <span id="totalHargaCart" style="color:#00f2fe; font-size:18px; font-weight:800; text-shadow: 0 0 15px rgba(0,242,254,0.4);">Rp 0</span>
            </div>
            
            <button onclick="prosesCheckout()" style="width:100%; background: linear-gradient(135deg, #00f2fe 0%, #4facfe 100%); color:#050e2e; border:none; padding:13px; border-radius:12px; font-weight:800; font-size:14px; letter-spacing:0.5px; cursor:pointer; box-shadow: 0 4px 20px rgba(0, 242, 254, 0.3); transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);"
                    onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 25px rgba(0, 242, 254, 0.55)';"
                    onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 20px rgba(0, 242, 254, 0.3)';"
                    onmousedown="this.style.transform='translateY(1px)'">
                BAYAR SEKARANG 🚀
            </button>
        </div>`;
        
    }).catch(err => console.error("Cart Error:", err));
}

function prosesCheckout() {
    const dipilih = document.querySelectorAll('.cart-checkbox:checked');
    
    if (dipilih.length === 0) {
        Toast.fire({
            // icon: 'error', <-- INI DIHAPUS BRAY, BIAR GAK TABRAKAN LAGI COY!
            html: `<span class="tz-toast-title">❌ PILIH PRODUK BRAY!</span><p class="tz-toast-content">Centang dulu barangnya mprruy!</p>`
        });
        return;
    }

    let ids = [];
    dipilih.forEach(cb => { ids.push(cb.value); });

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
        title: 'BUANG ITEM BRAY?',
        text: 'Item ini bakal dihapus dari keranjang lu!',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ef4444', // Merah neon untuk eksekusi
        cancelButtonColor: 'transparent', // Biar diatur penuh lewat hover CSS kita bray
        background: 'transparent', 
        color: '#fff',
        customClass: {
            popup: 'tz-liquid-modal', 
            confirmButton: 'tz-modal-btn-confirm',
            cancelButton: 'tz-modal-btn-cancel'
        },
        didOpen: () => {
            const container = document.querySelector('.swal2-container');
            if (container) {
                container.style.zIndex = '9999999';
                container.style.backdropFilter = 'blur(6px)';
                container.style.webkitBackdropFilter = 'blur(6px)';
            }
        }
    }).then((res) => {
        if (res.isConfirmed) {
            const allCards = document.querySelectorAll('.cart-card');
            allCards.forEach(card => {
                const cb = card.querySelector('.cart-checkbox');
                if (cb && cb.value == id) {
                    card.style.transition = '0.3s ease';
                    card.style.opacity = '0';
                    card.style.transform = 'translateX(20px)';
                    
                    setTimeout(() => {
                        card.remove();
                        const remainingItems = document.querySelectorAll('.cart-card');
                        if (remainingItems.length === 0) {
                            const listContainer = document.getElementById("cartItemsList");
                            if (listContainer) {
                                listContainer.style.transition = '0.5s ease';
                                listContainer.style.opacity = '0';
                                setTimeout(() => {
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
                        hitungTotal();
                        const cartCount = document.getElementById("cartCount");
                        if(cartCount) cartCount.innerText = remainingItems.length;
                    }, 300);
                }
            });
            fetch(`hapus_keranjang_db.php?id=${id}`).catch(err => console.log(err));
        }
    });
}

function hitungTotal() {
    let total = 0;
    
    // Ambil semua checkbox yang sedang dicentang
    document.querySelectorAll('.cart-checkbox:checked').forEach(cb => {
        const kartu = cb.closest('.cart-card');
        if (kartu) {
            // CARA PALING AMAN & ANTI-GAGAL:
            // Nyari span harga yang berwarna neon cyan (#00f2fe)
            const hargaEl = kartu.querySelector('span[style*="color:#00f2fe"]');
            
            // Cek dulu biar anti-eror null mprruy
            if (hargaEl) {
                const hargaText = hargaEl.innerText;
                const harga = parseInt(hargaText.replace(/[^0-9]/g, '')) || 0;
                
                // Ambil quantity dari span ID qty
                const qtyEl = kartu.querySelector('span[id^="qty-"]');
                const qty = qtyEl ? (parseInt(qtyEl.innerText) || 1) : 1;
                
                total += (harga * qty);
            }
        }
    });
    
    // Tembak nilainya ke element totalHargaCart bawaan lu
    const el = document.getElementById('totalHargaCart');
    if (el) {
        el.innerText = "Rp " + total.toLocaleString('id-ID');
    }
}

function tambahEventChecklist() {
    document.querySelectorAll('.cart-checkbox').forEach(cb => {
        cb.removeEventListener('change', hitungTotal); 
        cb.addEventListener('change', hitungTotal);
    });
}

/* ===== D. SIDEBAR & MODAL CONTROLS ===== */
function toggleCartSidebar() {
    const cartSidebar = document.getElementById("cartSidebar");
    const profileSidebar = document.getElementById("profileSidebar");
    const overlay = document.getElementById("panelOverlay");
    
    if(!cartSidebar) return;

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
        Toast.fire({
            // icon: 'error', <-- DIHAPUS BRAY
            html: `<span class="tz-toast-title">🔒 LOGIN DULU MBRRUY!</span><p class="tz-toast-content">Lu harus login akun TopZone dulu bray!</p>`
        });
        setTimeout(() => {
            window.location.href = "../Login/tampilanlogin.php";
        }, 1500);
        return;
    }
    
    if (selectedPrice === 0 || !selectedProductName) {
        Toast.fire({
            // icon: 'error', <-- DIHAPUS BRAY
            html: `<span class="tz-toast-title">⚠️ PAKET BELUM DIPILIH!</span><p class="tz-toast-content">Pilih item/nominal top up-nya dulu mprruy!</p>`
        });
        return;
    }

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
              // icon: 'success', <-- DIHAPUS BRAY BIAR EMERGENSI INDAH
              html: `<span class="tz-toast-title">✅ BERHASIL</span><p class="tz-toast-content"><b>${selectedProductName}</b> masuk keranjang!</p>`
         });
         updateCartDisplay();
    })
    .catch(err => {
        console.error("Gagal tambah keranjang:", err);
        Toast.fire({
            // icon: 'error', <-- DIHAPUS BRAY
            html: `<span class="tz-toast-title">💥 DATABASE ERROR</span><p class="tz-toast-content">Gagal menyambungkan ke server database bray!</p>`
        });
    });
}

/* ===== F. INITIALIZE ON LOAD & HERO SLIDER ===== */
document.addEventListener("DOMContentLoaded", () => {
    updateCartDisplay();

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

    const track = document.getElementById("tzHeroTrack");
    const prevBtn = document.getElementById("tzPrevBtn");
    const nextBtn = document.getElementById("tzNextBtn");
    const dotsContainer = document.getElementById("tzHeroDots");

    if (!track || !dotsContainer) return; 

    const slides = Array.from(track.children);
    let currentIndex = 0;

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

    function goToSlide(index) {
        const sliderWrap = document.getElementById("tzHeroWrap");
        if (sliderWrap && sliderWrap.style.display === "none") return;

        if (index < 0) {
            currentIndex = slides.length - 1;
        } else if (index >= slides.length) {
            currentIndex = 0;
        } else {
            currentIndex = index;
        }

        track.style.transform = `translateX(-${currentIndex * 100}%)`;

        const currentDots = Array.from(dotsContainer.children);
        currentDots.forEach(dot => {
            dot.classList.remove("tz-active");
            const newDot = dot.cloneNode(true);
            if(dot.parentNode) dot.parentNode.replaceChild(newDot, dot);
        });

        const updatedDots = Array.from(dotsContainer.children);

        updatedDots.forEach((d, i) => {
            d.addEventListener("click", () => goToSlide(i));
        });

        const activeDot = updatedDots[currentIndex];
        if (activeDot) {
            activeDot.classList.add("tz-active");

            activeDot.addEventListener("animationend", function() {
                goToSlide(currentIndex + 1);
            }, { once: true });
        }
    }

    if (prevBtn) {
        prevBtn.addEventListener("click", () => goToSlide(currentIndex - 1));
    }
    if (nextBtn) {
        nextBtn.addEventListener("click", () => goToSlide(currentIndex + 1));
    }

    goToSlide(0);
});
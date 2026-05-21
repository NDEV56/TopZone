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
    timer: 2500,
    timerProgressBar: true,
    background: 'transparent', // Biarkan CSS yang ambil alih
    color: '#ffffff',
    customClass: {
        popup: 'tz-pure-toast-glass',
        timerProgressBar: 'tz-pure-toast-progress'
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
    const allLi = document.querySelectorAll(".tp-sidebar li");
    
    if (kategoriAktif === kat) {
        // Reset ke "Semua"
        kategoriAktif = ""; 
        allLi.forEach(li => li.classList.remove("active"));
        document.querySelector('.tp-sidebar li:first-child').classList.add('active');
    } else {
        kategoriAktif = kat;
        allLi.forEach(li => li.classList.remove("active"));
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
        confirmButtonColor: '#ef4444',
        cancelButtonColor: 'transparent',
        background: 'transparent', 
        color: '#fff',
        // Mengatur backdrop agar tipis/transparan (bukan abu-abu pekat)
        backdrop: 'rgba(0,0,0,0.0)', 
        customClass: {
            popup: 'tz-liquid-modal', 
            confirmButton: 'tz-modal-btn-confirm',
            cancelButton: 'tz-modal-btn-cancel'
        },
        didOpen: () => {
            const container = document.querySelector('.swal2-container');
            if (container) {
                container.style.zIndex = '9999999';
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


//FOOTER
// Fungsi Pop-up FAQ Lengkap Berbasis Liquid Glassmorphism
function bukaFAQ() {
    Swal.fire({
        title: '<span style="color:#ffffff; font-family:\'Poppins\',sans-serif; text-shadow: 0 0 10px rgba(255,255,255,0.2);"><i class="fa-solid fa-circle-question" style="color:rgba(201, 162, 39, 1)"></i> FAQ & PUSAT BANTUAN TOPZONE</span>',
        html: `
            <div style="text-align: left; color: #e2e8f0; font-size: 13px; max-height: 320px; overflow-y: auto; padding-right: 8px; line-height: 1.6; font-family:\'Segoe UI\',sans-serif;">
                <div style="background: rgba(255,255,255,0.03); padding: 12px; border-radius: 10px; border-left: 4px solid rgba(25, 0, 247, 1); margin-bottom: 12px;">
                    <strong style="color: #fff; font-size: 14px;">1. Bagaimana Cara Bertransaksi di TOPZONE?</strong><br>
                    Silakan pilih kategori game yang diinginkan pada halaman utama, tentukan nominal/paket item, masukkan User ID & Zone ID akun game Anda dengan benar, pilih metode pembayaran via Xendit gateway, lalu selesaikan pembayaran. Sistem akan memproses pesanan Anda secara otomatis.
                </div>
                
                <div style="background: rgba(255,255,255,0.03); padding: 12px; border-radius: 10px; border-left: 4px solid rgba(25, 0, 247, 1); margin-bottom: 12px;">
                    <strong style="color: #fff; font-size: 14px;">2. Berapa Lama Waktu Pengiriman Item Game?</strong><br>
                    Dalam kondisi server normal, sistem otomatisasi kami akan menyuntikkan item game langsung ke akun Anda dalam waktu 10 detik hingga maksimal 5 menit setelah sitem menerima konfirmasi pembayaran sukses dari gateway.
                </div>

                <div style="background: rgba(255,255,255,0.03); padding: 12px; border-radius: 10px; border-left: 4px solid rgba(25, 0, 247, 1); margin-bottom: 12px;">
                    <strong style="color: #fff; font-size: 14px;">3. Sudah Bayar tapi Status Masih Pending / Belum Masuk?</strong><br>
                    Jangan panik. Terkadang terdapat delay antrean di sisi provider game atau delay mutasi bank. Jika dalam waktu 15 menit item belum masuk, silakan klik menu <strong>"Chat Admin"</strong> di footer atau hubungi support kami dengan melampirkan Invoice ID transaksi Anda.
                </div>

                <div style="background: rgba(255,255,255,0.03); padding: 12px; border-radius: 10px; border-left: 4px solid rgba(25, 0, 247, 1); margin-bottom: 12px;">
                    <strong style="color: #fff; font-size: 14px;">4. Apakah Layanan TOPZONE Buka 24 Jam?</strong><br>
                    Ya, sistem pemrosesan pesanan dan metode pembayaran instan kami beroperasi secara otomatis 24 jam non-stop setiap hari. Namun, untuk layanan bantuan/layanan pelanggan via Chat Admin beroperasi mulai pukul 08:00 WIB hingga 22:00 WIB.
                </div>
            </div>
        `,
        showClass: { popup: 'animasi-liquid-masuk' },
        hideClass: { popup: 'swal2-fade swal2-hide' },
        buttonsStyling: false,
        customClass: {
            popup: 'liquid-glass-popup',
            confirmButton: 'glass-btn-confirm'
        },
        confirmButtonText: 'Tutup'
    });
}

// Fungsi Pop-up Kebijakan Lengkap Berbasis Liquid Glassmorphism
function bukaKebijakan() {
    Swal.fire({
        title: '<span style="color:#ffffff; font-family:\'Poppins\',sans-serif; text-shadow: 0 0 10px rgba(255,255,255,0.2);"><i class="fa-solid fa-shield-halved" style="color:rgba(201, 162, 39, 1)"></i> KEBIJAKAN & PRIVASI LAYANAN</span>',
        html: `
            <div style="text-align: left; color: #e2e8f0; font-size: 13px; max-height: 320px; overflow-y: auto; padding-right: 8px; line-height: 1.6; font-family:\'Segoe UI\',sans-serif;">
                <p style="color: #fff; margin-bottom: 15px; font-weight: 500;">Dengan mengakses, menjelajahi, dan melakukan transaksi di platform TOPZONE, Anda secara sadar tunduk dan menyetujui seluruh syarat ketentuan hukum yang berlaku di bawah ini:</p>
                
                <h5 style="color: rgba(201, 162, 39, 1); margin: 15px 0 5px 0; font-size:14px;">A. Validasi Data Akun (Tanggung Jawab Pengguna)</h5>
                <p style="margin-bottom: 10px; padding-left: 5px;">Pengguna bertanggung jawab penuh atas kebenaran penginputan User ID, Server ID, atau data akun game lainnya. TOPZONE tidak berkewajiban melakukan validasi ulang, dan kesalahan pengiriman akibat kelalaian penginputan oleh pembeli sepenuhnya <strong>TIDAK DAPAT DI-REFUND ATAU DIBATALKAN</strong>.</p>
                
                <h5 style="color: rgba(201, 162, 39, 1); margin: 15px 0 5px 0; font-size:14px;">B. Kebijakan Pembatalan & Pengembalian Dana (Refund)</h5>
                <p style="margin-bottom: 10px; padding-left: 5px;">Seluruh transaksi pembayaran yang telah sukses divalidasi oleh sistem payment gateway bersifat mutlak dan final. Pengembalian dana hanya dapat diproses apabila sistem TOPZONE gagal mengirimkan produk akibat stok kosong atau kesalahan teknis dari server internal kami.</p>
                
                <h5 style="color: rgba(201, 162, 39, 1); margin: 15px 0 5px 0; font-size:14px;">C. Perlindungan Data Pribasi</h5>
                <p style="margin-bottom: 10px; padding-left: 5px;">Kami sangat menjaga privasi Anda. Seluruh informasi data akun game, email, nomor WhatsApp, serta catatan transaksi dienkripsi dengan aman dan hanya digunakan untuk kepentingan pemrosesan pesanan. Kami menjamin data Anda tidak akan dijual atau disalahgunakan kepada pihak ketiga.</p>

                <h5 style="color: rgba(201, 162, 39, 1); margin: 15px 0 5px 0; font-size:14px;">D. Batasan Keamanan Sistem</h5>
                <p style="margin-bottom: 5px; padding-left: 5px;">TOPZONE berhak membekukan akun atau memblokir akses pengguna jika mendeteksi adanya indikasi manipulasi data transaksi, penyalahgunaan bug sistem, atau tindakan ilegal lainnya yang merugikan ekosistem platform kami.</p>
            </div>
        `,
        showClass: { popup: 'animasi-liquid-masuk' },
        hideClass: { popup: 'swal2-fade swal2-hide' },
        buttonsStyling: false,
        customClass: {
            popup: 'liquid-glass-popup',
            confirmButton: 'glass-btn-confirm'
        },
        confirmButtonText: 'Saya Mengerti & Setujui'
    });
}

//RATINGGGGGGGGGGG
function filterReviewHalaman(rating, event) {
    document.querySelectorAll('.tz-filter-btn').forEach(btn => btn.classList.remove('active'));
    if(event) event.currentTarget.classList.add('active');

    const items = document.querySelectorAll('#mainReviewContainer .rev-item');
    const btnAll = document.getElementById('tzBtnViewAllContainer');
    const noReviewText = document.getElementById('noReviewText');
    
    let targetCocok = 0;
    let countDitampilkan = 0;

    items.forEach((item) => {
        const isMatch = (rating === 'semua' || item.getAttribute('data-rating') == rating);
        if (isMatch) {
            targetCocok++;
            item.style.display = (countDitampilkan < 3) ? 'block' : 'none';
            if (countDitampilkan < 3) countDitampilkan++;
        } else {
            item.style.display = 'none';
        }
    });

    if (noReviewText) noReviewText.style.display = (targetCocok === 0) ? 'block' : 'none';
    if (btnAll) {
        btnAll.style.display = (targetCocok > 3) ? 'block' : 'none';
        btnAll.querySelector('button').innerText = `Lihat Semua Ulasan (${targetCocok})`;
    }
}
function createNoReviewMsg() {
    let p = document.createElement('p');
    p.id = 'noReviewText';
    p.style.cssText = "text-align: center; color: rgba(255,255,255,0.4); padding: 25px; background: rgba(255,255,255,0.01); border-radius: 12px; border: 1px dashed rgba(255,255,255,0.1);";
    document.getElementById('mainReviewContainer').appendChild(p);
    return p;
}
// Fungsi Buka Pop-up dengan sistem filter yang sama
function bukaModalSemuaReview() {
    const targetArea = document.getElementById('tzPopupReviewInjectionArea');
    const source = document.querySelectorAll('#mainReviewContainer .rev-item');
    
    // Inject konten ke modal
    targetArea.innerHTML = '';
    source.forEach(item => {
        const clone = item.cloneNode(true);
        clone.style.display = 'block'; // Pastikan semua muncul di modal
        targetArea.appendChild(clone);
    });

    document.getElementById('tzMprruyPopupReview').style.display = 'flex';
}
function filterReviewDiDalamModal(rating, event) {
    const buttons = document.querySelectorAll('.tz-popup-box-main .tz-filter-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    event.currentTarget.classList.add('active');

    const container = document.getElementById('tzPopupReviewInjectionArea');
    let noReviewText = document.getElementById('noReviewTextModal');
    if (!noReviewText) {
        noReviewText = document.createElement('p');
        noReviewText.id = 'noReviewTextModal';
        noReviewText.style.cssText = "text-align: center; color: rgba(255,255,255,0.4); padding: 25px;";
        container.appendChild(noReviewText);
    }

    const items = document.querySelectorAll('#tzPopupReviewInjectionArea .rev-item');
    let targetKetemu = 0;

    items.forEach(item => {
        const itemRating = item.getAttribute('data-rating');
        if (rating === 'semua' || itemRating == rating) {
            item.style.display = 'block';
            item.classList.add('fade-in');
            targetKetemu++;
        } else {
            item.style.display = 'none';
        }
    });

    noReviewText.style.display = (targetKetemu === 0) ? 'block' : 'none';
    noReviewText.innerText = "Kosong mprruy, rating segini belum ada!";
}
function tutupModalSemuaReview() {
    document.getElementById('tzMprruyPopupReview').style.display = 'none';
}

function tutupPopUpSemuaReviewTopzone() {
    document.getElementById('tzMprruyPopupReview').style.display = 'none';
}

// System pengaman: Klik area luar gelap otomatis nutup pop-up
window.addEventListener('click', function(e) {
    const overlayPopup = document.getElementById('tzMprruyPopupReview');
    if (e.target == overlayPopup) {
        overlayPopup.style.display = 'none';
    }
});
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

/* ===== CART & BUY LOGIC ===== */
function getOrderData() {
    // selectedProductName dll harusnya variabel global dari file detail
    let data = {
        produk: typeof selectedProductName !== 'undefined' ? selectedProductName : "Game",
        harga: (typeof selectedPrice !== 'undefined' ? selectedPrice : 0) * (typeof currentQty !== 'undefined' ? currentQty : 1),
        qty: typeof currentQty !== 'undefined' ? currentQty : 1,
        id_game: ""
    };

    const robloxUser = document.getElementById('roblox_user');
    const genshinID = document.getElementById('user_id');
    const zoneID = document.getElementById('zone_id');

    if (robloxUser && robloxUser.offsetParent !== null) {
        data.id_game = robloxUser.value + " (Roblox)";
    } else if (genshinID) {
        data.id_game = genshinID.value + (zoneID ? " Zone: " + zoneID.value : "");
    }
    return data;
}

function tambahKeKeranjang() {
    if (!checkLogin()) return; // FILTER WAJIB LOGIN
    if (typeof selectedPrice === 'undefined' || selectedPrice === 0) return alert("Pilih produk dulu mprruy!");
    
    const order = getOrderData();
    if (order.id_game.trim() === "") return alert("ID Game jangan kosong!");

    let keranjang = JSON.parse(localStorage.getItem("topzone_cart")) || [];
    keranjang.push(order);
    localStorage.setItem("topzone_cart", JSON.stringify(keranjang));

    updateCartDisplay();
    showSuccessToast();
}

function prosesBeli() {
    if (!checkLogin()) return; // FILTER WAJIB LOGIN
    const order = getOrderData();
    if (order.id_game.trim() === "") return alert("ID Game wajib diisi!");
    alert("Gas mprruy! Lanjut ke pembayaran: Rp " + order.harga.toLocaleString('id-ID'));
}

/* ===== UI UIX (TOAST & DROPDOWN) ===== */
function showSuccessToast() {
    const toast = document.getElementById("toastSuccess");
    if(!toast) return;
    toast.classList.remove("toast-fade-out");
    toast.style.display = "block";
    setTimeout(() => {
        toast.classList.add("toast-fade-out");
        setTimeout(() => { toast.style.display = "none"; }, 500);
    }, 3000);
}

function toggleCartModal() {
    const dropdown = document.getElementById("cartDropdown");
    const listContainer = document.getElementById("cartItemsList");
    if(!dropdown) return;

    let keranjang = JSON.parse(localStorage.getItem("topzone_cart")) || [];

    if (dropdown.style.display === "none" || dropdown.style.display === "") {
        dropdown.style.display = "block";
        if (keranjang.length === 0) {
            listContainer.innerHTML = "<p style='color:#999; padding:10px;'>Kosong mprruy!</p>";
        } else {
            listContainer.innerHTML = keranjang.map(item => `
                <div style="border-bottom: 1px solid #eee; padding: 10px 0; font-size: 12px; color: #333;">
                    <strong>${item.produk}</strong><br>
                    ID: ${item.id_game}<br>
                    <span style="color:red">Rp ${item.harga.toLocaleString('id-ID')}</span>
                </div>
            `).join('');
        }
    } else {
        dropdown.style.display = "none";
    }
}

function updateCartDisplay() {
    let keranjang = JSON.parse(localStorage.getItem("topzone_cart")) || [];
    const cartCount = document.getElementById("cartCount");
    if (cartCount) cartCount.innerText = keranjang.length;
}

function hapusSemua() {
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
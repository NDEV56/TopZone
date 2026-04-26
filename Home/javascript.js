/* ===== GLOBAL VARIABLES ===== */
let kategoriAktif = "";
let sliderIndex = 0; // Pakai nama berbeda biar gak bentrok


/* ===== CORE FUNCTIONS ===== */
function loadData() {
    // Ambil semua elemen
    const searchInput = document.getElementById("searchInput");
    const slider = document.getElementById("sliderWrap");
    const productList = document.getElementById("productList");
    const notFound = document.getElementById("notFound"); // <-- Pastikan ini ketemu!
    const mainTitle = document.getElementById("mainTitle");

    const search = searchInput ? searchInput.value.trim() : ""; 

    // 1. LOGIKA JUDUL & SLIDER
    if (search.length > 0 || kategoriAktif !== "") {
        if (slider) slider.style.display = "none";
        if (mainTitle) mainTitle.innerText = search.length > 0 ? `🔍 Hasil: "${search}"` : `📂 Kategori: ${kategoriAktif}`;
    } else {
        if (slider) slider.style.display = "flex";
        if (mainTitle) mainTitle.innerText = "🔥 Semua Produk";
    }

    // 2. FETCH DATA
    fetch(`search.php?search=${encodeURIComponent(search)}&kategori=${encodeURIComponent(kategoriAktif)}`)
    .then(res => res.text())
    // ... baris fetch lu ...
    .then(data => {
        // ... baris sebelumnya ...
        const cleanData = data.trim();
        const productList = document.getElementById("productList");
        const notFound = document.getElementById("notFound");

        if (cleanData === "") {
            // JIKA KOSONG
            if (productList) {
                productList.innerHTML = ""; // Bersihin sisa card
                productList.style.display = "none"; // MATIIN GRIDNYA
            }
            if (notFound) {
                notFound.style.display = "block"; // MUNCULIN PESAN TENGAH
            }
        } else {
            // JIKA ADA DATA
            if (productList) {
                productList.innerHTML = cleanData;
                productList.style.display = "grid"; // HIDUPIN GRID LAGI
            }
            if (notFound) {
                notFound.style.display = "none";
            }
        }
    })
    .catch(err => console.error("Error:", err));
}
function searchRealtime() { loadData(); }

function filterKategori(kat, el) {
    kategoriAktif = kat; // Ini yang bikin judul berubah jadi 'MOBA', 'FPS', dll
    document.querySelectorAll(".tp-sidebar li").forEach(li => li.classList.remove("active"));
    if(el) el.classList.add("active");
    loadData();
}

document.addEventListener("DOMContentLoaded", loadData);

/* ===== SLIDER LOGIC ===== */
function initSlider() {
    const track = document.getElementById("sliderTrack");
    const slides = document.querySelectorAll(".tp-slide");
    
    if (!track || slides.length === 0) return;

    setInterval(() => {
        let sliderWrap = document.getElementById("sliderWrap");
        // Slider cuma jalan kalau lagi kelihatan
        if (sliderWrap && sliderWrap.style.display !== "none") {
            sliderIndex = (sliderIndex + 1) % slides.length;
            track.style.transform = `translateX(-${sliderIndex * 100}%)`;
        }
    }, 3000);
}

/* ===== USER & CART ===== */
function updateCart() {
    let cart = JSON.parse(localStorage.getItem("cart")) || [];
    let cartCountEl = document.getElementById("cartCount");
    if(cartCountEl) cartCountEl.innerText = cart.length;
}

function loadUser() {
    let user = localStorage.getItem("user");
    let userEl = document.getElementById("userName");
    if(userEl) {
        userEl.innerText = user ? user : "Guest";
    }
}

// Navigasi
function goCart() { alert("Masuk ke keranjang (belum dibuat)"); }
function goLogin() {
    let user = localStorage.getItem("user");
    alert(user ? "Masuk ke profil" : "Ke halaman login");
}

/* ===== INITIALIZE ON LOAD ===== */
document.addEventListener("DOMContentLoaded", () => {
    updateCart();
    loadUser();
    initSlider();
    // Load data awal biar grid gak kosong pas baru buka
    loadData(); 
});

function beliSekarang(id) {
    // Langsung lempar ke halaman pembayaran
    window.location.href = "pembayaran.php?id=" + id;
}

function tambahKeranjang(id) {
    // Pake fetch biar gak pindah halaman (AJAX)
    fetch("tambah_keranjang.php?id=" + id)
    .then(res => res.text())
    .then(data => {
        alert("Berhasil masuk keranjang mprruy!");
        // Update angka di icon keranjang header
        let count = document.getElementById("cartCount");
        count.innerText = parseInt(count.innerText) + 1;
    });
}
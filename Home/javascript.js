/* ===== GLOBAL VARIABLES ===== */
let kategoriAktif = "";
let sliderIndex = 0; // Pakai nama berbeda biar gak bentrok

/* ===== CORE FUNCTIONS ===== */

function loadData() {
    let search = document.getElementById("searchInput").value;
    let productList = document.getElementById("productList");
    let notFound = document.getElementById("notFound");
    let mainTitle = document.getElementById("mainTitle");

    // Kirim request ke search.php
    fetch(`search.php?search=${encodeURIComponent(search)}&kategori=${encodeURIComponent(kategoriAktif)}`)
    .then(res => res.text())
    .then(data => {
        // .trim() akan menghapus spasi/enter yang nggak sengaja terkirim dari PHP
        let cleanData = data.trim(); 

        if (cleanData === "") {
            // JIKA KOSONG: Kosongkan grid dan munculkan tulisan abu-abu
            productList.innerHTML = "";
            if (notFound) notFound.style.display = "block";
            
            // Opsional: ganti judul kalau lagi nyari tapi gada
            if (search.length > 0) {
                mainTitle.innerText = `🔍 Hasil Pencarian: "${search}" (Gada)`;
            }
        } else {
            // JIKA ADA DATA: Isi grid dan sembunyikan tulisan abu-abu
            productList.innerHTML = cleanData;
            if (notFound) notFound.style.display = "none";
            
            // Update judul normal
            if (search.length > 0) {
                mainTitle.innerText = `🔍 Hasil Pencarian: "${search}"`;
            } else if (kategoriAktif !== "") {
                mainTitle.innerText = `📂 Kategori: ${kategoriAktif}`;
            } else {
                mainTitle.innerText = "🔥 Semua Produk";
            }
        }
    });
}
// Trigger saat ngetik
function searchRealtime() {
    loadData();
}

// Trigger saat klik kategori
function filterKategori(kat, el) {
    kategoriAktif = kat;
    document.querySelectorAll(".tp-sidebar li").forEach(li => {
        li.classList.remove("active");
    });
    if(el) el.classList.add("active");
    loadData();
}

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
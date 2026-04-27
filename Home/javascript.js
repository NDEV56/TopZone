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
    // 1. CEK LOGIN (Paling Penting!)
    // Kita cek variabel PHP session yang tadi lo buat di atas ($nama_tampil)
    // Atau cara paling gampang, cek apakah ada tombol "Login" di navbar
    const isGuest = document.querySelector(".btn-login-nav"); // Sesuaikan class tombol login lo
    
    // Cek juga apakah nama user mengandung kata "User" + angka (Guest)
    const currentUserName = "<?php echo $nama_tampil; ?>";
    const isActuallyGuest = currentUserName.includes("User");

    if (isGuest || isActuallyGuest) {
        alert("Waduh mprruy! Login dulu biar akun lo terdata!");
        window.location.href = "../Login/tampilanlogin.php";
        return;
    }

    // 2. VALIDASI PRODUK (Jangan sampai Rp 0)
    if (selectedPrice === 0) {
        alert("Pilih produknya dulu mprruy!");
        return;
    }

    // 3. AMBIL DATA INPUT (Username/ID)
    // Ambil semua input yang ada di kolom kanan (ID Game/Password dll)
    const inputs = document.querySelectorAll('#dynamic-inputs input');
    let playerID = "";
    let dataInput = [];

    inputs.forEach(input => {
        if (input.value.trim() !== "") {
            dataInput.push(input.value.trim());
        }
    });

    if (dataInput.length === 0) {
        alert("Isi data akun game lo dulu mprruy!");
        return;
    }
    playerID = dataInput.join(" | ");

    // 4. KIRIM KE DATABASE (Hanya jalan kalau sudah lolos cek di atas)
    const formData = new FormData();
    formData.append('nama_produk', selectedProductName);
    formData.append('harga', selectedPrice * currentQty);
    formData.append('id_game', playerID);

    fetch('tambah_keranjang_db.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'sukses') {
            alert("✅ Mantap mprruy! " + selectedProductName + " udah masuk keranjang lo.");
            // Refresh angka keranjang di navbar
            if (typeof updateCartDisplay === 'function') updateCartDisplay();
        } else {
            alert("⚠️ Gagal simpan: " + data.pesan);
        }
    })
    .catch(err => {
        console.error("Error:", err);
        alert("Koneksi server bermasalah mprruy!");
    });
}

// Fungsi buat nampilin isi keranjang di modal/dropdown
function updateCartDisplay() {
    fetch('ambil_keranjang_db.php')
    .then(res => res.json())
    .then(data => {
        const cartCount = document.getElementById("cartCount");
        const listContainer = document.getElementById("cartItemsList");

        if (cartCount) cartCount.innerText = data.length;

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
                        <button onclick="hapusItemDB(${item.id_keranjang})" style="color:red; border:none; background:none; cursor:pointer;">❌</button>
                    </div>
                `).join('');
            }
        }
    })
    .catch(err => console.error("Gagal ambil data keranjang:", err));
}
function hapusSemua() {
    if(confirm("Yakin mau dikosongin mprruy?")) {
        localStorage.removeItem("topzone_cart");
        updateCartDisplay();
    }
}

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

const input_foto = document.getElementById('input_ganti_foto');

if (input_foto) {
    input_foto.addEventListener('change', function() {
        const file = this.files[0]; // Ambil file pertama yang dipilih

        if (file) {
            // 1. Tampilkan Pratinjau (Preview) Instan
            const reader = new FileReader();
            reader.onload = function(e) {
                // Ganti foto di lingkaran besar sidebar
                document.getElementById('prev_foto_besar').src = e.target.result;
                // Ganti foto di navbar (buletan kecil) biar sinkron
                document.getElementById('prev_foto_navbar').src = e.target.result;
            }
            reader.readAsDataURL(file); // Baca file sebagai data URL

            // 2. Kirim File ke Server (MySQL) pake AJAX (Kerja Beneran)
            const formData = new FormData();
            formData.append('foto_profil', file); // 'foto_profil' ini nama field buat PHP

            fetch('update_foto_profil_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json()) // PHP harus balikin JSON
            .then(data => {
                if (data.status === 'sukses') {
                    console.log('✅ Foto berhasil diupdate ke MySQL mprruy!');
                    // Tampilkan toast success jika perlu
                } else {
                    alert('⚠️ Gagal update foto gara-gara: ' + data.pesan);
                    // Kembalikan ke foto lama jika gagal
                }
            })
            .catch(err => {
                console.error('Error AJAX mprruy:', err);
                alert('⚠️ Terjadi error koneksi ke server.');
            });
        }
    });
}

document.getElementById('input_ganti_foto').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        const formData = new FormData();
        formData.append('foto_profil', file);

        // Kirim ke server tanpa reload
        fetch('update_foto_profil_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                // Ganti foto di semua tempat secara instan
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('prev_foto_navbar').src = e.target.result;
                    document.getElementById('prev_foto_besar').src = e.target.result;
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
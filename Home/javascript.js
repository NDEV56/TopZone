let kategoriAktif = "";

function loadData() {
    let searchInput = document.getElementById("searchInput");
    let search = searchInput ? searchInput.value : "";

    fetch(`search.php?search=${encodeURIComponent(search)}&kategori=${encodeURIComponent(kategoriAktif)}`)
    .then(res => res.text())
    .then(data => {
        document.getElementById("productList").innerHTML = data;
    });
}

// SEARCH
function searchRealtime() {
    loadData();
}

// FILTER
function filterKategori(kat, el) {
    kategoriAktif = kat;

    document.querySelectorAll(".tp-sidebar li").forEach(li => {
        li.classList.remove("active");
    });

    if(el) el.classList.add("active");

    loadData();
}

// CART COUNT (dummy dulu)
function updateCart() {
    let cart = JSON.parse(localStorage.getItem("cart")) || [];
    document.getElementById("cartCount").innerText = cart.length;
}

// GO TO CART
function goCart() {
    alert("Masuk ke keranjang (belum dibuat)");
}

// LOGIN / PROFILE
function goLogin() {
    let user = localStorage.getItem("user");
    if(user){
        alert("Masuk ke profil");
    } else {
        alert("Ke halaman login");
    }
}

// LOAD USER
function loadUser() {
    let user = localStorage.getItem("user");
    if(user){
        document.getElementById("userName").innerText = user;
    } else {
        document.getElementById("userName").innerText = "Guest";
    }
}

// INIT
updateCart();
loadUser();
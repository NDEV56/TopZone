function addToCart(name, price) {
    let item = { name: name, price: price };
    localStorage.setItem('pending_item', JSON.stringify(item));
    location.href = 'pembayaran.php';
}

// Fungsi untuk Chat Real-time di chat.html
function pantauStatus(id) {
    setInterval(() => {
        fetch('cek_status.php?id=' + id)
        .then(res => res.json())
        .then(data => {
            document.getElementById('statusText').innerText = data.status;
            if(data.status === 'Sudah Dikirim') {
                document.getElementById('s2').classList.add('active');
            }
            if(data.status === 'Selesai') {
                document.getElementById('s3').classList.add('active');
                document.getElementById('ratingPanel').style.display = 'block';
            }
        });
    }, 3000);
}
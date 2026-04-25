<?php include 'koneksi.php'; ?>
<style>
    .payment-method img {
        width: 120px; /* Ukuran pas, tidak raksasa lagi */
        height: auto;
        display: block;
        margin-bottom: 10px;
    }
    .method-card {
        border: 1px solid #ddd;
        padding: 15px;
        border-radius: 10px;
        display: inline-block;
        text-align: center;
        margin: 10px;
    }
</style>

<div class="container">
    <h2>Pilih Metode Pembayaran</h2>
    
    <div class="method-card">
        <img src="https://upload.wikimedia.org/wikipedia/commons/a/a2/Logo_QRIS.svg">
        <p>QRIS</p>
    </div>

    <div class="method-card">
        <img src="https://upload.wikimedia.org/wikipedia/commons/7/72/Logo_dana_blue.svg">
        <p>DANA</p>
    </div>
</div>
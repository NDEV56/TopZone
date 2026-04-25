<?php
include 'koneksi.php';

$search = $_GET['search'] ?? '';
$kategori = $_GET['kategori'] ?? '';

$query = "SELECT * FROM games WHERE 1=1";

if(!empty($search)){
    $search = mysqli_real_escape_string($conn, $search);
    $query .= " AND nama_game LIKE '%$search%'";
}

if(!empty($kategori)){
    $kategori = mysqli_real_escape_string($conn, $kategori);
    $query .= " AND kategori='$kategori'";
}

$result = mysqli_query($conn, $query);

if(mysqli_num_rows($result) > 0){
    while($g = mysqli_fetch_assoc($result)){
        echo "
        <a href='game_detail.php?game=".$g['slug']."' class='tp-card'>
            <div class='tp-img' style='background-image:url(".$g['gambar'].")'></div>
            <div class='tp-info'>
                <h4>".$g['nama_game']."</h4>
                <div class='tp-meta'>⭐ ".number_format($g['rating'],1)." | ".$g['terjual']." terjual</div>
                <div class='tp-price'>Rp ".number_format($g['harga'])."</div>
            </div>
        </a>
        ";
    }
} else {
    echo "<p style='color:red;'>game lau gada mpruyy!</p>";
}
?>
<?php
function pesanKeAPIGames($merchant_id, $secret_key, $kode_produk, $id_tujuan, $ref_id) {
    // URL Sandbox APIGames (Cek dokumentasi terbaru mereka untuk endpoint test)
    $url = "https://api-games.id/v1/transaksi"; 

    // Bikin Signature (Biasanya MD5 dari MerchantID + SecretKey + RefID)
    $signature = md5($merchant_id . $secret_key . $ref_id);

    $data = [
        'merchant_id' => $merchant_id,
        'secret_key'  => $secret_key,
        'produk'      => $kode_produk, // Misal: 'ROBLOX_400'
        'tujuan'      => $id_tujuan,   // ID Game Pembeli
        'ref_id'      => $ref_id,      // ID Order dari DB lo
        'signature'   => $signature
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}
?>
<?php
/**
 * fungsi_apigames.php — HARDENED v3.1
 *   • Pakai SSL verify
 *   • Timeout
 *   • Validasi parameter
 */
function pesanKeAPIGames($merchant_id, $secret_key, $kode_produk, $id_tujuan, $ref_id) {
    foreach (['merchant_id','secret_key','kode_produk','id_tujuan','ref_id'] as $req) {
        if ($$req === null || $$req === '') return ['error' => 'param-missing-' . $req];
    }
    $url = "https://api-games.id/v1/transaksi";
    $signature = md5($merchant_id . $secret_key . $ref_id);

    $data = [
        'merchant_id' => $merchant_id,
        'secret_key'  => $secret_key,
        'produk'      => $kode_produk,
        'tujuan'      => $id_tujuan,
        'ref_id'      => $ref_id,
        'signature'   => $signature,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('[apigames] curl-error: ' . $err);
        return ['error' => 'connect-fail'];
    }
    return json_decode((string)$response, true);
}

<?php
/**
 * ════════════════════════════════════════════════════════════════════
 *  TOPZONE — Database Helper (PDO + MySQLi compat)
 * ════════════════════════════════════════════════════════════════════
 *  Sediakan koneksi PDO yang aman pakai prepared statement, plus
 *  variabel mysqli ($koneksi/$conn) untuk backward-compat dengan
 *  kode lama. Pakai PDO untuk kode baru, mysqli untuk legacy.
 *
 *  PEMAKAIAN (rekomendasi):
 *    require_once __DIR__ . '/../includes/db.php';
 *
 *    // Query aman pakai prepared statement
 *    $user = db_one("SELECT * FROM users WHERE id = ?", [$id]);
 *    $list = db_all("SELECT * FROM games WHERE kategori = ?", [$kategori]);
 *    db_run("UPDATE orders SET status = ? WHERE id_order = ?", ['proses', $id]);
 * ════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/config.php';

// ─── Konfigurasi koneksi ────────────────────────────────────────────
$DB_HOST = env('DB_HOST', 'localhost');
$DB_USER = env('DB_USER', 'root');
$DB_PASS = env('DB_PASS', '');
$DB_NAME = env('DB_NAME', 'topzone');
$DB_PORT = (int) env('DB_PORT', 3306);


// ─── PDO (rekomendasi untuk kode baru) ──────────────────────────────
try {
    $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    if (APP_DEBUG) {
        die('PDO Error: ' . $e->getMessage());
    }
    error_log('[DB] ' . $e->getMessage());
    die('Database connection error. Coba lagi nanti.');
}


// ─── Mysqli (backward-compat dengan kode lama) ──────────────────────
$koneksi = @mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
if (!$koneksi) {
    if (APP_DEBUG) {
        die('Mysqli Error: ' . mysqli_connect_error());
    }
    error_log('[DB-mysqli] ' . mysqli_connect_error());
    die('Database connection error.');
}
mysqli_set_charset($koneksi, 'utf8mb4');
$conn = $koneksi; // alias


// ─── Helper functions (PDO-based, prepared statements) ──────────────

/**
 * Ambil 1 baris hasil query.
 *
 * @param string $sql    SQL dengan placeholder ? atau :name
 * @param array  $params Parameter binding
 * @return array|null
 */
function db_one(string $sql, array $params = []): ?array {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Ambil semua baris hasil query.
 */
function db_all(string $sql, array $params = []): array {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Eksekusi INSERT/UPDATE/DELETE.
 *
 * @return int Jumlah baris terpengaruh
 */
function db_run(string $sql, array $params = []): int {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Ambil nilai 1 kolom (scalar).
 */
function db_value(string $sql, array $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

/**
 * Insert dan return ID terakhir.
 */
function db_insert(string $sql, array $params = []): int {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $pdo->lastInsertId();
}

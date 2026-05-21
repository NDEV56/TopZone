<?php
session_start();
include '../Home/koneksi.php';

// =========================
// LIMIT LOGIN
// =========================
if (!isset($_SESSION['login_attempt'])) {
    $_SESSION['login_attempt'] = 0;
}

// =========================
// LOGIN
// =========================
if (isset($_POST['btn_login'])) {

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // =========================
    // VALIDASI KOSONG
    // =========================
    if (empty($username) || empty($password)) {

        $_SESSION['toast'] = [
            'icon' => 'error',
            'title' => 'Username dan password wajib diisi!'
        ];

        header('Location: tampilanlogin.php');
        exit;
    }

    // =========================
    // LIMIT LOGIN
    // =========================
    if ($_SESSION['login_attempt'] >= 5) {

        $_SESSION['toast'] = [
            'icon' => 'warning',
            'title' => 'Terlalu banyak percobaan login!'
        ];

        header('Location: tampilanlogin.php');
        exit;
    }

    try {

        // =========================
        // PREPARED STATEMENT
        // =========================
        $stmt = mysqli_prepare(
            $conn,
            "SELECT * FROM users WHERE username=? LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "s",
            $username
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        // =========================
        // USER ADA
        // =========================
        if (mysqli_num_rows($result) > 0) {

            $user = mysqli_fetch_assoc($result);

            // =========================
            // PASSWORD BENAR
            // =========================
            if (password_verify($password, $user['password'])) {

                // RESET LOGIN ATTEMPT
                $_SESSION['login_attempt'] = 0;

                // SESSION FIXATION
                session_regenerate_id(true);

                // SESSION USER
                $_SESSION['id_user']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['nama_user'] = $user['nama_user'];
                $_SESSION['email']     = $user['email'];
                $_SESSION['foto']      = $user['foto'];

                // ROLE
                $_SESSION['role'] = $user['role'] ?? 'user';

                // TOAST
                $_SESSION['toast'] = [
                    'icon' => 'success',
                    'title' => 'Login berhasil 🔥'
                ];

                header('Location: ../Home/index.php');
                exit;

            } else {

                $_SESSION['login_attempt']++;

                $_SESSION['toast'] = [
                    'icon' => 'error',
                    'title' => 'Password salah!'
                ];

                header('Location: tampilanlogin.php');
                exit;
            }

        } else {

            $_SESSION['login_attempt']++;

            $_SESSION['toast'] = [
                'icon' => 'warning',
                'title' => 'Username tidak ditemukan!'
            ];

            header('Location: tampilanlogin.php');
            exit;
        }

    } catch (mysqli_sql_exception $e) {

        error_log($e->getMessage());

        $_SESSION['toast'] = [
            'icon' => 'error',
            'title' => 'Terjadi kesalahan sistem!'
        ];

        header('Location: tampilanlogin.php');
        exit;
    }
}
?>
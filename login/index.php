<?php
session_start();

require_once __DIR__ . "/../koneksi.php";

/** @var mysqli $koneksi */

$error = "";

if (isset($_SESSION['id_admin'])) {
    header("Location: ../dashboard/index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = trim($_POST["password"] ?? "");

    if ($username === "" || $password === "") {
        $error = "Username dan password wajib diisi.";
    } else {
        $query = "SELECT id_admin, nama_admin, username, password_hash 
                  FROM admin 
                  WHERE username = ? 
                  LIMIT 1";

        $stmt = mysqli_prepare($koneksi, $query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);

            if ($result && mysqli_num_rows($result) === 1) {
                $admin = mysqli_fetch_assoc($result);

                if (password_verify($password, $admin["password_hash"])) {
                    $_SESSION["id_admin"] = $admin["id_admin"];
                    $_SESSION["nama_admin"] = $admin["nama_admin"];
                    $_SESSION["username"] = $admin["username"];

                    header("Location: ../dashboard/index.php");
                    exit;
                } else {
                    $error = "Username atau password salah.";
                }
            } else {
                $error = "Username atau password salah.";
            }

            mysqli_stmt_close($stmt);
        } else {
            $error = "Terjadi kesalahan pada sistem.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Sistem Prediksi Kemiskinan</title>

    <link rel="stylesheet" href="style-login.css">
</head>
<body>

    <main class="login-page">
        <section class="login-left">
            <div class="brand-box">
                <div class="logo-box">
                    <span>GIS</span>
                </div>

                <h1>Sistem Prediksi Kemiskinan</h1>
                <p>
                    Sistem prediksi jumlah penduduk miskin berbasis regresi linier
                    dan Sistem Informasi Geografis Kabupaten Sumba Timur.
                </p>
            </div>

            <div class="info-card">
                <h3>Fitur Sistem</h3>
                <ul>
                    <li>Prediksi jumlah penduduk miskin</li>
                    <li>Visualisasi peta SIG per kecamatan</li>
                    <li>Laporan data dan hasil prediksi</li>
                </ul>
            </div>
        </section>

        <section class="login-right">
            <div class="login-card">
                <div class="login-header">
                    <h2>Masuk Sistem</h2>
                    <p>Silakan login untuk mengakses dashboard admin.</p>
                </div>

                <?php if ($error !== "") : ?>
                    <div class="alert-error">
                        <?= htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" id="loginForm" autocomplete="off">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="input-wrapper">
                            <span class="input-icon">👤</span>
                            <input 
                                type="text" 
                                name="username" 
                                id="username" 
                                placeholder="Masukkan username"
                                value="<?= htmlspecialchars($_POST['username'] ?? ''); ?>"
                            >
                        </div>
                        <small class="error-text" id="usernameError"></small>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <span class="input-icon">🔒</span>
                            <input 
                                type="password" 
                                name="password" 
                                id="password" 
                                placeholder="Masukkan password"
                            >
                            <button type="button" class="toggle-password" id="togglePassword">
                                Lihat
                            </button>
                        </div>
                        <small class="error-text" id="passwordError"></small>
                    </div>

                    <button type="submit" class="btn-login">
                        Login
                    </button>
                </form>

                <div class="login-footer">
                    <p>© 2026 SIPREDIKSI GIS Sumba Timur</p>
                </div>
            </div>
        </section>
    </main>

    <script src="script-login.js"></script>
</body>
</html> 
<?php
// koneksi.php

$host     = "localhost";
$username = "root";
$password = "";
$database = "siprediksi_gis";

// Membuat koneksi ke database
$koneksi = mysqli_connect($host, $username, $password, $database);

// Mengecek koneksi
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Mengatur karakter agar mendukung teks Indonesia dengan baik
mysqli_set_charset($koneksi, "utf8mb4");
?>

    